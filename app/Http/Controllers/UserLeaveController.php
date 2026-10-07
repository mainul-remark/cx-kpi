<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserLeaveRequest;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class UserLeaveController extends Controller
{
    /**
     * Display the official leave of the field users, one row per user per day.
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.leaves.index', [
                'users' => User::query()->where('usages_sector', 'field')->orderBy('name')->get(['id', 'name']),
                'portions' => UserLeave::PORTIONS,
                'types' => UserLeave::TYPES,
                'statuses' => UserLeave::STATUSES,
            ]);
        }

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(array_keys(UserLeave::STATUSES))],
        ]);

        $leaves = UserLeave::query()
            ->select('user_leaves.*')
            ->with(['user:id,name', 'approvedByUser:id,name'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->input('user_id')))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('leave_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('leave_date', '<=', $to))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            // latest day first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->orderByDesc('leave_date')->orderBy('user_id'));

        return DataTables::of($leaves)
            ->addIndexColumn()
            ->toJson();
    }

    /**
     * Put every selected user on leave on every working day of the range.
     */
    public function store(UserLeaveRequest $request)
    {
        $data = $request->validated();
        $dates = Holiday::workingDays($data['from'], $data['to']);

        if (empty($dates)) {
            return response()->json([
                'message' => 'The selected range has no working day.',
                'errors' => ['to' => ['The selected range has no working day. Fridays and holidays are off days already.']],
            ], 422);
        }

        try {
            UserLeave::setForUsers($data['user_ids'], $dates, $data, $request->user()->id);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to set the leave. Please try again.',
            ], 500);
        }

        $users = count($data['user_ids']);
        $days = count($dates);

        return response()->json([
            'success' => true,
            'message' => 'Leave set for '.$users.' '.Str::plural('user', $users).' on '.$days.' working '.Str::plural('day', $days).'.',
            'data' => ['users' => $users, 'days' => $days, 'dates' => $dates],
        ], 201);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserLeave $leave)
    {
        return response()->json($leave->load('user:id,name'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserLeaveRequest $request, UserLeave $leave)
    {
        try {
            // a leave still waiting or turned down keeps its decision, an approved one is now this user's
            $leave->update($request->validated() + ($leave->status === UserLeave::STATUS_APPROVED ? ['approved_by' => $request->user()->id] : []));
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leave. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Leave updated successfully',
            'data' => $leave,
        ]);
    }

    /**
     * Approve a leave a user asked for, so it counts from now on.
     */
    public function approve(Request $request, UserLeave $leave)
    {
        // the user may have reported on the day since asking for it
        if ($leave->portion === UserLeave::PORTION_FULL
            && !empty($reports = UserLeave::reportsOn([$leave->user_id], [$leave->leave_date->toDateString()]))) {
            return response()->json([
                'success' => false,
                'message' => 'A full day of leave cannot be approved on a day already reported on: '.$reports[0].'. Make it a half day, or delete the report first.',
            ], 422);
        }

        return $this->decide($request, $leave, UserLeave::STATUS_APPROVED);
    }

    /**
     * Turn down a leave, so it does not count.
     */
    public function reject(Request $request, UserLeave $leave)
    {
        return $this->decide($request, $leave, UserLeave::STATUS_REJECTED);
    }

    private function decide(Request $request, UserLeave $leave, string $status)
    {
        try {
            $leave->update(['status' => $status, 'approved_by' => $request->user()->id]);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leave. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Leave '.$status.' successfully',
            'data' => $leave,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserLeave $leave)
    {
        try {
            $leave->delete();
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete leave. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Leave deleted successfully',
        ]);
    }
}
