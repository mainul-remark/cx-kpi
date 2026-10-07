<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserLeave extends Model
{
    public const PORTION_FULL = 'full';

    public const PORTION_HALF = 'half';

    /**
     * How much of the day a leave covers.
     */
    public const PORTIONS = [
        self::PORTION_FULL => 'Full Day',
        self::PORTION_HALF => 'Half Day',
    ];

    /**
     * The kinds of official leave.
     */
    public const TYPES = [
        'casual' => 'Casual',
        'sick'   => 'Sick',
        'annual' => 'Annual',
        'other'  => 'Other',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Where a leave stands: one set by an admin is approved at once, one asked for by the user waits as pending.
     * Only an approved leave counts for attendance, reports and the KPI.
     */
    public const STATUSES = [
        self::STATUS_PENDING  => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'user_id',
        'leave_date',
        'portion',
        'type',
        'note',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Put every listed user on the same leave on every listed day, from validated data.
     *
     * A leave already set for a user on one of the days is replaced. Targets of the days
     * are kept, so they count again when the leave is removed.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $dates  dates as Y-m-d
     * @param  int|null  $approvedBy  who decided on the leave, nobody yet for a pending one
     */
    public static function setForUsers(array $userIds, array $dates, array $data, ?int $approvedBy = null, string $status = self::STATUS_APPROVED): void
    {
        DB::transaction(function () use ($userIds, $dates, $data, $approvedBy, $status) {
            $now = now();
            $leaves = [];

            foreach ($userIds as $userId) {
                foreach ($dates as $date) {
                    $leaves[] = [
                        'user_id'     => $userId,
                        'leave_date'  => $date,
                        'portion'     => $data['portion'],
                        'type'        => $data['type'],
                        'note'        => $data['note'] ?? null,
                        'status'      => $status,
                        'approved_by' => $approvedBy,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }

            foreach (array_chunk($leaves, 500) as $chunk) {
                self::query()->upsert(
                    $chunk,
                    ['user_id', 'leave_date'],
                    ['portion', 'type', 'note', 'status', 'approved_by', 'updated_at']
                );
            }
        });
    }

    /**
     * How much of each day the given users are on approved leave within a range.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, string>> the portion per user id per Y-m-d date
     */
    public static function portionsByUser(string $from, string $to, array $userIds): array
    {
        $portions = [];

        if (empty($userIds)) {
            return $portions;
        }

        // a plain range on the date column keeps its index usable, the time part covers dates stored with one
        DB::table('user_leaves')
            ->whereBetween('leave_date', [$from, $to.' 23:59:59'])
            ->whereIn('user_id', $userIds)
            ->where('status', self::STATUS_APPROVED)
            ->orderBy('id')
            ->get(['user_id', 'leave_date', 'portion'])
            ->each(function (object $leave) use (&$portions) {
                $portions[$leave->user_id][substr((string) $leave->leave_date, 0, 10)] = $leave->portion;
            });

        return $portions;
    }

    /**
     * The reports that stand in the way of a full day of leave: a user cannot have worked and been off on one day.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $dates  dates as Y-m-d
     * @return list<string> one line per report, as "name on date"
     */
    public static function reportsOn(array $userIds, array $dates): array
    {
        if (empty($userIds) || empty($dates)) {
            return [];
        }

        return DailyReport::query()
            ->with('user:id,name')
            ->whereIn('user_id', $userIds)
            ->whereBetween('report_date', [min($dates), max($dates).' 23:59:59'])
            ->orderBy('report_date')
            ->get(['id', 'user_id', 'report_date'])
            ->filter(fn (DailyReport $report) => in_array($report->report_date->toDateString(), $dates, true))
            ->map(fn (DailyReport $report) => $report->user?->name.' on '.$report->report_date->toDateString())
            ->values()
            ->all();
    }
}
