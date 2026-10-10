<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyReportRequest;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class DailyReportController extends Controller
{
    /**
     * Display the reports: a corporate user sees every user's, anyone else only their own.
     */
    public function index(Request $request)
    {
        if ($this->canViewAll($request)) {
            return $this->teamListing($request, 'daily-reports.index');
        }

        if (!$request->ajax()) {
            return view('backend.daily-reports.index', [
                'scope' => 'own',
                'users' => collect(),
                'listRoute' => 'daily-reports.index',
            ]);
        }

        return $this->reportTable($request, DailyReport::query()->where('user_id', $request->user()->id));
    }

    /**
     * Display every user's reports. Access to this action is what lets a user see reports of others.
     */
    public function team(Request $request)
    {
        return $this->teamListing($request, 'daily-reports.team');
    }

    /**
     * The listing of every user's reports, filterable by user.
     */
    private function teamListing(Request $request, string $listRoute)
    {
        if (!$request->ajax()) {
            return view('backend.daily-reports.index', [
                'scope' => 'team',
                'users' => User::query()->where('usages_sector', 'field')->orderBy('name')->get(['id', 'name']),
                'listRoute' => $listRoute,
            ]);
        }

        $reports = DailyReport::query()
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->input('user_id')));

        return $this->reportTable($request, $reports);
    }

    /**
     * A corporate user may see the reports of every user.
     */
    private function canViewAll(Request $request): bool
    {
        return $request->user()->usages_sector === 'corporate';
    }

    /**
     * Show the day-end form for today, filled in when the user already reported that day.
     */
    public function create(Request $request)
    {
        $date = today()->toDateString();

        $report = DailyReport::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('report_date', $date)
            ->first();

        return $this->form($report, $date);
    }

    /**
     * Store the user's report for today, saving over the one already there.
     */
    public function store(DailyReportRequest $request)
    {
        try {
            $report = DailyReport::saveForUser($request->user(), $request->validated(), null, $request->reportDate());
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
        abort_unless($this->isOwner($request, $dailyReport) || $this->canViewAll($request) || allowed('daily-reports.team'), 403);

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
            $report = DailyReport::saveForUser($request->user(), $request->validated(), $dailyReport, $request->reportDate());
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
     * The entry form with one row per active platform and project, plus any the report already holds.
     *
     * Each row carries what the entity is switched on for, so the form shows only the inputs that apply.
     */
    private function form(?DailyReport $report, string $date)
    {
        $platforms = $report?->platformReplies()->get()->keyBy('social_platform_id') ?? collect();
        $projects = $report?->projectCalls()->get()->keyBy('project_id') ?? collect();

        $row = fn ($entity, $saved) => [
            'id' => $entity->id,
            'name' => $entity->name,
            'active' => $entity->active,
            'inbound_calls' => $entity->has_outbound_calls,
            'comments' => $entity->has_comments,
            'message_replies' => $entity->has_message_replies,
            'values' => [
                'inbound_calls' => $saved?->inbound_calls,
                'comments' => $saved?->comments,
                'message_replies' => $saved?->message_replies,
            ],
            'note' => $saved?->note,
        ];

        $columns = ['id', 'name', 'active', 'has_outbound_calls', 'has_comments', 'has_message_replies'];

        $platformRows = SocialPlatform::query()
            ->where('active', true)
            ->orWhereIn('id', $platforms->keys())
            ->orderBy('name')
            ->get($columns)
            ->map(fn (SocialPlatform $platform) => $row($platform, $platforms->get($platform->id)));

        $projectRows = Project::query()
            ->where('active', true)
            ->orWhereIn('id', $projects->keys())
            ->orderBy('name')
            ->get($columns)
            ->map(fn (Project $project) => $row($project, $projects->get($project->id)));

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
     * DataTables response for a report listing, with the per-report comment and message totals.
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
            ->withSum('platformReplies as platform_comments', 'comments')
            ->withSum('projectCalls as project_comments', 'comments')
            ->withSum('platformReplies as platform_messages', 'message_replies')
            ->withSum('projectCalls as project_messages', 'message_replies')
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
