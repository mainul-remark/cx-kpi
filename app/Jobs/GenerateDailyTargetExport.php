<?php

namespace App\Jobs;

use App\Models\DailyTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Writes the daily targets of a filter to a CSV in the background and keeps it on disk until its owner downloads it.
 *
 * The progress is kept in the cache under {@see self::cacheKey()}, which the page polls.
 */
class GenerateDailyTargetExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * How long the outcome, and so the chance to download the file, is kept. Files left behind are removed
     * by kpi:purge-exports.
     */
    public const TTL_MINUTES = 30;

    public const DIRECTORY = 'exports/daily-targets';

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private readonly string $token,
        private readonly int $ownerId,
        private readonly ?string $from,
        private readonly ?string $to,
        private readonly ?int $userId,
    ) {
    }

    public static function cacheKey(string $token): string
    {
        return 'daily-target-export:'.$token;
    }

    public static function path(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.csv';
    }

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);

        $handle = fopen($disk->path(self::path($this->token)), 'w');

        try {
            // the byte order mark makes Excel read the file as UTF-8
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Date', 'User', 'Outbound Calls', 'Inbound Calls (approx.)', 'Solved Issues (approx.)', 'Comments (approx.)',
                'Message Replies (approx.)', 'By Project (approx.)', 'By Social Platform (approx.)', 'Set By',
            ]);

            DailyTarget::query()
                ->listing($this->from, $this->to, $this->userId)
                ->orderByDesc('target_date')
                ->orderBy('user_id')
                ->orderBy('daily_targets.id')
                ->chunk(500, function ($targets) use ($handle) {
                    foreach ($targets as $target) {
                        fputcsv($handle, [
                            $target->target_date->toDateString(),
                            $target->user?->name,
                            $target->outbound_calls,
                            $target->inbound_calls,
                            $target->solved_issues,
                            $this->total($target->platform_comments, $target->project_comments),
                            $this->total($target->platform_messages, $target->project_messages),
                            $this->breakdown($target->projectCalls, 'project'),
                            $this->breakdown($target->platformReplies, 'socialPlatform'),
                            $target->setByUser?->name,
                        ]);
                    }
                });
        } finally {
            fclose($handle);
        }

        Cache::put(self::cacheKey($this->token), [
            'status' => 'done',
            'user_id' => $this->ownerId,
            'file' => self::path($this->token),
            'name' => 'daily-targets_'.now()->format('Y-m-d_His').'.csv',
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Storage::disk('local')->delete(self::path($this->token));

        Cache::put(self::cacheKey($this->token), [
            'status' => 'failed',
            'user_id' => $this->ownerId,
            'message' => 'The export could not be generated, please try again.',
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    // a total of approximate targets, empty when neither side has one
    private function total(mixed $a, mixed $b): ?int
    {
        return $a === null && $b === null ? null : (int) $a + (int) $b;
    }

    // "Name: 5 outbound calls, 3 comments, 2 replies" per project or platform that has a target
    private function breakdown($rows, string $relation): string
    {
        $parts = ['inbound_calls' => 'outbound calls', 'comments' => 'comments', 'message_replies' => 'replies'];

        return $rows->map(function ($row) use ($parts, $relation) {
            $values = [];
            foreach ($parts as $column => $label) {
                if ($row->{$column} !== null) {
                    $values[] = $row->{$column}.' '.$label;
                }
            }

            return $values ? ($row->{$relation}?->name ?? '').': '.implode(', ', $values) : null;
        })->filter()->implode('; ');
    }
}
