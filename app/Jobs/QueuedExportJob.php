<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class QueuedExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        private readonly mixed  $export,
        private readonly string $storagePath,
        private readonly string $cacheKey,
    ) {}

    public function handle(): void
    {
        try {
            Excel::store($this->export, $this->storagePath, 'local');

            Cache::put($this->cacheKey, [
                'status' => 'done',
                'file'   => $this->storagePath,
            ], now()->addMinutes(10));

        } catch (\Throwable $e) {
            Cache::put($this->cacheKey, [
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ], now()->addMinutes(10));
        }
    }
}
