<?php

namespace App\Console\Commands;

use App\Models\KpiSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotKpi extends Command
{
    protected $signature   = 'kpi:snapshot {month? : The month to freeze as YYYY-MM, last month when left out} {--force : Work out again the scores already frozen for the month}';
    protected $description = 'Freeze the KPI score of every field user for a month that is over.';

    public function handle(): int
    {
        $month = $this->argument('month');

        if ($month !== null && !Carbon::hasFormat($month, 'Y-m')) {
            $this->error('The month must be written as YYYY-MM, for example '.today()->subMonthNoOverflow()->format('Y-m').'.');
            return self::FAILURE;
        }

        $start = $month !== null
            ? Carbon::createFromFormat('!Y-m', $month)
            : today()->subMonthNoOverflow()->startOfMonth();

        // a month still running would be frozen with days missing
        if ($start->copy()->endOfMonth()->isAfter(today()->subDay()->endOfDay())) {
            $this->error($start->format('F Y').' is not over yet, so it cannot be frozen.');
            return self::FAILURE;
        }

        $saved = KpiSnapshot::freezeMonth($start, (bool) $this->option('force'));

        $this->info("Saved the KPI score of {$saved} user(s) for ".$start->format('F Y').'.');

        return self::SUCCESS;
    }
}
