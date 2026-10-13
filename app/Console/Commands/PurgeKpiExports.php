<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyTargetExport;
use App\Jobs\GenerateKpiExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeKpiExports extends Command
{
    protected $signature   = 'kpi:purge-exports';
    protected $description = 'Delete the KPI export files that were generated but never downloaded.';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $limit = now()->subMinutes(GenerateKpiExport::TTL_MINUTES)->getTimestamp();
        $deleted = 0;

        foreach ([GenerateKpiExport::DIRECTORY, GenerateDailyTargetExport::DIRECTORY] as $directory) {
            foreach ($disk->files($directory) as $file) {
                if ($disk->lastModified($file) <= $limit && $disk->delete($file)) {
                    $deleted++;
                }
            }
        }

        $this->info("Deleted {$deleted} expired KPI export file(s).");

        return self::SUCCESS;
    }
}
