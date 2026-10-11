<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a user is asked to do in a whole month for one activity, used when kpi.target_mode is "period".
 */
class PeriodTarget extends Model
{
    protected $fillable = [
        'user_id',
        'period_month',
        'activity',
        'target',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date:Y-m-d',
            'target'       => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Replace the targets of a user for a month with the given ones, keyed by activity.
     *
     * An activity left empty or at 0 ends up without a target.
     *
     * @param  array<string, int|string|null>  $targets
     */
    public static function setForUser(int $userId, Carbon|string $month, array $targets, ?int $setBy = null): void
    {
        $start = Carbon::parse($month)->startOfMonth()->toDateString();

        DB::transaction(function () use ($userId, $start, $targets, $setBy) {
            self::query()->where('user_id', $userId)->whereBetween('period_month', [$start, $start.' 23:59:59'])->delete();

            $now = now();
            $rows = [];

            foreach ($targets as $activity => $target) {
                if ($target === null || $target === '' || (int) $target <= 0) {
                    continue;
                }

                $rows[] = [
                    'user_id'      => $userId,
                    'period_month' => $start,
                    'activity'     => $activity,
                    'target'       => (int) $target,
                    'set_by'       => $setBy,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            if ($rows) {
                self::query()->insert($rows);
            }
        });
    }

    /**
     * The monthly targets of the given users for the months a range touches.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, array<string, int>>> targets per user id per Y-m-01 month per activity
     */
    public static function forRange(string $from, string $to, array $userIds): array
    {
        $figures = [];

        if (empty($userIds)) {
            return $figures;
        }

        $first = Carbon::parse($from)->startOfMonth()->toDateString();
        $last = Carbon::parse($to)->startOfMonth()->toDateString();

        // a plain range on the date column keeps its index usable, the time part covers dates stored with one
        DB::table('period_targets')
            ->whereBetween('period_month', [$first, $last.' 23:59:59'])
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'period_month', 'activity', 'target'])
            ->each(function (object $row) use (&$figures) {
                $figures[$row->user_id][substr((string) $row->period_month, 0, 10)][$row->activity] = (int) $row->target;
            });

        return $figures;
    }
}
