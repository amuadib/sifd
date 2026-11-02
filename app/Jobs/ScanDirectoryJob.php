<?php

namespace App\Jobs;

use App\Models\ScanHistory;
use App\Models\StorageSource;
use App\Services\Scanner\DirectoryScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ScanDirectoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /**
     * @param  array<string>  $allowedExtensions
     * @param  array<string>  $tagIds
     */
    public function __construct(
        public string $scanHistoryId,
        public string $storageSourceId,
        public string $targetPath,
        public bool $recursive = true,
        public ?int $userId = null,
        public string $scanMode = 'file',
        public array $allowedExtensions = [],
        public array $tagIds = [],
        public ?string $categoryId = null
    ) {}

    public function handle(DirectoryScanService $scanner): void
    {
        $history = ScanHistory::find($this->scanHistoryId);
        if (! $history) {
            return;
        }

        // Check if cancelled while waiting in queue
        if (Cache::get("scan_cancel_{$this->scanHistoryId}") === true || $history->status === 'cancelled') {
            $history->update([
                'status' => 'cancelled',
                'current_path' => null,
                'error_message' => 'Pemindaian dibatalkan sebelum dijalankan.',
            ]);
            Cache::forget("scan_cancel_{$this->scanHistoryId}");

            return;
        }

        $source = StorageSource::find($this->storageSourceId);
        if (! $source) {
            $history->update([
                'status' => 'failed',
                'current_path' => null,
                'error_message' => 'Sumber penyimpanan tidak ditemukan.',
            ]);

            return;
        }

        $scanner->scanDirectory(
            source: $source,
            targetPath: $this->targetPath,
            recursive: $this->recursive,
            userId: $this->userId,
            scanMode: $this->scanMode,
            allowedExtensions: $this->allowedExtensions,
            tagIds: $this->tagIds,
            categoryId: $this->categoryId,
            existingHistory: $history
        );
    }
}
