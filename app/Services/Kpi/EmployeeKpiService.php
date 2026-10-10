<?php

namespace App\Services\Kpi;

use App\Models\AttendanceSession;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\Dashboard\ReportDashboardService;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scores each user on what they reported against the targets set for them.
 *
 * Reports and targets are matched day by day and activity by activity, then summed and divided once,
 * so a busy day weighs more than a light one. Only the activities with a target on a day count,
 * and a day of official leave, an off day or a day without a target counts for nothing either way.
 * Each activity counts by its weight from config/kpi.php, the totals shown stay plain counts.
 */
class EmployeeKpiService
{
    /**
     * The highest score a user can get, however far above the target they went.
     */
    public const MAX_SCORE = 100;

    /**
     * The sheet of the field users for a range, or of one user, with the best score first.
     *
     * @return list<array<string, mixed>>
     */
    public function sheet(string $from, string $to, ?int $userId = null): array
    {
        $users = User::query()
            ->when($userId, fn ($query) => $query->whereKey($userId), fn ($query) => $query->where('usages_sector', 'field'))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'created_at']);

        return $this->forUsers($users, $from, $to)
            ->sortBy([
                // a user without a target goes last
                fn (array $a, array $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1),
                fn (array $a, array $b) => ($b['pct'] ?? -1) <=> ($a['pct'] ?? -1),
                fn (array $a, array $b) => $b['actual_total'] <=> $a['actual_total'],
            ])
            ->values()
            ->all();
    }

    /**
     * The figures of one user for a range, with every day of it.
     *
     * @return array<string, mixed>
     */
    public function detail(User $user, string $from, string $to): array
    {
        return $this->forUsers(collect([$user]), $from, $to, true)->first();
    }

    /**
     * The figures of all the given users together.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function summary(array $rows): array
    {
        $target = array_sum(array_column($rows, 'target_total'));
        $actual = array_sum(array_column($rows, 'actual_total'));
        $weightedTarget = array_sum(array_column($rows, 'weighted_target'));
        $weightedActual = array_sum(array_column($rows, 'weighted_actual'));
        $scores = array_filter(array_column($rows, 'score'), fn ($score) => $score !== null);

        return [
            'users' => count($rows),
            'scored' => count($scores),
            'target_total' => $target,
            'actual_total' => $actual,
            'pct' => self::pct($weightedActual, $weightedTarget),
            'average_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null,
            'absent' => array_sum(array_column($rows, 'absent')),
            'leave' => array_sum(array_column($rows, 'leave')),
            'late_days' => array_sum(array_column($rows, 'late_days')),
            'incomplete_days' => array_sum(array_column($rows, 'incomplete_days')),
        ];
    }

    /**
     * The figures of each given user for a range, keyed by user id.
     *
     * Days still to come are left out, so a target set ahead is not held against anyone yet.
     *
     * @param  Collection<int, User>  $users  with their id, name and created_at
     * @return Collection<int, array<string, mixed>>
     */
    public function forUsers(Collection $users, string $from, string $to, bool $withDays = false): Collection
    {
        $until = min($to, today()->toDateString());
        $userIds = $users->map(fn (User $user) => $user->id)->all();
        $hasDays = $from <= $until && !empty($userIds);

        $working = $hasDays ? array_flip(Holiday::workingDays($from, $until)) : [];
        $targets = $hasDays ? $this->figures('target', $from, $until, $userIds) : [];
        $reports = $hasDays ? $this->figures('report', $from, $until, $userIds) : [];
        $leaves = $hasDays ? UserLeave::portionsByUser($from, $until, $userIds) : [];
        $punctuality = $hasDays ? $this->punctuality($from, $until, $userIds) : [];
        $dates = $hasDays ? array_map(fn ($day) => $day->toDateString(), CarbonPeriod::create($from, $until)->toArray()) : [];

        $weights = array_map('floatval', (array) config('kpi.weights', []));

        return $users->mapWithKeys(function (User $user) use ($dates, $working, $targets, $reports, $leaves, $weights, $withDays, $punctuality) {
            $joined = $user->created_at?->toDateString();
            $breakdown = array_fill_keys(array_keys(ReportDashboardService::METRICS), ['target' => null, 'actual' => 0]);
            // the same figures per activity, where each platform and each project stands on its own
            $activities = [];
            $targetTotal = $actualTotal = $targetDays = $worked = $absent = 0;
            $weightedTarget = $weightedActual = $leave = 0.0;
            $days = [];

            foreach ($dates as $date) {
                $day = ['date' => $date, 'status' => null, 'half_leave' => false, 'target' => null, 'actual' => null, 'pct' => null];
                $portion = $leaves[$user->id][$date] ?? null;
                $done = $reports[$user->id][$date] ?? null;

                if (!isset($working[$date])) {
                    $day['status'] = 'off';
                } elseif ($joined && $date < $joined) {
                    $day['status'] = 'not_joined';
                } elseif ($portion === UserLeave::PORTION_FULL) {
                    $day['status'] = 'leave';
                    $leave += 1;
                } else {
                    $half = $portion === UserLeave::PORTION_HALF;
                    $leave += $half ? 0.5 : 0;
                    $day['half_leave'] = $half;
                    $day['status'] = $done !== null ? 'worked' : 'absent';
                    $worked += $done !== null ? 1 : 0;
                    $absent += $done !== null ? 0 : 1;

                    $goals = $targets[$user->id][$date] ?? [];

                    if (!empty($goals)) {
                        $day['target'] = $day['actual'] = 0;
                        $dayTarget = $dayActual = 0.0;

                        foreach ($goals as $activity => $goal) {
                            // half a day of leave halves what is asked of the day
                            $goal = $half ? (int) ceil($goal / 2) : $goal;
                            $count = $done[$activity] ?? 0;
                            $metric = self::metricOf($activity);

                            $breakdown[$metric]['target'] += $goal;
                            $breakdown[$metric]['actual'] += $count;
                            $activities[$activity] ??= ['metric' => $metric, 'target' => 0, 'actual' => 0];
                            $activities[$activity]['target'] += $goal;
                            $activities[$activity]['actual'] += $count;
                            $day['target'] += $goal;
                            $day['actual'] += $count;
                            $dayTarget += $goal * ($weights[$metric] ?? 1.0);
                            $dayActual += $count * ($weights[$metric] ?? 1.0);
                        }

                        $day['pct'] = self::pct($dayActual, $dayTarget);
                        $targetTotal += $day['target'];
                        $actualTotal += $day['actual'];
                        $weightedTarget += $dayTarget;
                        $weightedActual += $dayActual;
                        $targetDays++;
                    }
                }

                $days[] = $day;
            }

            $pct = self::pct($weightedActual, $weightedTarget);

            $row = [
                'id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee_id,
                'target_total' => $targetTotal,
                'actual_total' => $actualTotal,
                'weighted_target' => $weightedTarget,
                'weighted_actual' => $weightedActual,
                'pct' => $pct,
                'score' => $pct === null ? null : min($pct, (float) self::MAX_SCORE),
                'target_days' => $targetDays,
                'worked' => $worked,
                'absent' => $absent,
                'leave' => $leave,
                // from check ins; they only inform, the score above is not changed by them
                'checked_in_days' => $punctuality[$user->id]['checked_in'] ?? 0,
                'late_days' => $punctuality[$user->id]['late'] ?? 0,
                'incomplete_days' => $punctuality[$user->id]['incomplete'] ?? 0,
                'activities' => $activities,
                'breakdown' => collect($breakdown)
                    ->map(fn (array $figures, string $metric) => [
                        'key' => $metric,
                        'label' => ReportDashboardService::METRICS[$metric],
                        'target' => $figures['target'],
                        'actual' => $figures['actual'],
                        'pct' => $figures['target'] === null ? null : self::pct($figures['actual'], $figures['target']),
                    ])
                    ->values()
                    ->all(),
            ];

            if ($withDays) {
                $row['days'] = $days;
            }

            return [$user->id => $row];
        });
    }

    /**
     * How many days each user checked in on, was late on, and forgot to check out of.
     *
     * Only days with a check in are judged, so a user who never checks in is not marked down for it.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{checked_in: int, late: int, incomplete: int}>
     */
    private function punctuality(string $from, string $to, array $userIds): array
    {
        $figures = [];
        $seen = [];

        AttendanceSession::query()
            ->whereBetween('work_date', [$from, $to.' 23:59:59'])
            ->whereIn('user_id', $userIds)
            ->orderBy('checked_in_at')
            ->get(['user_id', 'work_date', 'late_minutes', 'close_reason'])
            ->each(function (AttendanceSession $session) use (&$figures, &$seen) {
                $date = $session->work_date->toDateString();
                $figures[$session->user_id] ??= ['checked_in' => 0, 'late' => 0, 'incomplete' => 0];

                // a day counts once, however many sessions it holds
                if (!isset($seen[$session->user_id][$date])) {
                    $seen[$session->user_id][$date] = true;
                    $figures[$session->user_id]['checked_in']++;
                }
                if ($session->late_minutes > 0) {
                    $figures[$session->user_id]['late']++;
                }
                if ($session->close_reason === AttendanceSession::REASON_AUTO) {
                    $figures[$session->user_id]['incomplete']++;
                }
            });

        return $figures;
    }

    /**
     * The same figures with every target taken out, for a viewer who is not shown targets.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function withoutTargets(array $row): array
    {
        $row['target_total'] = $row['weighted_target'] = $row['weighted_actual'] = null;
        $row['breakdown'] = array_map(fn (array $figures) => ['target' => null] + $figures, $row['breakdown']);
        $row['activities'] = array_map(fn (array $figures) => ['target' => null] + $figures, $row['activities']);

        if (isset($row['days'])) {
            $row['days'] = array_map(fn (array $day) => ['target' => null] + $day, $row['days']);
        }

        return $row;
    }

    /**
     * What was entered per user per day per activity, for the reports or for the targets.
     *
     * Each platform and each project is an activity of its own, and an activity left without a target is left out.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, array<string, int>>> counts per user id per Y-m-d date per activity
     */
    private function figures(string $source, string $from, string $to, array $userIds): array
    {
        $config = ReportDashboardService::SOURCES[$source];
        $parent = $config['table'];
        $figures = [];

        // a plain range on the date column keeps its index usable, the time part covers dates stored with one
        $scoped = fn () => DB::table($parent)
            ->whereBetween($parent.'.'.$config['date'], [$from, $to.' 23:59:59'])
            ->whereIn($parent.'.user_id', $userIds);

        $main = $scoped()->orderBy('id')->get(['user_id', $config['date'].' as day', 'outbound_calls', 'inbound_calls', 'message_replies']);

        foreach ($main as $row) {
            $date = substr((string) $row->day, 0, 10);
            // a report is kept even when empty, as it still shows the user worked that day
            $figures[$row->user_id][$date] ??= [];

            foreach (['outbound_calls', 'inbound_calls', 'message_replies'] as $activity) {
                if ($row->{$activity} !== null) {
                    $figures[$row->user_id][$date][$activity] = (int) $row->{$activity};
                }
            }
        }

        $children = [
            'platform' => [$config['platforms'], 'social_platform_id', 'total_replies'],
            'project' => [$config['projects'], 'project_id', 'total_calls'],
        ];

        foreach ($children as $prefix => [$table, $foreignKey, $column]) {
            $rows = $scoped()
                ->join($table, $table.'.'.$config['key'], '=', $parent.'.id')
                ->orderBy($table.'.id')
                ->get([$parent.'.user_id', $parent.'.'.$config['date'].' as day', $table.'.'.$foreignKey.' as item', $table.'.'.$column.' as total']);

            foreach ($rows as $row) {
                $figures[$row->user_id][substr((string) $row->day, 0, 10)][$prefix.':'.$row->item] = (int) $row->total;
            }
        }

        return $figures;
    }

    /**
     * The dashboard metric an activity belongs to: a platform is comment replies, a project is project calls.
     */
    private static function metricOf(string $activity): string
    {
        return match (true) {
            str_starts_with($activity, 'platform:') => 'comment_replies',
            str_starts_with($activity, 'project:') => 'project_calls',
            default => $activity,
        };
    }

    private static function pct(int|float $actual, int|float $target): ?float
    {
        return $target > 0 ? round($actual / $target * 100, 1) : null;
    }
}
