<?php

namespace App\Http\Controllers\Backend\CommonPages;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssignAssetToBrand;
use App\Models\AssignKvToAsset;
use App\Models\Brand;
use App\Models\PlanogramHistory;
use App\Models\Store;
use App\Models\User;
use App\Models\UserStoreAssignment;
use App\Models\VisualMerchandising;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Services\Dashboard\ReportDashboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Mainul\CustomHelperFunctions\Helpers\CustomHelper;
use Spatie\Activitylog\Models\Activity;
use Uzzal\Acl\Models\Resource;

class AdminViewController extends Controller
{
    /**
     * The report dashboard: the page itself, and its figures as JSON when asked for over ajax.
     *
     * A corporate user sees everyone's data and the targets, a field user only their own reports.
     */
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
            'preset' => ['nullable', Rule::in(ReportDashboardService::PRESETS)],
            'from' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer'],
        ]);

        [$from, $to] = $dashboard->range($filters['preset'] ?? 'month', $filters['from'] ?? null, $filters['to'] ?? null);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= ReportDashboardService::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'The date range cannot be longer than '.ReportDashboardService::MAX_RANGE_DAYS.' days.',
            ]);
        }

        // a field user is always held to their own data, whatever user the request names
        $userId = $canViewAll ? (isset($filters['user_id']) ? (int) $filters['user_id'] : null) : (int) $user->id;

        return response()->json($dashboard->build($from, $to, $userId, $canViewAll, $canViewAll));
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
