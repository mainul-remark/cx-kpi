<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveRequestRequest;
use App\Models\Holiday;
use App\Models\UserLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class LeaveRequestController extends Controller
{
    /**
     * Display the user's own leave, one row per day, with where each stands.
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.my-leaves.index', [
                'canApply' => $request->user()->usages_sector === 'field',
                'portions' => UserLeave::PORTIONS,
                'types' => UserLeave::TYPES,
                'statuses' => UserLeave::STATUSES,
            ]);
        }

        $leaves = UserLeave::query()
            ->select('user_leaves.*')
            ->with('approvedByUser:id,name')
            ->where('user_id', $request->user()->id)
            // latest day first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->orderByDesc('leave_date'));

        return DataTables::of($leaves)
            ->addIndexColumn()
            ->toJson();
    }

    /**
     * Ask for leave on every working day of a range. It counts once it is approved.
     */
    public function store(LeaveRequestRequest $request)
    {
        $data = $request->validated();
        $dates = Holiday::workingDays($data['from'], $data['to']);

        try {
            UserLeave::setForUsers([$request->user()->id], $dates, $data, null, UserLeave::STATUS_PENDING);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send the leave request. Please try again.',
            ], 500);
        }

        $days = count($dates);

        return response()->json([
            'success' => true,
            'message' => 'Leave requested for '.$days.' working '.Str::plural('day', $days).'. It counts once it is approved.',
            'data' => ['days' => $days, 'dates' => $dates],
        ], 201);
    }

    /**
     * Withdraw a leave request that has not been decided on yet.
     */
    public function destroy(Request $request, UserLeave $leave)
    {
        abort_unless((int) $leave->user_id === (int) $request->user()->id, 403);

        if ($leave->status !== UserLeave::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only a leave request still pending can be withdrawn.',
            ], 422);
        }

        try {
            $leave->delete();
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to withdraw the leave request. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Leave request withdrawn successfully',
        ]);
    }
}
