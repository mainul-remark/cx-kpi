<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Notifications\AttendanceCheckOutReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /** Today's date in the attendance timezone, the day a new session belongs to. */
    public function today(): string
    {
        return Carbon::now(config('attendance.timezone'))->toDateString();
    }

    /**
     * Close, as forgotten, every open session of an earlier day. Returns how many were closed.
     *
     * The checkout time is deliberately left empty: the user never left at a known time.
     */
    public function closeStale(?int $userId = null): int
    {
        return AttendanceSession::query()
            ->open()
            ->where('work_date', '<', $this->today())
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->update([
                'close_reason' => AttendanceSession::REASON_AUTO,
                'auto_closed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function currentSession(User $user): ?AttendanceSession
    {
        return $user->attendanceSessions()->open()->latest('checked_in_at')->first();
    }

    /**
     * Whether the user has to file a daily report before using the system.
     *
     * That is so when they worked today, or checked in on a day and never checked out (even when the system closed it
     * as forgotten), and no report exists on or after that day. Filing today's report clears it.
     */
    public function mustFileReport(User $user): bool
    {
        $days = $user->attendanceSessions()
            ->where(fn ($query) => $query->whereNull('checked_out_at')->orWhere('work_date', $this->today()))
            ->pluck('work_date')
            ->map(fn ($date) => min(Carbon::parse($date)->toDateString(), today()->toDateString()));

        if ($days->isEmpty()) {
            return false;
        }

        $lastReport = DB::table('daily_reports')->where('user_id', $user->id)->max('report_date');

        return $lastReport === null || $days->contains(fn (string $day) => $day > substr((string) $lastReport, 0, 10));
    }

    /**
     * Give the sessions the user forgot to end a checkout of 11:59 pm on their own day, and keep them marked as
     * "did not check out". Called when the user files a daily report. Returns how many were set.
     *
     * The session stays closed by the system, so the day still reads Incomplete and its hours stay unknown.
     */
    public function finalizeForgotten(User $user): int
    {
        $timezone = config('attendance.timezone');
        $count = 0;

        $user->attendanceSessions()
            ->whereNull('checked_out_at')
            ->whereNull('adjusted_by')
            ->where('work_date', '<', $this->today())
            ->get()
            ->each(function (AttendanceSession $session) use ($timezone, &$count) {
                $endOfDay = Carbon::parse($session->work_date->toDateString().' 23:59:00', $timezone)
                    ->setTimezone(config('app.timezone'));

                $session->forceFill([
                    'checked_out_at' => $endOfDay,
                    'close_reason' => AttendanceSession::REASON_AUTO,
                    'auto_closed_at' => $session->auto_closed_at ?? now(),
                ])->save();
                $count++;
            });

        return $count;
    }

    /** Forgotten sessions the user has not been warned about yet. */
    public function pendingWarnings(User $user): Collection
    {
        return $user->attendanceSessions()
            ->forgotten()
            ->whereNull('acknowledged_at')
            ->orderBy('work_date')
            ->get(['id', 'work_date']);
    }

    public function acknowledge(User $user): int
    {
        $this->markRemindersRead($user);

        return $user->attendanceSessions()
            ->forgotten()
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now()]);
    }

    /**
     * Start a session. Checking in again while one is open returns that session.
     *
     * @param  array{lat?: float|null, lng?: float|null, accuracy?: float|null}  $geo
     * @return array{session: AttendanceSession, warnings: Collection, created: bool}
     */
    public function checkIn(User $user, array $geo, ?string $ip, ?string $device): array
    {
        return DB::transaction(function () use ($user, $geo, $ip, $device) {
            // serialise the user's requests, so a double click cannot open two sessions
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $this->closeStale($user->id);

            // read before they are acknowledged just below, so the caller can show them once
            $warnings = $this->pendingWarnings($user);

            $session = $this->currentSession($user);
            $created = false;

            if (!$session) {
                $session = $user->attendanceSessions()->create([
                    'work_date' => $this->today(),
                    'checked_in_at' => now(),
                    'check_in_lat' => $geo['lat'] ?? null,
                    'check_in_lng' => $geo['lng'] ?? null,
                    'check_in_accuracy' => $geo['accuracy'] ?? null,
                    'check_in_ip' => $ip,
                    'check_in_device' => $this->device($device),
                ]);
                $created = true;
            }

            $this->acknowledge($user);

            return compact('session', 'warnings', 'created');
        });
    }

    /**
     * End the open session. Returns null when there is none to end.
     *
     * @param  array{lat?: float|null, lng?: float|null, accuracy?: float|null}  $geo
     */
    public function checkOut(User $user, array $geo, ?string $ip, ?string $device): ?AttendanceSession
    {
        return DB::transaction(function () use ($user, $geo, $ip, $device) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $this->closeStale($user->id);

            $session = $this->currentSession($user);

            if (!$session) {
                return null;
            }

            $this->markRemindersRead($user);

            $session->update([
                'checked_out_at' => now(),
                'close_reason' => AttendanceSession::REASON_MANUAL,
                'check_out_lat' => $geo['lat'] ?? null,
                'check_out_lng' => $geo['lng'] ?? null,
                'check_out_accuracy' => $geo['accuracy'] ?? null,
                'check_out_ip' => $ip,
                'check_out_device' => $this->device($device),
            ]);

            return $session;
        });
    }

    /** Whether an end-of-day reminder is waiting for the user, who is still checked in. */
    public function hasPendingReminder(User $user): bool
    {
        return $user->unreadNotifications()->where('type', AttendanceCheckOutReminder::class)->exists()
            && $this->currentSession($user) !== null;
    }

    public function markRemindersRead(User $user): void
    {
        $user->unreadNotifications()->where('type', AttendanceCheckOutReminder::class)->update(['read_at' => now()]);
    }

    /** Remind every user who is still checked in today. Returns how many were reminded. */
    public function remindOpenSessions(): int
    {
        $reminded = 0;

        AttendanceSession::query()
            ->open()
            ->forDate($this->today())
            ->with('user')
            ->get()
            ->each(function (AttendanceSession $session) use (&$reminded) {
                try {
                    $session->user->notify(new AttendanceCheckOutReminder(
                        $session->checked_in_at->copy()->timezone(config('attendance.timezone'))->format('h:i A')
                    ));
                    $reminded++;
                } catch (\Throwable $th) {
                    // one failing mailbox must not stop the others being reminded
                    report($th);
                }
            });

        return $reminded;
    }

    /**
     * The check-in days of a range, newest first, ready for the attendance page.
     *
     * A manager also gets the places and addresses of the check; the user only their own times.
     *
     * @return array{rows: array<int, array<string, mixed>>, truncated: bool}
     */
    public function sessions(string $from, string $to, ?int $userId, bool $forManager): array
    {
        $limit = (int) config('attendance.max_listed_sessions');
        $timezone = config('attendance.timezone');

        $sessions = AttendanceSession::query()
            ->with('user:id,name')
            ->whereBetween('work_date', [$from, $to])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('work_date')
            ->orderByDesc('checked_in_at')
            ->limit($limit + 1)
            ->get();

        $truncated = $sessions->count() > $limit;

        // one row per user and day: the first check in, the last check out and the worked time in between
        $rows = $sessions->take($limit)
            ->groupBy(fn (AttendanceSession $session) => $session->user_id.'|'.$session->work_date->toDateString())
            ->map(function ($day) use ($forManager, $timezone) {
                $day = $day->sortBy('checked_in_at')->values();
                $first = $day->first();
                $last = $day->last();

                $in = $first->checked_in_at->copy()->timezone($timezone);
                $out = $last->checked_out_at?->copy()->timezone($timezone);
                $lastIn = $last->checked_in_at->copy()->timezone($timezone);

                // a forgotten session has no known end, whatever end of day was filled in for it
                $closed = $day->filter(fn (AttendanceSession $session) => $session->checked_out_at !== null && !$session->isAutoClosed());

                $row = [
                    // the session a manager corrects: the one that ends the day
                    'id' => $last->id,
                    'user_id' => $first->user_id,
                    'name' => $first->user?->name,
                    'work_date' => $first->work_date->toDateString(),
                    'checked_in' => $in->format('h:i A'),
                    'checked_out' => $out?->format('h:i A'),
                    // worked time of the ended sessions; an incomplete one has no known end
                    'minutes' => $closed->isEmpty() ? null : (int) $closed->sum(
                        fn (AttendanceSession $session) => $session->checked_in_at->diffInMinutes($session->checked_out_at)
                    ),
                    'sessions' => $day->count(),
                    'status' => $this->dayStatus($day),
                    'note' => $day->pluck('adjustment_note')->filter()->last(),
                ];

                if ($forManager) {
                    $row += [
                        'in_location' => $this->location($first->check_in_lat, $first->check_in_lng),
                        'out_location' => $this->location($last->check_out_lat, $last->check_out_lng),
                        'checked_in_input' => $lastIn->format('Y-m-d\TH:i'),
                        'checked_out_input' => $out?->format('Y-m-d\TH:i'),
                    ];
                }

                return $row;
            })->values()->all();

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    /** The status of a day: still open wins, then a forgotten check out, then a manager correction. */
    private function dayStatus($day): string
    {
        $statuses = $day->map(fn (AttendanceSession $session) => $this->status($session));

        foreach (['open', 'incomplete', 'adjusted'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return 'completed';
    }

    /** Field users who are checked in right now. */
    public function currentlyIn(): array
    {
        $timezone = config('attendance.timezone');

        return AttendanceSession::query()
            ->open()
            ->with('user:id,name')
            ->orderBy('checked_in_at')
            ->get()
            ->map(fn (AttendanceSession $session) => [
                'name' => $session->user?->name,
                'since' => $session->checked_in_at->copy()->timezone($timezone)->format('d M, h:i A'),
            ])
            ->all();
    }

    /**
     * A manager fixes when a session ended, for example one the user forgot to end.
     *
     * @throws ValidationException when the time falls before the check in or in the future
     */
    public function adjust(AttendanceSession $session, User $manager, Carbon $checkedOutAt, string $note): AttendanceSession
    {
        // the form picks a minute, the check in keeps its seconds: compare on the minute
        if ($checkedOutAt->lt($session->checked_in_at->copy()->startOfMinute()) || $checkedOutAt->isFuture()) {
            throw ValidationException::withMessages([
                'checked_out_at' => 'The check out must be after the check in, and not in the future.',
            ]);
        }

        return DB::transaction(function () use ($session, $manager, $checkedOutAt, $note) {
            $before = $session->only(['checked_out_at', 'close_reason']);

            $session->update([
                'checked_out_at' => $checkedOutAt,
                'close_reason' => AttendanceSession::REASON_ADMIN,
                'adjusted_by' => $manager->id,
                'adjustment_note' => $note,
            ]);

            activity('data')
                ->performedOn($session)
                ->causedBy($manager)
                ->withProperties(['old' => $before, 'attributes' => $session->only(['checked_out_at', 'close_reason']), 'note' => $note])
                ->log('attendance session adjusted');

            return $session;
        });
    }

    private function status(AttendanceSession $session): string
    {
        return match (true) {
            $session->close_reason === AttendanceSession::REASON_ADMIN => 'adjusted',
            $session->close_reason === AttendanceSession::REASON_AUTO => 'incomplete',
            $session->checked_out_at === null => 'open',
            default => 'completed',
        };
    }

    private function location(mixed $lat, mixed $lng): ?array
    {
        return $lat !== null && $lng !== null ? ['lat' => (float) $lat, 'lng' => (float) $lng] : null;
    }

    private function device(?string $agent): ?string
    {
        return $agent !== null ? mb_substr($agent, 0, 255) : null;
    }
}
