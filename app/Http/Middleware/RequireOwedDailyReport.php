<?php

namespace App\Http\Middleware;

use App\Services\Attendance\AttendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A field user who checked in on an earlier day without filing that day's daily report is sent to the report form
 * for it, on whatever page they open, until every such report is filed.
 */
class RequireOwedDailyReport
{
    /** The routes the user needs to file the report, so they are never sent away from them. */
    private const EXEMPT = ['daily-reports.create', 'daily-reports.store', 'daily-reports.edit', 'daily-reports.update'];

    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            !$user
            || $user->usages_sector !== 'field'
            || !$request->isMethod('GET')
            || $request->expectsJson()
            || $request->ajax()
            || $request->routeIs(...self::EXEMPT)
        ) {
            return $next($request);
        }

        $owed = $this->attendance->owedReportDates($user);

        if ($owed === []) {
            return $next($request);
        }

        return redirect()
            ->route('daily-reports.create', ['date' => $owed[0]])
            ->with('owed_report_dates', $owed);
    }
}
