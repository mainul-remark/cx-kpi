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
            ->with(['user:id,name', 'setByUser:id,name'])
            ->withSum('platformReplies as platform_replies_total', 'total_replies')
            ->withSum('projectCalls as project_calls_total', 'total_calls')
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
     * The date ranges offered as one-click buttons on the form.
     *
     * @return list<array{label: string, from: string, to: string}>
     */
    private function presets(): array
    {
        $today = today();
        $nextWeek = $today->copy()->startOfWeek(Holiday::WEEK_START_DAY)->addWeek();
        $nextMonth = $today->copy()->addMonthNoOverflow()->startOfMonth();

        $ranges = [
            'Today' => [$today, $today],
            'Tomorrow' => [$today->copy()->addDay(), $today->copy()->addDay()],
            'Next Week' => [$nextWeek, $nextWeek->copy()->addDays(6)],
            'Rest of the Month' => [$today, $today->copy()->endOfMonth()],
            'Next Month' => [$nextMonth, $nextMonth->copy()->endOfMonth()],
        ];

        return collect($ranges)
            ->map(fn (array $range, string $label) => [
                'label' => $label,
                'from' => $range[0]->toDateString(),
                'to' => $range[1]->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * The target form with one row per active platform and project, filled from the given target.
     */
    private function form(?DailyTarget $target)
    {
        $replies = $target?->platformReplies()->pluck('total_replies', 'social_platform_id') ?? collect();
        $calls = $target?->projectCalls()->pluck('total_calls', 'project_id') ?? collect();

        $platformRows = SocialPlatform::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SocialPlatform $platform) => [
                'id' => $platform->id,
                'name' => $platform->name,
                'count' => $replies->get($platform->id),
            ]);

        $projectRows = Project::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'count' => $calls->get($project->id),
            ]);

        $date = $target?->target_date->toDateString() ?? today()->toDateString();

        return view('backend.daily-targets.form', [
            'target' => $target,
            'users' => $this->fieldUsers(),
            'selectedUserIds' => $target ? [$target->user_id] : [],
            'from' => $date,
            'to' => $date,
            'presets' => $this->presets(),
            'platformRows' => $platformRows,
            'projectRows' => $projectRows,
        ]);
    }
}
