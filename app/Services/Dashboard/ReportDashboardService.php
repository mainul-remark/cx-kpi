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
use Illuminate\Support\Facades\Cache;
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
        'comments'        => 'Comments',
        'message_replies' => 'Message Replies',
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
     * The figures of the report dashboard for a range, for one user or for everyone.
     *
     * Only what the database adds up in a few queries is here, the figures of every single user are
     * the sections of their own below, so the page can draw this first and page through the rest.
     * Targets are left out entirely unless asked for, so they never reach a viewer who should not see them.
     */
    public function build(string $from, string $to, ?int $userId, bool $withTargets): array
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
            'platforms' => $this->breakdown('platforms', 'social_platforms', 'social_platform_id', $from, $to, $userId, $withTargets),
            'projects' => $this->breakdown('projects', 'projects', 'project_id', $from, $to, $userId, $withTargets),
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
        $days = $this->attendanceDays($from, $to);
        $users = $this->attendanceUsers($userId)->get(['id', 'name', 'created_at']);

        return [
            'days' => $this->publicDays($days),
            'rows' => $this->attendanceRows($users, $days, $from, $to, $userId),
        ];
    }

    /**
     * One page of the attendance sheet, with the figures of the whole sheet for the tiles.
     *
     * Only the users of the page are worked out in full; the totals are added up in batches that are not kept.
     *
     * @return array{days: array, rows: array, meta: array<string, int>, summary: array<string, int>|null}
     */
    public function attendancePage(string $from, string $to, ?int $userId, int $page, int $perPage, bool $withSummary = true): array
    {
        $days = $this->attendanceDays($from, $to);

        $paginator = $this->attendanceUsers($userId)->paginate($perPage, ['id', 'name', 'created_at'], 'page', $page);

        $summary = null;
        if ($withSummary) {
            $summary = ['users' => $paginator->total(), 'present' => 0, 'absent' => 0, 'leave' => 0];

            $this->attendanceUsers($userId)->reorder()->chunkById(200, function (Collection $users) use ($days, $from, $to, $userId, &$summary) {
                foreach ($this->attendanceRows($users, $days, $from, $to, $userId) as $row) {
                    $summary['present'] += $row['present'];
                    $summary['absent'] += $row['absent'];
                    $summary['leave'] += $row['leave'];
                }
            }, 'id');
        }

        return [
            'days' => $this->publicDays($days),
            'rows' => $this->attendanceRows(collect($paginator->items()), $days, $from, $to, $userId),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'summary' => $summary,
        ];
    }

    /**
     * The users on the attendance sheet: the field users, or the one asked for.
     */
    private function attendanceUsers(?int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->when($userId, fn ($query) => $query->whereKey($userId), fn ($query) => $query->where('usages_sector', 'field'))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * Every day of the range with the mark it has before any user is looked at.
     *
     * @return list<array{date: string, mark: string, holiday: string|null}>
     */
    private function attendanceDays(string $from, string $to): array
    {
        $today = today()->toDateString();

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$from, $to])
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

        return $days;
    }

    /**
     * @param  list<array{date: string, mark: string, holiday: string|null}>  $days
     */
    private function publicDays(array $days): array
    {
        return array_map(fn (array $day) => ['date' => $day['date'], 'holiday' => $day['holiday']], $days);
    }

    /**
     * The marks and counts of the given users.
     *
     * @param  Collection<int, User>  $users
     * @param  list<array{date: string, mark: string, holiday: string|null}>  $days
     * @return list<array<string, mixed>>
     */
    private function attendanceRows(Collection $users, array $days, string $from, string $to, ?int $userId): array
    {
        $reported = [];
        $this->scoped('report', $from, $to, $userId)
            ->whereIn('user_id', $users->pluck('id')->all())
            ->orderBy('id')
            ->get(['user_id', 'report_date'])
            ->each(function (object $report) use (&$reported) {
                $reported[$report->user_id][substr((string) $report->report_date, 0, 10)] = true;
            });

        // A user is present on a day they reported or checked in on, so either one is enough.
        $checkedIn = $incomplete = [];
        AttendanceSession::query()
            ->whereBetween('work_date', [$from, $to])
            ->whereIn('user_id', $users->pluck('id')->all())
            ->get(['user_id', 'work_date', 'close_reason'])
            ->each(function (AttendanceSession $session) use (&$reported, &$checkedIn, &$incomplete) {
                $date = $session->work_date->toDateString();
                $reported[$session->user_id][$date] = true;
                $checkedIn[$session->user_id][$date] = true;

                if ($session->close_reason === AttendanceSession::REASON_AUTO) {
                    $incomplete[$session->user_id][$date] = true;
                }
            });

        $leaves = UserLeave::portionsByUser($from, $to, $users->pluck('id')->all());

        return $users->map(function (User $user) use ($days, $reported, $leaves, $checkedIn, $incomplete) {
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
        })->values()->all();
    }

    /**
     * The parent rows of a source within the range, for one user or for everyone.
     */
    private function scoped(string $source, string $from, string $to, ?int $userId): Builder
    {
        $config = self::SOURCES[$source];

        // a plain range on the date column keeps its index usable, the time part covers dates stored with one
        return DB::table($config['table'])
            ->whereBetween($config['table'].'.'.$config['date'], [$from, $to])
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

        // order processing is reported next to the outbound calls and counts with them, a target has none
        $outbound = $source === 'report' ? 'SUM(outbound_calls + order_processing)' : 'SUM(outbound_calls)';

        $main = $this->scoped($source, $from, $to, $userId)
            ->selectRaw($outbound.' as outbound_calls, SUM(inbound_calls) as inbound_calls, COUNT(*) as entries')
            ->when($group, fn (Builder $query) => $query->addSelect($group.' as group_key')->groupBy($group))
            ->get();

        foreach ($main as $row) {
            $rows->put($groupKey($row), [
                'outbound_calls' => $row->outbound_calls,
                'inbound_calls' => $row->inbound_calls,
                'comments' => null,
                'message_replies' => null,
                'entries' => (int) $row->entries,
            ]);
        }

        // comments and message replies are only entered per project and per platform, so each is both tables added
        foreach (['comments', 'message_replies'] as $metric) {
            foreach ([$config['platforms'], $config['projects']] as $table) {
                $sums = $this->scoped($source, $from, $to, $userId)
                    ->join($table, $table.'.'.$config['key'], '=', $config['table'].'.id')
                    ->selectRaw('SUM('.$table.'.'.$metric.') as total')
                    ->when($group, fn (Builder $query) => $query->addSelect($group.' as group_key')->groupBy($group))
                    ->get();

                foreach ($sums as $row) {
                    $key = $groupKey($row);

                    if ($rows->has($key) && $row->total !== null) {
                        $current = $rows->get($key);
                        $current[$metric] = (int) $current[$metric] + (int) $row->total;
                        $rows->put($key, $current);
                    }
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
     * The reported figure per platform or per project, next to its approximate target when targets are shown.
     *
     * A figure is the inbound calls, comments and message replies of the row added up.
     */
    private function breakdown(string $child, string $nameTable, string $foreignKey, string $from, string $to, ?int $userId, bool $withTargets): array
    {
        $sum = function (string $source) use ($child, $foreignKey, $from, $to, $userId) {
            $config = self::SOURCES[$source];
            $table = $config[$child];

            return $this->scoped($source, $from, $to, $userId)
                ->join($table, $table.'.'.$config['key'], '=', $config['table'].'.id')
                ->groupBy($table.'.'.$foreignKey)
                ->selectRaw($table.'.'.$foreignKey.' as id, SUM(COALESCE('.$table.'.inbound_calls, 0) + COALESCE('.$table.'.comments, 0) + COALESCE('.$table.'.message_replies, 0)) as total')
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
     * What the people section sorts by, as the key of a row.
     */
    public const PEOPLE_SORTS = ['name', 'reports', 'outbound_calls', 'inbound_calls', 'comments', 'message_replies', 'total', 'achievement_pct', 'score'];

    /**
     * One page of the field users with their figures and KPI score, and what stands out in the whole team.
     *
     * The figures of every user are needed to sort by the score, so they are worked out in batches of
     * users that are not kept, and only the slim result is held (and cached for a short while).
     * Paging, searching and sorting then only touch that list.
     *
     * @param  array{page?: int, per_page?: int, sort?: string, dir?: string, search?: string, fresh?: bool, all?: bool}  $options
     * @return array{rows: list<array<string, mixed>>, meta: array<string, int>, insights: array<string, mixed>}
     */
    public function people(string $from, string $to, ?int $userId, bool $withTargets, array $options = []): array
    {
        $all = collect($this->remember('people', [$from, $to, $userId, $withTargets], (bool) ($options['fresh'] ?? false),
            fn () => $this->teamRows($from, $to, $userId, $withTargets)));

        $insights = $this->insights($all);

        $search = mb_strtolower(trim((string) ($options['search'] ?? '')));
        if ($search !== '') {
            $all = $all->filter(fn (array $row) => str_contains(mb_strtolower($row['name'].' '.($row['employee_id'] ?? '')), $search));
        }

        $sort = in_array($options['sort'] ?? null, self::PEOPLE_SORTS, true) ? $options['sort'] : 'score';
        $desc = ($options['dir'] ?? ($sort === 'name' ? 'asc' : 'desc')) !== 'asc';

        $sorted = $all->all();
        usort($sorted, function (array $a, array $b) use ($sort, $desc) {
            // a user without a figure always goes last, whichever way it is sorted
            if (($a[$sort] === null) !== ($b[$sort] === null)) {
                return $a[$sort] === null ? 1 : -1;
            }

            $order = $sort === 'name' ? strcasecmp($a['name'], $b['name']) : ($a[$sort] <=> $b[$sort]);

            return ($desc ? -$order : $order) ?: strcasecmp($a['name'], $b['name']);
        });

        $total = count($sorted);

        // every row in one go, which is what a file of the table needs
        if (!empty($options['all'])) {
            return [
                'rows' => $sorted,
                'meta' => ['page' => 1, 'per_page' => $total, 'total' => $total, 'last_page' => 1, 'from' => $total ? 1 : 0, 'to' => $total],
                'insights' => $insights,
            ];
        }

        $perPage = max(1, (int) ($options['per_page'] ?? config('dashboard.per_page')));
        $last = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($options['page'] ?? 1)), $last);

        return [
            'rows' => array_slice($sorted, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $last,
                'from' => $total ? ($page - 1) * $perPage + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
            'insights' => $insights,
        ];
    }

    /**
     * One slim row per field user, and per anyone else who reported: figures, target and KPI score.
     *
     * The KPI is the one of the KPI page: reports matched to targets day by day, official leave left out.
     *
     * @return list<array<string, mixed>>
     */
    private function teamRows(string $from, string $to, ?int $userId, bool $withTargets): array
    {
        $actual = $this->totals('report', $from, $to, $userId, 'user_id');
        $rows = [];

        User::query()
            ->when($userId, fn ($query) => $query->whereKey($userId), fn ($query) => $query->where(
                fn ($inner) => $inner->where('usages_sector', 'field')->orWhereIn('id', $actual->keys()->all())
            ))
            ->orderBy('id')
            ->chunkById((int) config('dashboard.chunk'), function (Collection $users) use ($from, $to, $actual, $withTargets, &$rows) {
                $kpis = $this->kpi->forUsers($users, $from, $to);

                foreach ($users as $user) {
                    $done = $actual->get((string) $user->id, []);
                    $kpi = $kpis->get($user->id);
                    $row = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'employee_id' => $user->employee_id,
                        'reports' => (int) ($done['entries'] ?? 0),
                    ];

                    foreach (array_keys(self::METRICS) as $metric) {
                        $row[$metric] = (int) ($done[$metric] ?? 0);
                    }

                    $row['total'] = array_sum(array_intersect_key($row, self::METRICS));
                    $row['target_total'] = $withTargets && ($kpi['target_total'] ?? 0) > 0 ? $kpi['target_total'] : null;
                    $row['achievement_pct'] = $withTargets ? ($kpi['pct'] ?? null) : null;
                    $row['score'] = $kpi['score'] ?? null;
                    $row['absent'] = (int) ($kpi['absent'] ?? 0);

                    $rows[] = $row;
                }
            }, 'id');

        return $rows;
    }

    /**
     * What stands out in the team: the average score, how the scores spread over the bands,
     * who is ahead and who is behind.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function insights(Collection $rows): array
    {
        $scored = $rows->filter(fn (array $row) => $row['score'] !== null)->values();
        $bands = array_fill_keys(array_keys((array) config('dashboard.score_bands')), 0) + ['low' => 0, 'unscored' => $rows->count() - $scored->count()];

        foreach ($scored as $row) {
            $band = 'low';
            foreach ((array) config('dashboard.score_bands') as $name => $minimum) {
                if ($row['score'] >= $minimum) {
                    $band = $name;
                    break;
                }
            }
            $bands[$band]++;
        }

        $slim = fn (array $row) => ['id' => $row['id'], 'name' => $row['name'], 'score' => $row['score'], 'total' => $row['total']];
        $byScore = $scored->sortBy([['score', 'desc'], ['total', 'desc'], ['name', 'asc']])->values();
        $top = (int) config('dashboard.top');

        return [
            'users' => $rows->count(),
            'scored' => $scored->count(),
            'average_score' => $scored->isEmpty() ? null : round($scored->avg('score'), 1),
            'bands' => $bands,
            'top' => $byScore->take($top)->map($slim)->all(),
            // the lowest scores, lowest first, never the same user as in the list of the best
            'attention' => $byScore->slice($top)->reverse()->take($top)->map($slim)->values()->all(),
        ];
    }

    /**
     * The attendance of the team added up for a range: the totals, the presence of every day, and the
     * users who missed the most reports. The sheet itself, row by row, is the attendance page.
     *
     * @return array{summary: array<string, mixed>, daily: list<array<string, mixed>>, missing: array<string, mixed>}
     */
    public function attendanceOverview(string $from, string $to, ?int $userId, bool $fresh = false): array
    {
        return $this->remember('attendance', [$from, $to, $userId], $fresh, function () use ($from, $to, $userId) {
            $days = $this->attendanceDays($from, $to);
            $daily = array_map(fn (array $day) => ['date' => $day['date'], 'holiday' => $day['holiday'], 'present' => 0, 'absent' => 0, 'leave' => 0], $days);
            $summary = ['users' => 0, 'present' => 0, 'absent' => 0, 'leave' => 0, 'checked_in_days' => 0, 'incomplete_days' => 0];
            $missing = [];

            $this->attendanceUsers($userId)->reorder()->chunkById((int) config('dashboard.chunk'), function (Collection $users) use ($days, $from, $to, $userId, &$daily, &$summary, &$missing) {
                foreach ($this->attendanceRows($users, $days, $from, $to, $userId) as $row) {
                    $summary['users']++;
                    $summary['present'] += $row['present'];
                    $summary['absent'] += $row['absent'];
                    $summary['leave'] += $row['leave'];
                    $summary['checked_in_days'] += $row['checked_in'];
                    $summary['incomplete_days'] += $row['incomplete'];

                    $dates = [];
                    foreach (str_split($row['marks']) as $index => $mark) {
                        if ($mark === 'P') {
                            $daily[$index]['present']++;
                        } elseif ($mark === 'A') {
                            $daily[$index]['absent']++;
                            $dates[] = $days[$index]['date'];
                        } elseif ($mark === 'L') {
                            $daily[$index]['leave']++;
                        }
                    }

                    if ($row['absent'] > 0) {
                        $missing[] = ['id' => $row['id'], 'name' => $row['name'], 'absent' => $row['absent'], 'dates' => array_slice($dates, -6)];
                    }
                }
            }, 'id');

            usort($missing, fn (array $a, array $b) => $b['absent'] <=> $a['absent'] ?: strcasecmp($a['name'], $b['name']));

            $due = $summary['present'] + $summary['absent'];
            $summary['pct'] = $due > 0 ? round($summary['present'] / $due * 100, 1) : null;

            return [
                'summary' => $summary,
                'daily' => $daily,
                'missing' => ['total' => count($missing), 'rows' => array_slice($missing, 0, (int) config('dashboard.top'))],
            ];
        });
    }

    /**
     * Where the team stands today, for a manager: who is in, who has reported, who is off.
     *
     * Not cached, as it is a handful of counts and is only worth anything when it is current.
     */
    public function live(): array
    {
        $today = today()->toDateString();
        $fieldIds = User::query()->where('usages_sector', 'field')->pluck('id')->all();
        $isWorkingDay = !empty(Holiday::workingDays($today, $today));

        $onLeave = $isWorkingDay
            ? DB::table('user_leaves')->whereBetween('leave_date', [$today, $today.' 23:59:59'])->where('status', UserLeave::STATUS_APPROVED)
                ->where('portion', UserLeave::PORTION_FULL)->whereIn('user_id', $fieldIds)->distinct()->count('user_id')
            : 0;

        $reported = DB::table('daily_reports')->whereBetween('report_date', [$today, $today.' 23:59:59'])
            ->whereIn('user_id', $fieldIds)->distinct()->count('user_id');

        $inside = AttendanceSession::query()->open()->with('user:id,name')->orderBy('checked_in_at')->get();
        $timezone = config('attendance.timezone');

        return [
            'date' => $today,
            'working_day' => $isWorkingDay,
            'field_users' => count($fieldIds),
            'checked_in' => $inside->count(),
            'checked_in_users' => $inside->take(6)->map(fn (AttendanceSession $session) => [
                'name' => $session->user?->name,
                'since' => $session->checked_in_at->copy()->timezone($timezone)->format('h:i A'),
            ])->values()->all(),
            'reported' => $reported,
            'due' => $isWorkingDay ? max(0, count($fieldIds) - $onLeave) : 0,
            'on_leave' => $onLeave,
            'pending_leave_days' => UserLeave::query()->where('status', UserLeave::STATUS_PENDING)->where('leave_date', '>=', $today)->count(),
            // a forgotten check out the user has not been told about yet is one a manager may want to fix
            'forgotten_checkouts' => AttendanceSession::query()->forgotten()->whereNull('acknowledged_at')
                ->where('work_date', '>=', today()->subDays(7)->toDateString())->count(),
        ];
    }

    /**
     * A figure kept for a short while, as working out the whole team is the expensive part of the dashboard.
     */
    private function remember(string $name, array $parts, bool $fresh, \Closure $callback): mixed
    {
        $ttl = (int) config('dashboard.cache_ttl');

        if ($ttl <= 0) {
            return $callback();
        }

        $key = 'dashboard:'.$name.':'.md5(json_encode($parts));

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, $ttl, $callback);
    }
}
