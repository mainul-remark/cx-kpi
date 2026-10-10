<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Dashboard\ReportDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * The attendance sheet of the field users: the page itself, and its marks as JSON when asked for over ajax.
     *
     * A corporate user sees every field user, a field user only their own attendance.
     */
    public function index(Request $request, ReportDashboardService $reports, AttendanceService $checkIns)
    {
        $user = $request->user();
        $canViewAll = $user->usages_sector === 'corporate';

        if (!$request->ajax()) {
            return view('backend.attendance.index', [
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

        [$from, $to] = $reports->range($filters['preset'] ?? 'month', $filters['from'] ?? null, $filters['to'] ?? null);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= ReportDashboardService::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'The date range cannot be longer than '.ReportDashboardService::MAX_RANGE_DAYS.' days.',
            ]);
        }

        // a field user is always held to their own attendance, whatever user the request names
        $userId = $canViewAll ? (isset($filters['user_id']) ? (int) $filters['user_id'] : null) : (int) $user->id;

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'attendance' => $reports->attendance($from, $to, $userId),
            'check_ins' => $checkIns->sessions($from, $to, $userId, $canViewAll),
            'currently_in' => $canViewAll ? $checkIns->currentlyIn() : [],
        ]);
    }

    /**
     * A manager fixes when a check-in session ended, for example one the user forgot to end.
     */
    public function adjust(Request $request, AttendanceSession $session, AttendanceService $checkIns)
    {
        abort_unless($request->user()->usages_sector === 'corporate', 403);

        $data = $request->validate([
            'checked_out_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        // the manager types the time in the attendance timezone, the database keeps the app one
        $checkedOutAt = Carbon::createFromFormat('Y-m-d\TH:i', $data['checked_out_at'], config('attendance.timezone'))
            ->timezone(config('app.timezone'));

        $checkIns->adjust($session, $request->user(), $checkedOutAt, $data['note']);

        return response()->json(['success' => true, 'message' => 'The check out was updated.']);
    }
}
