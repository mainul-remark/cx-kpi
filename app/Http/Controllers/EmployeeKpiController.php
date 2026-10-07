<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeKpiExport;
use App\Models\KpiSnapshot;
use App\Models\User;
use App\Services\Dashboard\ReportDashboardService;
use App\Services\Kpi\EmployeeKpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeKpiController extends Controller
{
    /**
     * The KPI sheet of the field users: the page itself, and its figures as JSON when asked for over ajax.
     *
     * A corporate user sees every field user and the targets, a field user only their own score.
     */
    public function index(Request $request, ReportDashboardService $reports, EmployeeKpiService $kpi)
    {
        $canViewAll = $this->canViewAll($request);

        if (!$request->ajax()) {
            return view('backend.kpi.index', [
                'canViewAll' => $canViewAll,
                'users' => $canViewAll
                    ? User::query()->where('usages_sector', 'field')->orderBy('name')->get(['id', 'name'])
                    : collect(),
            ]);
        }

        [$from, $to, $userId] = $this->filters($request, $reports);

        $rows = $kpi->sheet($from, $to, $userId);
        $summary = $kpi->summary($rows);

        if (!$canViewAll) {
            $rows = array_map(fn (array $row) => $kpi->withoutTargets($row), $rows);
            $summary['target_total'] = null;
        }

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'show_targets' => $canViewAll,
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }

    /**
     * The figures of one user for a range, with every day of it.
     */
    public function show(Request $request, User $user, ReportDashboardService $reports, EmployeeKpiService $kpi)
    {
        $canViewAll = $this->canViewAll($request);

        // a field user is only ever shown their own days
        abort_unless($canViewAll || (int) $user->id === (int) $request->user()->id, 403);

        [$from, $to] = $this->filters($request, $reports);

        $detail = $kpi->detail($user, $from, $to);

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'show_targets' => $canViewAll,
            'user' => $canViewAll ? $detail : $kpi->withoutTargets($detail),
        ]);
    }

    /**
     * Download the KPI sheet of a range as an Excel workbook, with one tab per user.
     */
    public function export(Request $request, ReportDashboardService $reports, EmployeeKpiService $kpi)
    {
        // the workbook is built around the targets, which a field user is not shown
        abort_unless($this->canViewAll($request), 403);

        [$from, $to, $userId] = $this->filters($request, $reports);

        // without a period in the request the sheet is this month's, as on the page
        $preset = $request->input('preset', 'month');

        return Excel::download(new EmployeeKpiExport($kpi->sheet($from, $to, $userId), $from, $to, $preset), 'kpi-sheet_'.$from.'_to_'.$to.'.xlsx');
    }

    /**
     * The scores frozen for a month that is over, which later edits to reports and targets no longer change.
     */
    public function monthly(Request $request)
    {
        $canViewAll = $this->canViewAll($request);

        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $start = isset($filters['month'])
            ? Carbon::createFromFormat('!Y-m', $filters['month'])
            : today()->subMonthNoOverflow()->startOfMonth();

        $rows = KpiSnapshot::query()
            ->with('user:id,name')
            ->whereBetween('period_month', [$start->toDateString(), $start->toDateString().' 23:59:59'])
            // a field user is only ever shown their own score
            ->when(!$canViewAll, fn ($query) => $query->where('user_id', $request->user()->id))
            ->get()
            ->sortBy([
                fn (KpiSnapshot $a, KpiSnapshot $b) => ($b->score ?? -1) <=> ($a->score ?? -1),
                fn (KpiSnapshot $a, KpiSnapshot $b) => $b->actual_total <=> $a->actual_total,
            ])
            ->values()
            ->map(fn (KpiSnapshot $snapshot) => [
                'id' => $snapshot->user_id,
                'name' => $snapshot->user?->name,
                'target_total' => $canViewAll ? $snapshot->target_total : null,
                'actual_total' => $snapshot->actual_total,
                'pct' => $snapshot->pct,
                'score' => $snapshot->score,
                'worked' => $snapshot->worked,
                'absent' => $snapshot->absent,
                'leave' => $snapshot->leave,
                'generated_at' => $snapshot->generated_at->toDateString(),
            ]);

        return response()->json([
            'month' => $start->format('Y-m'),
            'show_targets' => $canViewAll,
            'rows' => $rows,
        ]);
    }

    private function canViewAll(Request $request): bool
    {
        return $request->user()->usages_sector === 'corporate';
    }

    /**
     * The range and the user asked for, with a field user always held to their own figures.
     *
     * @return array{0: string, 1: string, 2: int|null}
     */
    private function filters(Request $request, ReportDashboardService $reports): array
    {
        $filters = $request->validate([
            'preset' => ['nullable', Rule::in(ReportDashboardService::PRESETS)],
            'from' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer'],
        ]);

        [$from, $to] = $reports->range($filters['preset'] ?? 'month', $filters['from'] ?? null, $filters['to'] ?? null);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= ReportDashboardService::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'The date range cannot be longer than '.ReportDashboardService::MAX_RANGE_DAYS.' days.',
            ]);
        }

        $userId = $this->canViewAll($request)
            ? (isset($filters['user_id']) ? (int) $filters['user_id'] : null)
            : (int) $request->user()->id;

        return [$from, $to, $userId];
    }
}
