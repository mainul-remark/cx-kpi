<?php

namespace App\Models;

use App\Services\Kpi\EmployeeKpiService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The KPI of a user for a month as it stood when the month was closed,
 * so a report or target edited afterwards no longer changes it.
 */
class KpiSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'period_month',
        'target_total',
        'actual_total',
        'pct',
        'score',
        'target_days',
        'worked',
        'absent',
        'leave',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date:Y-m-d',
            'target_total' => 'integer',
            'actual_total' => 'integer',
            'pct'          => 'float',
            'score'        => 'float',
            'target_days'  => 'integer',
            'worked'       => 'integer',
            'absent'       => 'integer',
            'leave'        => 'float',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Freeze the KPI of every field user for a month.
     *
     * A month already frozen for a user is left as it is, unless it is asked to be worked out again.
     *
     * @return int the number of users whose score was saved
     */
    public static function freezeMonth(Carbon $month, bool $overwrite = false): int
    {
        $start = $month->copy()->startOfMonth();
        $from = $start->toDateString();
        $to = $start->copy()->endOfMonth()->toDateString();

        return DB::transaction(function () use ($from, $to, $overwrite) {
            $frozen = self::query()
                ->whereBetween('period_month', [$from, $from.' 23:59:59'])
                ->get()
                ->keyBy('user_id');

            $saved = 0;

            foreach (app(EmployeeKpiService::class)->sheet($from, $to) as $row) {
                $snapshot = $frozen->get($row['id']);

                if ($snapshot && !$overwrite) {
                    continue;
                }

                $snapshot ??= new self(['user_id' => $row['id'], 'period_month' => $from]);
                $snapshot->fill([
                    'target_total' => $row['target_total'],
                    'actual_total' => $row['actual_total'],
                    'pct'          => $row['pct'],
                    'score'        => $row['score'],
                    'target_days'  => $row['target_days'],
                    'worked'       => $row['worked'],
                    'absent'       => $row['absent'],
                    'leave'        => $row['leave'],
                    'generated_at' => now(),
                ]);
                $snapshot->save();
                $saved++;
            }

            return $saved;
        });
    }
}
