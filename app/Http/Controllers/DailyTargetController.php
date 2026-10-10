<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyTargetRequest;
use App\Models\DailyTarget;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class DailyTargetController extends Controller
{
    /**
     * Display the targets set for the field users, one row per user per day.
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.daily-targets.index', ['users' => $this->fieldUsers()]);
        }

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $targets = DailyTarget::query()
            ->select('daily_targets.*')
            ->with([
                'user:id,name',
                'setByUser:id,name',
                'projectCalls.project:id,name',
                'platformReplies.socialPlatform:id,name',
            ])
            ->withSum('platformReplies as platform_comments', 'comments')
            ->withSum('projectCalls as project_comments', 'comments')
            ->withSum('platformReplies as platform_messages', 'message_replies')
            ->withSum('projectCalls as project_messages', 'message_replies')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->input('user_id')))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('target_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('target_date', '<=', $to))
            // latest day first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->orderByDesc('target_date')->orderBy('user_id'));

        return DataTables::of($targets)
            ->addIndexColumn()
            ->toJson();
    }

    /**
     * Show the form for setting a target for field users over a date range.
     */
    public function create()
    {
        return $this->form(null);
    }

    /**
     * Set the target for every selected user on every working day of the range.
     */
    public function store(DailyTargetRequest $request)
    {
        $data = $request->validated();
        $dates = Holiday::workingDays($data['from'], $data['to']);

        if (empty($dates)) {
            return response()->json([
                'message' => 'The selected range has no working day.',
                'errors' => ['to' => ['The selected range has no working day. Fridays and holidays are skipped.']],
            ], 422);
        }

        try {
            DailyTarget::setForUsers($data['user_ids'], $dates, $data, $request->user()->id);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to set the target. Please try again.',
            ], 500);
        }

        $users = count($data['user_ids']);
        $days = count($dates);

        return response()->json([
            'success' => true,
            'message' => 'Target set for '.$users.' '.Str::plural('user', $users).' on '.$days.' working '.Str::plural('day', $days).'.',
            'data' => ['users' => $users, 'days' => $days, 'dates' => $dates],
        ], 201);
    }

    /**
     * Show the form filled with a target, to change it or apply it to other users and days.
     */
    public function edit(DailyTarget $dailyTarget)
    {
        return $this->form($dailyTarget);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DailyTarget $dailyTarget)
    {
        try {
            $dailyTarget->delete();
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete target. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Target deleted successfully',
        ]);
    }

    private function fieldUsers()
    {
        return User::query()->where('usages_sector', 'field')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The target form with one row per active platform and project, filled from the given target.
     */
    private function form(?DailyTarget $target)
    {
        $platforms = $target?->platformReplies()->get()->keyBy('social_platform_id') ?? collect();
        $projects = $target?->projectCalls()->get()->keyBy('project_id') ?? collect();

        $row = fn ($entity, $saved) => [
            'id' => $entity->id,
            'name' => $entity->name,
            'active' => true,
            'inbound_calls' => $entity->has_outbound_calls,
            'comments' => $entity->has_comments,
            'message_replies' => $entity->has_message_replies,
            'values' => [
                'inbound_calls' => $saved?->inbound_calls,
                'comments' => $saved?->comments,
                'message_replies' => $saved?->message_replies,
            ],
        ];

        $columns = ['id', 'name', 'has_outbound_calls', 'has_comments', 'has_message_replies'];

        $platformRows = SocialPlatform::query()
            ->where('active', true)
            ->orderBy('name')
            ->get($columns)
            ->map(fn (SocialPlatform $platform) => $row($platform, $platforms->get($platform->id)));

        $projectRows = Project::query()
            ->where('active', true)
            ->orderBy('name')
            ->get($columns)
            ->map(fn (Project $project) => $row($project, $projects->get($project->id)));

        $date = $target?->target_date->toDateString() ?? today()->toDateString();

        return view('backend.daily-targets.form', [
            'target' => $target,
            'users' => $this->fieldUsers(),
            'selectedUserIds' => $target ? [$target->user_id] : [],
            'from' => $date,
            'to' => $date,
            'platformRows' => $platformRows,
            'projectRows' => $projectRows,
        ]);
    }
}
