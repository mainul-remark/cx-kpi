<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyTargetRequest;
use App\HelperFiles\HelperFile;
use App\Jobs\GenerateDailyTargetExport;
use App\Models\DailyTarget;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
            ->listing($filters['from'] ?? null, $filters['to'] ?? null, $request->filled('user_id') ? (int) $request->input('user_id') : null)
            // latest day first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->orderByDesc('target_date')->orderBy('user_id'));

        return DataTables::of($targets)
            ->addIndexColumn()
            ->toJson();
    }

    /**
     * The CSV of the targets on the page, built in the background.
     *
     * One action for the three steps, so the permission to export covers all of them:
     *  - without a token the export is queued and its token returned,
     *  - with a token its progress is returned,
     *  - with a token and download=1 the finished file is sent and removed from the disk.
     */
    public function export(Request $request)
    {
        $token = $request->query('token');

        if (is_string($token) && $token !== '') {
            return $request->boolean('download') ? $this->exportDownload($request, $token) : $this->exportStatus($request, $token);
        }

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $token = (string) Str::uuid();

        Cache::put(GenerateDailyTargetExport::cacheKey($token), [
            'status' => 'pending',
            'user_id' => $request->user()->id,
        ], now()->addMinutes(GenerateDailyTargetExport::TTL_MINUTES));

        GenerateDailyTargetExport::dispatch(
            $token,
            $request->user()->id,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            isset($filters['user_id']) ? (int) $filters['user_id'] : null,
        );

        // there is no worker running all the time on every installation, so make sure one picks the job up
        if (config('queue.default') !== 'sync') {
            HelperFile::startQueueWorker();
        }

        return response()->json(['token' => $token], 202);
    }

    private function exportStatus(Request $request, string $token)
    {
        $export = $this->ownExport($request, $token);

        if ($export['status'] === 'done' && !Storage::disk('local')->exists($export['file'])) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json(array_filter([
            'status' => $export['status'],
            'message' => $export['message'] ?? null,
        ]));
    }

    private function exportDownload(Request $request, string $token)
    {
        $export = $this->ownExport($request, $token);

        abort_unless($export['status'] === 'done' && Storage::disk('local')->exists($export['file']), 404);

        // a file is handed over once, and removed from the disk as soon as it has been sent
        Cache::forget(GenerateDailyTargetExport::cacheKey($token));

        return response()
            ->download(Storage::disk('local')->path($export['file']), $export['name'], ['Content-Type' => 'text/csv; charset=UTF-8'])
            ->deleteFileAfterSend(true);
    }

    /**
     * The outcome of a queued export of the user, a 404 for one that is unknown, expired or someone else's.
     *
     * @return array<string, mixed>
     */
    private function ownExport(Request $request, string $token): array
    {
        $export = Cache::get(GenerateDailyTargetExport::cacheKey($token));

        abort_unless(is_array($export) && (int) ($export['user_id'] ?? 0) === (int) $request->user()->id, 404);

        return $export;
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
