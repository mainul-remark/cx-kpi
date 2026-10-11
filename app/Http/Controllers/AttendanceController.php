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
     *
     * The sheet and the check-in log are paged on the server, each by its own page:
     *  - no section: the first page of both, with the totals of the sheet and who is checked in now,
     *  - section=sheet or section=check_ins: only that section, for the page asked for,
     *  - export=1: every row of the sheet, for the CSV.
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
            'section' => ['nullable', Rule::in(['sheet', 'check_ins'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'summary' => ['nullable', 'boolean'],
            'export' => ['nullable', 'boolean'],
        ]);

        [$from, $to] = $reports->range($filters['preset'] ?? 'month', $filters['from'] ?? null, $filters['to'] ?? null);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= ReportDashboardService::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'The date range cannot be longer than '.ReportDashboardService::MAX_RANGE_DAYS.' days.',
            ]);
        }

        // a field user is always held to their own attendance, whatever user the request names
        $userId = $canViewAll ? (isset($filters['user_id']) ? (int) $filters['user_id'] : null) : (int) $user->id;

        $range = ['from' => $from, 'to' => $to];

        // the whole sheet in one go, which is what a file of it needs
        if ($request->boolean('export')) {
            return response()->json(['range' => $range, 'attendance' => $reports->attendance($from, $to, $userId)]);
        }

        $section = $filters['section'] ?? null;
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? config('attendance.per_page'));
        $response = ['range' => $range];

        if ($section === null || $section === 'sheet') {
            // the totals are only worked out for a new sheet, turning its pages leaves the tiles as they are
            $response['attendance'] = $reports->attendancePage(
                $from, $to, $userId, $page, $perPage, $request->boolean('summary', true)
            );
        }

        if ($section === null || $section === 'check_ins') {
            $response['check_ins'] = $checkIns->sessions($from, $to, $userId, $canViewAll, $page, $perPage);
        }

        if ($section === null) {
            $response['currently_in'] = $canViewAll ? $checkIns->currentlyIn() : [];
        }

        return response()->json($response);
    }

    /**
     * A manager fixes when a check-in session started and ended, for example one the user forgot to end.
     */
    public function adjust(Request $request, AttendanceSession $session, AttendanceService $checkIns)
    {
        abort_unless($request->user()->usages_sector === 'corporate', 403);

        $data = $request->validate([
            'checked_in_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'checked_out_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        // the manager types the times in the attendance timezone, the database keeps the app one
        $toAppTime = fn (string $value) => Carbon::createFromFormat('Y-m-d\TH:i', $value, config('attendance.timezone'))
            ->timezone(config('app.timezone'));

        $checkIns->adjust(
            $session,
            $request->user(),
            $toAppTime($data['checked_out_at']),
            $data['note'],
            filled($data['checked_in_at'] ?? null) ? $toAppTime($data['checked_in_at']) : null,
        );

        return response()->json(['success' => true, 'message' => 'The check in and check out were updated.']);
    }
}
