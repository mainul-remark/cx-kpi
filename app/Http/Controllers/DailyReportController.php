<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyReportRequest;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;

class DailyReportController extends Controller
{
    /**
     * Display the signed-in user's own reports.
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.daily-reports.index', ['scope' => 'own', 'users' => collect()]);
        }

        return $this->reportTable($request, DailyReport::query()->where('user_id', $request->user()->id));
    }

    /**
     * Display every user's reports. Access to this action is what lets a user see reports of others.
     */
    public function team(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.daily-reports.index', [
                'scope' => 'team',
                'users' => User::query()->orderBy('name')->get(['id', 'name']),
            ]);
        }

        $reports = DailyReport::query()
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->input('user_id')));

        return $this->reportTable($request, $reports);
    }

    /**
     * Show the day-end form for a date, filled in when the user already reported that day.
     */
    public function create(Request $request)
    {
        $date = $this->resolveDate($request->query('date'));

        $report = DailyReport::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('report_date', $date)
            ->first();

        return $this->form($report, $date);
    }

    /**
     * Store the user's report for a date, saving over the one already there.
     */
    public function store(DailyReportRequest $request)
    {
        try {
            $report = DailyReport::saveForUser($request->user(), $request->validated());
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save daily report. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => $report->wasRecentlyCreated ? 'Daily report saved successfully' : 'Daily report updated successfully',
            'data' => $report,
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, DailyReport $dailyReport)
    {
        abort_unless($this->isOwner($request, $dailyReport) || allowed('daily-reports.team'), 403);

        $dailyReport->load(['user:id,name', 'platformReplies.socialPlatform', 'projectCalls.project']);

        if ($request->expectsJson()) {
            return response()->json($dailyReport);
        }

        return view('backend.daily-reports.show', [
            'report' => $dailyReport,
            'isOwner' => $this->isOwner($request, $dailyReport),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, DailyReport $dailyReport)
    {
        abort_unless($this->isOwner($request, $dailyReport), 403);

        return $this->form($dailyReport, $dailyReport->report_date->toDateString());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DailyReportRequest $request, DailyReport $dailyReport)
    {
        try {
            $report = DailyReport::saveForUser($request->user(), $request->validated(), $dailyReport);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update daily report. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Daily report updated successfully',
            'data' => $report,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, DailyReport $dailyReport)
    {
        abort_unless($this->isOwner($request, $dailyReport), 403);

        try {
            $dailyReport->delete();
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete daily report. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Daily report deleted successfully',
        ]);
    }

    private function isOwner(Request $request, DailyReport $report): bool
    {
        return (int) $report->user_id === (int) $request->user()->id;
    }

    /**
     * A usable report date from the query string: today unless it is a valid day that is not in the future.
     */
    private function resolveDate(mixed $date): string
    {
        $today = today()->toDateString();

        if (!is_string($date) || !Carbon::hasFormat($date, 'Y-m-d')) {
            return $today;
        }

        return $date > $today ? $today : $date;
    }

    /**
     * The entry form with one row per active platform and project, plus any the report already holds.
     */
    private function form(?DailyReport $report, string $date)
    {
        $replies = $report?->platformReplies()->get()->keyBy('social_platform_id') ?? collect();
        $calls = $report?->projectCalls()->get()->keyBy('project_id') ?? collect();

        $platformRows = SocialPlatform::query()
            ->where('active', true)
            ->orWhereIn('id', $replies->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(fn (SocialPlatform $platform) => [
                'id' => $platform->id,
                'name' => $platform->name,
                'active' => $platform->active,
                'count' => $replies->get($platform->id)?->total_replies,
                'note' => $replies->get($platform->id)?->note,
            ]);

        $projectRows = Project::query()
            ->where('active', true)
            ->orWhereIn('id', $calls->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'active' => $project->active,
                'count' => $calls->get($project->id)?->total_calls,
                'note' => $calls->get($project->id)?->note,
            ]);

        return view('backend.daily-reports.form', [
            'report' => $report,
            'date' => $date,
            // the edit route keeps writing to its own report, the create route saves by date
            'isEdit' => request()->routeIs('daily-reports.edit'),
            'platformRows' => $platformRows,
            'projectRows' => $projectRows,
        ]);
    }

    /**
     * DataTables response for a report listing, with the per-report platform and project totals.
     */
    private function reportTable(Request $request, Builder $reports)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $userId = (int) $request->user()->id;

        $reports
            ->select('daily_reports.*')
            ->with('user:id,name')
            ->withSum('platformReplies as platform_replies_total', 'total_replies')
            ->withSum('projectCalls as project_calls_total', 'total_calls')
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('report_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('report_date', '<=', $to))
            // latest day first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->orderByDesc('report_date')->orderByDesc('id'));

        return DataTables::of($reports)
            ->addIndexColumn()
            ->addColumn('is_own', fn (DailyReport $report) => (int) $report->user_id === $userId)
            ->toJson();
    }
}
