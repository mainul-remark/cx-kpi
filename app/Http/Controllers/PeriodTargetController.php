<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeriodTargetRequest;
use App\Models\PeriodTarget;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Monthly targets of the field users, the ones the KPI uses when kpi.target_mode is "period".
 */
class PeriodTargetController extends Controller
{
    /**
     * The targets set for a month and the form to set them, filled from one user's when asked for.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'user' => ['nullable', 'integer'],
        ]);

        $month = isset($filters['month'])
            ? Carbon::createFromFormat('!Y-m', $filters['month'])
            : today()->startOfMonth();
        $start = $month->toDateString();

        $users = User::query()->where('usages_sector', 'field')->orderBy('name')->get(['id', 'name']);

        $targets = PeriodTarget::query()
            ->whereBetween('period_month', [$start, $start.' 23:59:59'])
            ->orderBy('user_id')
            ->get(['user_id', 'activity', 'target'])
            ->groupBy('user_id');

        $editing = isset($filters['user']) ? $users->firstWhere('id', (int) $filters['user']) : null;
        $values = $editing ? $targets->get($editing->id, collect())->pluck('target', 'activity') : collect();

        $labels = ['inbound_calls' => 'Outbound Calls', 'comments' => 'Comments', 'message_replies' => 'Message Replies'];
        $names = [
            'platform' => SocialPlatform::query()->pluck('name', 'id'),
            'project' => Project::query()->pluck('name', 'id'),
        ];

        // "Facebook (Comments)" for an activity key like platform:3:comments
        $title = function (string $activity) use ($names, $labels): string {
            if ($activity === 'outbound_calls') {
                return 'Outbound Calls';
            }
            if ($activity === 'inbound_calls') {
                return 'Inbound Calls';
            }

            [$type, $id, $count] = explode(':', $activity);

            return ($names[$type][(int) $id] ?? $type.' '.$id).' ('.($labels[$count] ?? $count).')';
        };

        $rows = fn ($entities, string $prefix) => $entities->map(fn ($entity) => [
            'id' => $entity->id,
            'name' => $entity->name,
            'inbound_calls' => $entity->has_outbound_calls,
            'comments' => $entity->has_comments,
            'message_replies' => $entity->has_message_replies,
            'values' => collect(array_keys($labels))->mapWithKeys(fn (string $count) => [$count => $values->get($prefix.':'.$entity->id.':'.$count)])->all(),
        ]);

        $columns = ['id', 'name', 'has_outbound_calls', 'has_comments', 'has_message_replies'];

        return view('backend.period-targets.index', [
            'month' => $month,
            'users' => $users,
            'editing' => $editing,
            'values' => $values,
            'selectedUserIds' => $editing ? [$editing->id] : [],
            'platformRows' => $rows(SocialPlatform::query()->where('active', true)->orderBy('name')->get($columns), 'platform'),
            'projectRows' => $rows(Project::query()->where('active', true)->orderBy('name')->get($columns), 'project'),
            'list' => $users->filter(fn (User $user) => $targets->has($user->id))->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'targets' => $targets->get($user->id)->map(fn (PeriodTarget $target) => $title($target->activity).': '.$target->target)->all(),
            ])->values(),
        ]);
    }

    /**
     * Set the monthly target of every selected user, replacing what they had for the month.
     */
    public function store(PeriodTargetRequest $request)
    {
        $data = $request->validated();
        $activities = $request->activities();

        try {
            foreach ($data['user_ids'] as $userId) {
                PeriodTarget::setForUser((int) $userId, $data['month'].'-01', $activities, $request->user()->id);
            }
        } catch (\Throwable $th) {
            report($th);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set the target. Please try again.',
            ], 500);
        }

        $users = count($data['user_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Monthly target set for '.$users.' '.Str::plural('user', $users).'.',
            'data' => ['users' => $users, 'month' => $data['month']],
        ], 201);
    }

    /**
     * Remove the monthly target of a user for a month.
     */
    public function destroy(Request $request, User $period_target)
    {
        $filters = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        PeriodTarget::setForUser($period_target->id, $filters['month'].'-01', []);

        return response()->json(['success' => true, 'message' => 'Monthly target deleted successfully']);
    }
}
