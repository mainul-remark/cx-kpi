<?php

namespace App\HelperFiles;

use App\Jobs\QueuedExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Contracts\Queue\ShouldQueue;

class HelperFile
{

    /**
     * Queue an Excel export, start the queue worker in the background,
     * and return a unique cache key the caller can use to poll status.
     *
     * Usage in controller:
     *   $key = HelperFile::exportExelOnQueue(new MyExport(), 'my-file.xlsx');
     *   return response()->json(['key' => $key]);
     */
    public static function exportExelOnQueue(mixed $export, string $filename): string
    {
        $key         = (string) Str::uuid();
        $storagePath = 'exports/' . $key . '/' . $filename;

        Cache::put($key, ['status' => 'pending'], now()->addMinutes(10));

        QueuedExportJob::dispatch($export, $storagePath, $key);

        self::startQueueWorker();

        return $key;
    }

    /**
     * Return the current status of a queued export as a JSON response.
     * When done, includes a download_url.
     *
     * Usage in controller:
     *   return HelperFile::exportStatus($key, 'route.name.download');
     */
    public static function exportStatus(string $key, string $downloadRoute): JsonResponse
    {
        $data = Cache::get($key);

        if (! $data) {
            return response()->json(['status' => 'expired']);
        }

        $response = ['status' => $data['status']];

        if ($data['status'] === 'done') {
            $response['download_url'] = route($downloadRoute, ['key' => $key]);
        }

        if ($data['status'] === 'failed') {
            $response['message'] = $data['message'] ?? 'Export failed.';
        }

        return response()->json($response);
    }

    /**
     * Stream the exported file to the browser and clean up storage.
     *
     * Usage in controller:
     *   return HelperFile::exportDownload($key);
     */
    public static function exportDownload(string $key): BinaryFileResponse
    {
        $data = Cache::get($key);

        if (! $data || $data['status'] !== 'done' || empty($data['file'])) {
            abort(404, 'Export file not found or has expired.');
        }

        $storagePath = $data['file'];
        $fullPath    = Storage::disk('local')->path($storagePath);

        if (! file_exists($fullPath)) {
            abort(404, 'Export file not found.');
        }

        $filename = basename($storagePath);

        Cache::forget($key);

        // deleteFileAfterSend ensures the file is removed only after streaming completes,
        // avoiding the null-stream error that occurs when deleting before the response is sent.
        $response = response()->download($fullPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);

        // Schedule directory cleanup after PHP shuts down (after response is fully sent)
        $dir = dirname($fullPath);
        register_shutdown_function(function () use ($dir) {
            @rmdir($dir);
        });

        return $response;
    }

    // -------------------------------------------------------------------------

    private static function startQueueWorker(): void
    {
        $php     = PHP_BINARY;
        $artisan = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            // Empty "" after /B is the window title — required so Windows doesn't
            // treat the first quoted path as the title instead of the executable.
            pclose(popen(
                "cmd /c start /B \"\" \"{$php}\" \"{$artisan}\" queue:work --stop-when-empty >NUL 2>&1",
                'r'
            ));
        } else {
            exec("\"{$php}\" \"{$artisan}\" queue:work --stop-when-empty > /dev/null 2>&1 &");
        }
    }
}
