<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceService;
use Illuminate\Console\Command;

class RemindAttendanceCheckOut extends Command
{
    protected $signature   = 'attendance:remind';
    protected $description = 'Remind the users still checked in at the end of the day to check out.';

    public function handle(AttendanceService $attendance): int
    {
        $reminded = $attendance->remindOpenSessions();

        $this->info("Reminded {$reminded} user(s) to check out.");

        return self::SUCCESS;
    }
}
