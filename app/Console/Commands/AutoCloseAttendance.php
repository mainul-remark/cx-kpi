<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceService;
use Illuminate\Console\Command;

class AutoCloseAttendance extends Command
{
    protected $signature   = 'attendance:auto-close';
    protected $description = 'Close the check-in sessions users forgot to end on an earlier day.';

    public function handle(AttendanceService $attendance): int
    {
        $closed = $attendance->closeStale();

        $this->info("Closed {$closed} forgotten session(s).");

        return self::SUCCESS;
    }
}
