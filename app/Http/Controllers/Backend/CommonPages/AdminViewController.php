<?php

namespace App\Http\Controllers\Backend\CommonPages;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\ReportDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Uzzal\Acl\Models\Resource;

class AdminViewController extends Controller
{
    /**
     * The sections the dashboard draws from, each loaded on its own so a slow or failing one
     * does not hold up the rest:
     *  - overview (the default): figures, trend and breakdowns, summed by the database,
     *  - people: one page of the users with their KPI score, sorted and searched on the server,
     *  - attendance: the totals, daily presence and most missed reports of the team,
     *  - live: where the team stands today, for corporate users.
     *
     * A corporate user sees everyone's data and the targets, a field user only their own reports.
     */
    public const SECTIONS = ['overview', 'people', 'attendance', 'live'];

    public function dashboard(Request $request, ReportDashboardService $dashboard)
    {
        $user = $request->user();
        $canViewAll = $user->usages_sector === 'corporate';

        if (!$request->ajax()) {
            return view('backend.common-pages.dashboard.dashboard', [
                'canViewAll' => $canViewAll,
                'users' => $canViewAll
                    ? User::query()->where('usages_sector', 'field')->orderBy('name')->get(['id', 'name'])
                    : collect(),
            ]);
        }

        $filters = $request->validate([
            'section' => ['nullable', Rule::in(self::SECTIONS)],
            'preset' => ['nullable', Rule::in(ReportDashboardService::PRESETS)],
            'from' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', Rule::in(ReportDashboardService::PEOPLE_SORTS)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'search' => ['nullable', 'string', 'max:100'],
            'export' => ['nullable', 'boolean'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        [$from, $to] = $dashboard->range($filters['preset'] ?? 'month', $filters['from'] ?? null, $filters['to'] ?? null);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= ReportDashboardService::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'The date range cannot be longer than '.ReportDashboardService::MAX_RANGE_DAYS.' days.',
            ]);
        }

        // a field user is always held to their own data, whatever user the request names
        $userId = $canViewAll ? (isset($filters['user_id']) ? (int) $filters['user_id'] : null) : (int) $user->id;
        $fresh = $request->boolean('refresh');
        $range = ['from' => $from, 'to' => $to];

        return response()->json(match ($filters['section'] ?? 'overview') {
            'people' => ['range' => $range] + $dashboard->people($from, $to, $userId, $canViewAll, [
                'page' => $filters['page'] ?? 1,
                'per_page' => $filters['per_page'] ?? null,
                'sort' => $filters['sort'] ?? null,
                'dir' => $filters['dir'] ?? null,
                'search' => $filters['search'] ?? '',
                'fresh' => $fresh,
                'all' => $request->boolean('export'),
            ]),
            'attendance' => ['range' => $range] + $dashboard->attendanceOverview($from, $to, $userId, $fresh),
            'live' => ['live' => $canViewAll ? $dashboard->live() : null],
            default => $dashboard->build($from, $to, $userId, $canViewAll),
        });
    }
    public function viewPermissionList()
    {
        return view('backend.common-pages.permission-list', ['resources' => Resource::orderBy('controller') ->orderByRaw('ISNULL(label) DESC')->orderBy('label')->get()]);
    }

    public function updateResourceLabel(Request $request, $resourceId)
    {
        try {
            $resource = Resource::where('resource_id', $resourceId)->first();
            $resource->label = $request->label;
            $resource->save();
            return response()->json([
                'status'  => true,
                'message' => 'Resource label updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ]);
        }
    }


    public function activityLog(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:50'],
            'log_name' => ['nullable', 'string', 'max:255'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'causer_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $activityLogsQuery = $this->applyActivityLogFilters(
            Activity::query()->with(['causer', 'subject'])->latest(),
            $filters
        );

        $activityLogs = $activityLogsQuery
            ->paginate(15)
            ->withQueryString();

        $summaryQuery = $this->applyActivityLogFilters(Activity::query(), $filters);

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'today' => (clone $summaryQuery)->whereDate('created_at', today())->count(),
            'auth' => (clone $summaryQuery)->where('log_name', 'auth')->count(),
            'data' => (clone $summaryQuery)->where('log_name', 'data')->count(),
            'workflow' => (clone $summaryQuery)->where('log_name', 'workflow')->count(),
        ];

        $eventOptions = Activity::query()
            ->whereNotNull('event')
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $logNameOptions = Activity::query()
            ->whereNotNull('log_name')
            ->select('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name');

        $subjectTypeOptions = Activity::query()
            ->whereNotNull('subject_type')
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn (string $subjectType) => [
                'value' => $subjectType,
                'label' => class_basename($subjectType),
            ]);

        $causerOptions = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('backend.common-pages.activity-error-log', [
            'activityLogs' => $activityLogs,
            'summary' => $summary,
            'filters' => $filters,
            'eventOptions' => $eventOptions,
            'logNameOptions' => $logNameOptions,
            'subjectTypeOptions' => $subjectTypeOptions,
            'causerOptions' => $causerOptions,
        ]);
    }

    private function applyActivityLogFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhere('causer_type', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (! empty($filters['causer_id'])) {
            $query->where('causer_type', User::class)
                ->where('causer_id', (int) $filters['causer_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
