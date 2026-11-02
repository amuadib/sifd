<?php

namespace App\Services\Queue;

use App\Models\ScanHistory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueWorkerService
{
    /**
     * Get the count of pending queue jobs in the database.
     */
    public function getPendingJobsCount(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Get the count of failed queue jobs in the database.
     */
    public function getFailedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Get the currently active or pending scan history.
     */
    public function getActiveScan(): ?ScanHistory
    {
        return ScanHistory::with('storageSource')
            ->whereIn('status', ['running', 'pending'])
            ->latest()
            ->first();
    }

    /**
     * Get real-time progress for an active scan from cache, falling back to database model.
     *
     * @return array<string, mixed>|null
     */
    public function getActiveProgress(?ScanHistory $scan): ?array
    {
        if (! $scan) {
            return null;
        }

        $cached = Cache::get("scan_active_{$scan->id}");
        if (is_array($cached)) {
            return $cached;
        }

        return [
            'history_id' => $scan->id,
            'current_path' => $scan->current_path ?? $scan->scanned_path,
            'files_found' => $scan->files_found,
            'files_added' => $scan->files_added,
            'locations_added' => $scan->locations_added,
            'files_unmodified' => $scan->files_unmodified,
            'files_failed' => $scan->files_failed,
            'duration_seconds' => $scan->duration_seconds,
            'status' => $scan->status,
        ];
    }

    /**
     * Start an asynchronous background queue worker process without terminal or cron.
     */
    public function startBackgroundWorker(): bool
    {
        try {
            $artisan = base_path('artisan');
            $php = PHP_BINARY;

            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = sprintf('start /B "" "%s" "%s" queue:work --stop-when-empty --tries=1 --memory=512', $php, $artisan);
                $handle = popen($cmd, 'r');
                if (is_resource($handle)) {
                    pclose($handle);

                    return true;
                }

                return false;
            } else {
                $cmd = sprintf('"%s" "%s" queue:work --stop-when-empty --tries=1 --memory=512 > /dev/null 2>&1 &', $php, $artisan);
                exec($cmd);

                return true;
            }
        } catch (Throwable $e) {
            Log::error('Failed to spawn background queue worker: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Process pending jobs immediately via artisan in-process.
     */
    public function processQueueImmediately(): void
    {
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 10,
            '--max-time' => 30,
        ]);
    }
}
