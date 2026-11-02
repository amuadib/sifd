<?php

namespace App\Services\Scanner;

use App\DTOs\ScanResult;
use App\Models\FileLocation;
use App\Models\InventoryFile;
use App\Models\ScanHistory;
use App\Models\StorageSource;
use App\Services\Connectors\StorageConnectorManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class DirectoryScanService
{
    public function __construct(
        protected StorageConnectorManager $connectorManager
    ) {}

    /**
     * Perform a manual read-only directory scan on the specified storage source.
     *
     * @param  array<string>  $allowedExtensions
     * @param  array<string>  $tagIds
     */
    public function scanDirectory(
        StorageSource $source,
        string $targetPath,
        bool $recursive = true,
        ?int $userId = null,
        string $scanMode = 'file',
        array $allowedExtensions = [],
        array $tagIds = [],
        ?string $categoryId = null,
        ?ScanHistory $existingHistory = null
    ): ScanResult {
        $startTime = microtime(true);
        $connector = $this->connectorManager->forSource($source);

        $normalizedTarget = $this->normalizePath($targetPath);

        if (! $connector->isAccessible($normalizedTarget)) {
            throw new InvalidArgumentException("Directory [{$targetPath}] is not accessible or does not exist.");
        }

        $cleanExtensions = array_values(array_unique(array_filter(array_map(
            fn (string $e) => strtolower(ltrim(trim($e), '.')),
            $allowedExtensions
        ))));
        $extString = ! empty($cleanExtensions) ? implode(', ', $cleanExtensions) : null;

        if ($existingHistory) {
            $history = $existingHistory;
            $history->update([
                'status' => 'running',
                'current_path' => $normalizedTarget,
            ]);
        } else {
            $history = ScanHistory::create([
                'storage_source_id' => $source->id,
                'user_id' => $userId,
                'scanned_path' => $normalizedTarget,
                'current_path' => $normalizedTarget,
                'is_recursive' => $recursive,
                'scan_mode' => $scanMode,
                'allowed_extensions' => $extString,
                'status' => 'running',
            ]);
        }

        $filesFound = 0;
        $filesAdded = 0;
        $locationsAdded = 0;
        $filesUnmodified = 0;
        $filesFailed = 0;
        $errors = [];
        $lastDbUpdate = $startTime;

        try {
            $basePath = $this->normalizePath($source->base_path);

            $iterator = $scanMode === 'folder'
                ? $connector->listDirectories($normalizedTarget)
                : $connector->listFiles($normalizedTarget, $recursive, $cleanExtensions);

            foreach ($iterator as $itemPath) {
                // Check if cancellation was requested
                if (Cache::get("scan_cancel_{$history->id}") === true) {
                    $duration = round(microtime(true) - $startTime, 2);
                    $history->update([
                        'files_found' => $filesFound,
                        'files_added' => $filesAdded,
                        'locations_added' => $locationsAdded,
                        'files_unmodified' => $filesUnmodified,
                        'files_failed' => $filesFailed,
                        'duration_seconds' => $duration,
                        'status' => 'cancelled',
                        'current_path' => null,
                        'error_message' => 'Pemindaian dibatalkan oleh pengguna.',
                    ]);
                    Cache::forget("scan_cancel_{$history->id}");
                    Cache::forget("scan_active_{$history->id}");

                    return new ScanResult(
                        filesFound: $filesFound,
                        filesAdded: $filesAdded,
                        locationsAdded: $locationsAdded,
                        filesUnmodified: $filesUnmodified,
                        filesFailed: $filesFailed,
                        durationSeconds: $duration,
                        historyId: $history->id,
                        errors: ['Pemindaian dibatalkan oleh pengguna.']
                    );
                }

                $filesFound++;

                $now = microtime(true);
                if ($now - $lastDbUpdate >= 1.0 || $filesFound === 1) {
                    $history->update([
                        'files_found' => $filesFound,
                        'files_added' => $filesAdded,
                        'locations_added' => $locationsAdded,
                        'files_unmodified' => $filesUnmodified,
                        'files_failed' => $filesFailed,
                        'current_path' => $itemPath,
                        'duration_seconds' => round($now - $startTime, 2),
                    ]);
                    $lastDbUpdate = $now;
                }

                Cache::put("scan_active_{$history->id}", [
                    'history_id' => $history->id,
                    'current_path' => $itemPath,
                    'files_found' => $filesFound,
                    'files_added' => $filesAdded,
                    'locations_added' => $locationsAdded,
                    'files_unmodified' => $filesUnmodified,
                    'files_failed' => $filesFailed,
                    'duration_seconds' => round($now - $startTime, 2),
                    'status' => 'running',
                ], 60);

                try {
                    $details = $scanMode === 'folder'
                        ? $connector->getDirectoryDetails($itemPath)
                        : $connector->getFileDetails($itemPath);

                    $relativePath = $this->computeRelativePath($basePath, $itemPath);

                    DB::transaction(function () use (
                        $source,
                        $details,
                        $relativePath,
                        $scanMode,
                        $tagIds,
                        $categoryId,
                        &$filesAdded,
                        &$locationsAdded,
                        &$filesUnmodified
                    ) {
                        /** @var InventoryFile|null $inventoryFile */
                        $inventoryFile = InventoryFile::where('checksum_sha256', $details['checksum_sha256'])->first();

                        if (! $inventoryFile) {
                            $inventoryFile = InventoryFile::create([
                                'category_id' => $categoryId,
                                'is_directory' => $scanMode === 'folder',
                                'name' => $details['name'],
                                'extension' => $details['extension'],
                                'mime_type' => $details['mime_type'],
                                'file_type_group' => $details['file_type_group'],
                                'file_size' => $details['file_size'],
                                'checksum_sha256' => $details['checksum_sha256'],
                                'metadata' => $details['metadata'],
                            ]);
                            $filesAdded++;
                        } elseif ($categoryId && ! $inventoryFile->category_id) {
                            $inventoryFile->update(['category_id' => $categoryId]);
                        }

                        if (! empty($tagIds)) {
                            $inventoryFile->tags()->syncWithoutDetaching($tagIds);
                        }

                        /** @var FileLocation|null $location */
                        $location = FileLocation::where('storage_source_id', $source->id)
                            ->where('relative_path', $relativePath)
                            ->first();

                        if (! $location) {
                            FileLocation::create([
                                'inventory_file_id' => $inventoryFile->id,
                                'storage_source_id' => $source->id,
                                'relative_path' => $relativePath,
                                'original_filename' => $details['original_filename'],
                                'last_verified_at' => now(),
                                'is_accessible' => true,
                            ]);
                            $locationsAdded++;
                        } else {
                            $location->update([
                                'inventory_file_id' => $inventoryFile->id,
                                'last_verified_at' => now(),
                                'is_accessible' => true,
                            ]);
                            $filesUnmodified++;
                        }
                    });
                } catch (Throwable $e) {
                    $filesFailed++;
                    $errors[] = basename($itemPath).': '.$e->getMessage();
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            $history->update([
                'files_found' => $filesFound,
                'files_added' => $filesAdded,
                'locations_added' => $locationsAdded,
                'files_unmodified' => $filesUnmodified,
                'files_failed' => $filesFailed,
                'duration_seconds' => $duration,
                'status' => 'completed',
                'current_path' => null,
                'error_message' => empty($errors) ? null : implode("\n", array_slice($errors, 0, 10)),
            ]);

            Cache::forget("scan_active_{$history->id}");
            Cache::forget("scan_cancel_{$history->id}");

            $source->update([
                'last_scanned_at' => now(),
            ]);

            return new ScanResult(
                filesFound: $filesFound,
                filesAdded: $filesAdded,
                locationsAdded: $locationsAdded,
                filesUnmodified: $filesUnmodified,
                filesFailed: $filesFailed,
                durationSeconds: $duration,
                historyId: $history->id,
                errors: $errors
            );
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $history->update([
                'files_found' => $filesFound,
                'files_added' => $filesAdded,
                'locations_added' => $locationsAdded,
                'files_unmodified' => $filesUnmodified,
                'files_failed' => $filesFailed,
                'duration_seconds' => $duration,
                'status' => 'failed',
                'current_path' => null,
                'error_message' => $e->getMessage(),
            ]);

            Cache::forget("scan_active_{$history->id}");

            throw $e;
        }
    }

    public function cancelScan(string $historyId): void
    {
        Cache::put("scan_cancel_{$historyId}", true, 300);
        $history = ScanHistory::find($historyId);
        if ($history && in_array($history->status, ['pending', 'running'], true)) {
            $history->update([
                'status' => 'cancelled',
                'current_path' => null,
                'error_message' => 'Pemindaian dibatalkan oleh pengguna.',
            ]);
        }
        Cache::forget("scan_active_{$historyId}");
    }

    protected function normalizePath(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));

        return rtrim($normalized, DIRECTORY_SEPARATOR);
    }

    protected function computeRelativePath(string $basePath, string $fullFilePath): string
    {
        $normalizedBase = $this->normalizePath($basePath);
        $normalizedFile = $this->normalizePath($fullFilePath);

        if (str_starts_with($normalizedFile, $normalizedBase)) {
            $rel = substr($normalizedFile, strlen($normalizedBase));

            return ltrim(str_replace('\\', '/', $rel), '/');
        }

        return ltrim(str_replace('\\', '/', $normalizedFile), '/');
    }
}
