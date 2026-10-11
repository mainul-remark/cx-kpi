<?php

namespace App\Jobs;

use App\Exports\EmployeeKpiExport;
use App\Services\Kpi\EmployeeKpiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Builds the KPI workbook of a range in the background and keeps it on disk until its owner downloads it.
 *
 * The progress is kept in the cache under {@see self::cacheKey()}, which the page polls.
 */
class GenerateKpiExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * How long the outcome, and so the chance to download the file, is kept. Files left behind are removed
     * by kpi:purge-exports.
     */
    public const TTL_MINUTES = 30;

    public const DIRECTORY = 'exports/kpi';

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private readonly string $token,
        private readonly int $ownerId,
        private readonly string $from,
        private readonly string $to,
        private readonly ?int $userId,
        private readonly ?string $preset,
    ) {
    }

    public static function cacheKey(string $token): string
    {
        return 'kpi-export:'.$token;
    }

    public static function path(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.xlsx';
    }

    public static function filename(string $from, string $to): string
    {
        return 'kpi-sheet_'.$from.'_to_'.$to.'.xlsx';
    }

    public function handle(EmployeeKpiService $kpi): void
    {
        $rows = $kpi->sheet($this->from, $this->to, $this->userId);

        Excel::store(new EmployeeKpiExport($rows, $this->from, $this->to, $this->preset), self::path($this->token), 'local');

        Cache::put(self::cacheKey($this->token), [
            'status' => 'done',
            'user_id' => $this->ownerId,
            'file' => self::path($this->token),
            'name' => self::filename($this->from, $this->to),
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Cache::put(self::cacheKey($this->token), [
            'status' => 'failed',
            'user_id' => $this->ownerId,
            'message' => 'The export could not be generated, please try again.',
        ], now()->addMinutes(self::TTL_MINUTES));
    }
}
