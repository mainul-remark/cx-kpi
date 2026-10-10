<?php

namespace App\Http\Middleware;

use App\Services\Attendance\AttendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A field user who has checked in (and not checked out) or worked today is sent to the daily report form, on whatever
 * page they open, until today's report is filed.
 */
class RequireDailyReport
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
            || !$this->attendance->mustFileReport($user)
        ) {
            return $next($request);
        }

        return redirect()
            ->route('daily-reports.create')
            ->with('report_required', true);
    }
}
