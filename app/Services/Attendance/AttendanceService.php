<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSession;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use App\Notifications\AttendanceCheckOutReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private readonly OfficeLocator $locator)
    {
    }

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
                $where = $this->locator->resolve($geo, $ip);

                if (config('attendance.require_office') && $where['place'] !== OfficeLocator::OFFICE) {
                    throw ValidationException::withMessages([
                        'location' => 'You can only check in from an office location.',
                    ]);
                }

                $session = $user->attendanceSessions()->create([
                    'work_date' => $this->today(),
                    'checked_in_at' => now(),
                    'late_minutes' => $this->lateMinutes($user, now()),
                    'check_in_place' => $where['place'],
                    'office_location_id' => $where['office_id'],
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

    /**
     * The earlier days the user checked in on but filed no daily report for, oldest first.
     *
     * Today is left out: the report is written at the end of the day.
     *
     * @return array<int, string> dates as Y-m-d
     */
    public function owedReportDates(User $user): array
    {
        $dates = $user->attendanceSessions()
            ->where('work_date', '<', $this->today())
            ->orderBy('work_date')
            ->pluck('work_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return [];
        }

        $reported = DB::table('daily_reports')
            ->where('user_id', $user->id)
            ->whereBetween('report_date', [$dates->first(), $dates->last().' 23:59:59'])
            ->pluck('report_date')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->all();

        return $dates->reject(fn (string $date) => in_array($date, $reported, true))->values()->all();
    }

    /** The start and grace of the user's shift, the default one when none is assigned. */
    public function shiftOf(User $user): array
    {
        $shift = $user->workShift;

        return [
            'start_time' => $shift?->start_time ?? config('attendance.default_shift.start_time'),
            'grace_minutes' => $shift?->grace_minutes ?? config('attendance.default_shift.grace_minutes'),
        ];
    }

    /**
     * How many minutes after the shift start the first check in of a working day was, 0 when on time.
     *
     * Null when the check is not judged: a later session of the day, an off day or holiday, or a day of leave.
     */
    public function lateMinutes(User $user, Carbon $at): ?int
    {
        $timezone = config('attendance.timezone');
        $local = $at->copy()->timezone($timezone);
        $date = $local->toDateString();

        $judged = Holiday::workingDays($date, $date) !== []
            && !isset(UserLeave::portionsByUser($date, $date, [$user->id])[$user->id][$date])
            && !$user->attendanceSessions()->whereDate('work_date', $date)->exists();

        if (!$judged) {
            return null;
        }

        $shift = $this->shiftOf($user);
        $start = Carbon::parse($date.' '.$shift['start_time'], $timezone);

        if ($local->lte($start->copy()->addMinutes((int) $shift['grace_minutes']))) {
            return 0;
        }

        return (int) $start->diffInMinutes($local);
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
     * The check-in sessions of a range, newest first, ready for the attendance page.
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
            ->whereBetween('work_date', [$from, $to.' 23:59:59'])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('work_date')
            ->orderByDesc('checked_in_at')
            ->limit($limit + 1)
            ->get();

        $truncated = $sessions->count() > $limit;

        $rows = $sessions->take($limit)->map(function (AttendanceSession $session) use ($forManager, $timezone) {
            $in = $session->checked_in_at->copy()->timezone($timezone);
            $out = $session->checked_out_at?->copy()->timezone($timezone);

            $row = [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'name' => $session->user?->name,
                'work_date' => $session->work_date->toDateString(),
                'checked_in' => $in->format('h:i A'),
                'checked_out' => $out?->format('h:i A'),
                // a closed session shows how long it ran; an incomplete one has no known end
                'minutes' => $out ? (int) $in->diffInMinutes($out) : null,
                'status' => $this->status($session),
                'late_minutes' => $session->late_minutes,
                'place' => $session->check_in_place,
                'note' => $session->adjustment_note,
            ];

            if ($forManager) {
                $row += [
                    'in_location' => $this->location($session->check_in_lat, $session->check_in_lng),
                    'out_location' => $this->location($session->check_out_lat, $session->check_out_lng),
                    'checked_in_input' => $in->format('Y-m-d\TH:i'),
                    'checked_out_input' => $out?->format('Y-m-d\TH:i'),
                ];
            }

            return $row;
        })->values()->all();

        return ['rows' => $rows, 'truncated' => $truncated];
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
        if ($checkedOutAt->lt($session->checked_in_at) || $checkedOutAt->isFuture()) {
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
