<?php

namespace App\Services\Dashboard;

use App\Models\AttendanceSession;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\Kpi\EmployeeKpiService;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the dashboard figures from the daily reports and the targets set for them.
 *
 * Every figure is summed by the database, so the cost follows the number of
 * days and users on screen rather than the number of reports stored.
 */
class ReportDashboardService
{
    /**
     * The activities shown on the dashboard, in display order.
     */
    public const METRICS = [
        'outbound_calls'  => 'Outbound Calls',
        'inbound_calls'   => 'Inbound Calls',
        'message_replies' => 'Message Replies',
        'comment_replies' => 'Comment Replies',
        'project_calls'   => 'Project Calls',
    ];

    public const PRESETS = ['today', 'week', '15days', 'month', 'custom'];

    /**
     * The longest range, in days, the dashboard covers at once.
     */
    public const MAX_RANGE_DAYS = 366;

    /**
     * Where reports and targets keep the same figures.
     */
    public const SOURCES = [
        'report' => [
            'table' => 'daily_reports',
            'date' => 'report_date',
            'platforms' => 'daily_report_platform_replies',
            'projects' => 'daily_report_project_calls',
            'key' => 'daily_report_id',
        ],
        'target' => [
            'table' => 'daily_targets',
            'date' => 'target_date',
            'platforms' => 'daily_target_platform_replies',
            'projects' => 'daily_target_project_calls',
            'key' => 'daily_target_id',
        ],
    ];

    public function __construct(private EmployeeKpiService $kpi)
    {
    }

    /**
     * The first and last day of a preset, or the given days for a custom range.
     *
     * @return array{0: string, 1: string} dates as Y-m-d
     */
    public function range(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = today();

        return match ($preset) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->startOfWeek(Holiday::WEEK_START_DAY)->toDateString(), $today->toDateString()],
            '15days' => [$today->copy()->subDays(14)->toDateString(), $today->toDateString()],
            'custom' => [$from, $to],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        };
    }

    /**
     * Everything the dashboard shows for a range, for one user or for everyone.
     *
     * Targets are left out entirely unless asked for, so they never reach a viewer who should not see them.
     */
    public function build(string $from, string $to, ?int $userId, bool $withTargets, bool $withUsers): array
    {
        $days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $previousTo = Carbon::parse($from)->subDay()->toDateString();
        $previousFrom = Carbon::parse($from)->subDays($days)->toDateString();

        $actual = $this->totals('report', $from, $to, $userId)->get('all', []);
        $previous = $this->totals('report', $previousFrom, $previousTo, $userId)->get('all', []);
        $target = $withTargets ? $this->totals('target', $from, $to, $userId)->get('all', []) : [];

        $kpis = [];
        foreach (self::METRICS as $key => $label) {
            $value = (int) ($actual[$key] ?? 0);
            $before = (int) ($previous[$key] ?? 0);
            $goal = isset($target[$key]) ? (int) $target[$key] : null;

            $kpis[] = [
                'key' => $key,
                'label' => $label,
                'actual' => $value,
                'previous' => $before,
                'change_pct' => $before > 0 ? round(($value - $before) / $before * 100, 1) : null,
                'target' => $goal,
                'achievement_pct' => $goal ? round($value / $goal * 100, 1) : null,
            ];
        }

        return [
            'range' => [
                'from' => $from,
                'to' => $to,
                'days' => $days,
                'previous_from' => $previousFrom,
                'previous_to' => $previousTo,
            ],
            'show_targets' => $withTargets,
            'kpis' => $kpis,
            'submissions' => $this->submissions($from, $to, $userId, (int) ($actual['entries'] ?? 0)),
            'trend' => $this->trend($from, $to, $userId),
            'platforms' => $this->breakdown('platforms', 'social_platforms', 'social_platform_id', 'total_replies', $from, $to, $userId, $withTargets),
            'projects' => $this->breakdown('projects', 'projects', 'project_id', 'total_calls', $from, $to, $userId, $withTargets),
            'users' => $withUsers ? $this->users($from, $to, $userId, $withTargets) : null,
            'attendance' => $withUsers ? $this->attendance($from, $to, $userId) : null,
        ];
    }

    /**
     * The attendance sheet of the field users: a report on a day is what counts as being present.
     *
     * Each user carries one mark per day of the range: P present, A absent, L a full day of official leave,
     * O weekly off day, H holiday, F a day still to come and N a day before the user was added.
     * A day of leave counts as neither present nor absent.
     */
    public function attendance(string $from, string $to, ?int $userId): array
    {
        $today = today()->toDateString();

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$from, $to.' 23:59:59'])
            ->get(['holiday_date', 'title'])
            ->mapWithKeys(fn (Holiday $holiday) => [$holiday->holiday_date->toDateString() => $holiday->title]);

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $days[] = [
                'date' => $date,
                'mark' => $holidays->has($date) ? 'H' : ($day->dayOfWeek === Holiday::WEEKLY_OFF_DAY ? 'O' : ($date > $today ? 'F' : 'A')),
                'holiday' => $holidays->get($date),
            ];
        }

        $users = User::query()
            ->when($userId, fn ($query) => $query->whereKey($userId), fn ($query) => $query->where('usages_sector', 'field'))
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        $reported = [];
        $this->scoped('report', $from, $to, $userId)
            ->whereIn('user_id', $users->modelKeys())
            ->orderBy('id')
            ->get(['user_id', 'report_date'])
            ->each(function (object $report) use (&$reported) {
                $reported[$report->user_id][substr((string) $report->report_date, 0, 10)] = true;
            });

        // A user is present on a day they reported or checked in on, so either one is enough. A check-in without
        // a report is made up for by the daily report the user is made to file at their next login.
        $checkedIn = $incomplete = [];
        AttendanceSession::query()
            ->whereBetween('work_date', [$from, $to.' 23:59:59'])
            ->whereIn('user_id', $users->modelKeys())
            ->get(['user_id', 'work_date', 'close_reason'])
            ->each(function (AttendanceSession $session) use (&$reported, &$checkedIn, &$incomplete) {
                $date = $session->work_date->toDateString();
                $reported[$session->user_id][$date] = true;
                $checkedIn[$session->user_id][$date] = true;

                if ($session->close_reason === AttendanceSession::REASON_AUTO) {
                    $incomplete[$session->user_id][$date] = true;
                }
            });

        $leaves = UserLeave::portionsByUser($from, $to, $users->modelKeys());

        $rows = $users->map(function (User $user) use ($days, $reported, $leaves, $checkedIn, $incomplete) {
            $joined = $user->created_at?->toDateString();
            $marks = '';
            $present = $absent = $leave = 0;

            foreach ($days as $day) {
                $mark = $day['mark'];

                if (isset($reported[$user->id][$day['date']])) {
                    $mark = 'P';
                } elseif ($mark === 'A' && $joined && $day['date'] < $joined) {
                    $mark = 'N';
                } elseif (in_array($mark, ['A', 'F'], true) && ($leaves[$user->id][$day['date']] ?? null) === UserLeave::PORTION_FULL) {
                    // a half day of leave is still worked, so only a full day takes the place of a report
                    $mark = 'L';
                }

                $present += $mark === 'P' ? 1 : 0;
                $absent += $mark === 'A' ? 1 : 0;
                $leave += $mark === 'L' ? 1 : 0;
                $marks .= $mark;
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'marks' => $marks,
                'present' => $present,
                'absent' => $absent,
                'leave' => $leave,
                'checked_in' => count($checkedIn[$user->id] ?? []),
                'incomplete' => count($incomplete[$user->id] ?? []),
                'pct' => $present + $absent > 0 ? round($present / ($present + $absent) * 100, 1) : null,
            ];
        });

        return [
            'days' => array_map(fn (array $day) => ['date' => $day['date'], 'holiday' => $day['holiday']], $days),
            'rows' => $rows->values()->all(),
        ];
    }

    /**
     * The parent rows of a source within the range, for one user or for everyone.
     */
    private function scoped(string $source, string $from, string $to, ?int $userId): Builder
    {
        $config = self::SOURCES[$source];

        // a plain range on the date column keeps its index usable, the time part covers dates stored with one
        return DB::table($config['table'])
            ->whereBetween($config['table'].'.'.$config['date'], [$from, $to.' 23:59:59'])
            ->when($userId, fn (Builder $query) => $query->where($config['table'].'.user_id', $userId));
    }

    /**
     * The summed figures of a source, as one "all" row or one row per value of the grouping column.
     *
     * A figure stays null when nothing was entered for it, which for a target means none was set.
     *
     * @return Collection<string, array<string, int|null>>
     */
    private function totals(string $source, string $from, string $to, ?int $userId, ?string $groupBy = null): Collection
    {
        $config = self::SOURCES[$source];
        $group = $groupBy ? $config['table'].'.'.$groupBy : null;
        $groupKey = fn (object $row) => $groupBy ? substr((string) $row->group_key, 0, $groupBy === 'user_id' ? null : 10) : 'all';

        $rows = collect();

        $main = $this->scoped($source, $from, $to, $userId)
            ->selectRaw('SUM(outbound_calls) as outbound_calls, SUM(inbound_calls) as inbound_calls, SUM(message_replies) as message_replies, COUNT(*) as entries')
            ->when($group, fn (Builder $query) => $query->addSelect($group.' as group_key')->groupBy($group))
            ->get();

        foreach ($main as $row) {
            $rows->put($groupKey($row), [
                'outbound_calls' => $row->outbound_calls,
                'inbound_calls' => $row->inbound_calls,
                'message_replies' => $row->message_replies,
                'comment_replies' => null,
                'project_calls' => null,
                'entries' => (int) $row->entries,
            ]);
        }

        $children = [
            'comment_replies' => [$config['platforms'], 'total_replies'],
            'project_calls' => [$config['projects'], 'total_calls'],
        ];

        foreach ($children as $metric => [$table, $column]) {
            $sums = $this->scoped($source, $from, $to, $userId)
                ->join($table, $table.'.'.$config['key'], '=', $config['table'].'.id')
                ->selectRaw('SUM('.$table.'.'.$column.') as total')
                ->when($group, fn (Builder $query) => $query->addSelect($group.' as group_key')->groupBy($group))
                ->get();

            foreach ($sums as $row) {
                $key = $groupKey($row);

                if ($rows->has($key)) {
                    $rows->put($key, array_merge($rows->get($key), [$metric => $row->total]));
                }
            }
        }

        return $rows->map(fn (array $row) => array_map(fn ($value) => $value === null ? null : (int) $value, $row));
    }

    /**
     * How many reports came in against how many were due: one per user per working day up to today,
     * less the days a user was on a full day of official leave.
     */
    private function submissions(string $from, string $to, ?int $userId, int $submitted): array
    {
        $until = min($to, today()->toDateString());
        $workingDays = $from <= $until ? Holiday::workingDays($from, $until) : [];
        $userIds = $userId ? [$userId] : User::query()->where('usages_sector', 'field')->pluck('id')->all();

        $onLeave = 0;
        if (!empty($workingDays)) {
            $working = array_flip($workingDays);

            foreach (UserLeave::portionsByUser($from, $until, $userIds) as $days) {
                foreach ($days as $date => $portion) {
                    $onLeave += $portion === UserLeave::PORTION_FULL && isset($working[$date]) ? 1 : 0;
                }
            }
        }

        $expected = count($workingDays) * count($userIds) - $onLeave;

        return [
            'submitted' => $submitted,
            'expected' => $expected,
            'pct' => $expected > 0 ? round($submitted / $expected * 100, 1) : null,
        ];
    }

    /**
     * The figures of every day in the range, with the days nobody reported on as zeros.
     */
    private function trend(string $from, string $to, ?int $userId): array
    {
        $byDate = $this->totals('report', $from, $to, $userId, self::SOURCES['report']['date']);
        $dates = [];
        $series = array_fill_keys(array_keys(self::METRICS), []);

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $dates[] = $date;

            foreach (array_keys(self::METRICS) as $metric) {
                $series[$metric][] = (int) ($byDate->get($date)[$metric] ?? 0);
            }
        }

        return ['dates' => $dates, 'series' => $series];
    }

    /**
     * The reported figure per platform or per project, next to its target when targets are shown.
     */
    private function breakdown(string $child, string $nameTable, string $foreignKey, string $column, string $from, string $to, ?int $userId, bool $withTargets): array
    {
        $sum = function (string $source) use ($child, $foreignKey, $column, $from, $to, $userId) {
            $config = self::SOURCES[$source];
            $table = $config[$child];

            return $this->scoped($source, $from, $to, $userId)
                ->join($table, $table.'.'.$config['key'], '=', $config['table'].'.id')
                ->groupBy($table.'.'.$foreignKey)
                ->selectRaw($table.'.'.$foreignKey.' as id, SUM('.$table.'.'.$column.') as total')
                ->pluck('total', 'id');
        };

        $actual = $sum('report');
        $target = $withTargets ? $sum('target') : collect();
        $ids = $actual->keys()->merge($target->keys())->unique();

        return DB::table($nameTable)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $row) => [
                'name' => $row->name,
                'actual' => (int) ($actual[$row->id] ?? 0),
                'target' => $target->has($row->id) ? (int) $target[$row->id] : null,
            ])
            ->sortByDesc('actual')
            ->values()
            ->all();
    }

    /**
     * One row per field user, and per anyone else who reported, with the best achievement first.
     *
     * The achievement is the user's KPI: reports matched to targets day by day, with official leave left out.
     */
    private function users(string $from, string $to, ?int $userId, bool $withTargets): array
    {
        $actual = $this->totals('report', $from, $to, $userId, 'user_id');

        $users = User::query()
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->when(!$userId, fn ($query) => $query->where('usages_sector', 'field')->orWhereIn('id', $actual->keys()))
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        $kpis = $withTargets ? $this->kpi->forUsers($users, $from, $to) : collect();

        return $users
            ->map(function (User $user) use ($actual, $kpis) {
                $done = $actual->get((string) $user->id, []);
                $kpi = $kpis->get($user->id);
                $row = ['id' => $user->id, 'name' => $user->name, 'reports' => (int) ($done['entries'] ?? 0)];

                foreach (array_keys(self::METRICS) as $metric) {
                    $row[$metric] = (int) ($done[$metric] ?? 0);
                }

                $row['total'] = array_sum(array_intersect_key($row, self::METRICS));
                $row['target_total'] = ($kpi['target_total'] ?? 0) > 0 ? $kpi['target_total'] : null;
                $row['achievement_pct'] = $kpi['pct'] ?? null;

                return $row;
            })
            ->sortBy([['achievement_pct', 'desc'], ['total', 'desc']])
            ->values()
            ->all();
    }
}
