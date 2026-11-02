<?php

namespace App\Services\Connectors;

use App\Contracts\StorageConnectorInterface;
use DirectoryIterator;
use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

class LocalConnector implements StorageConnectorInterface
{
    public function driver(): string
    {
        return 'local';
    }

    public function isAccessible(string $path): bool
    {
        return file_exists($path) && is_readable($path) && is_dir($path);
    }

    /**
     * @param  array<string>  $allowedExtensions
     * @return Generator<string>
     */
    public function listFiles(string $path, bool $recursive = true, array $allowedExtensions = []): Generator
    {
        if (! $this->isAccessible($path)) {
            return;
        }

        $filterExts = array_map(fn (string $e) => strtolower(ltrim(trim($e), '.')), array_filter($allowedExtensions));
        $hasFilter = ! empty($filterExts);

        if ($recursive) {
            try {
                $directoryIterator = new RecursiveDirectoryIterator(
                    $path,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO
                );

                $iterator = new RecursiveIteratorIterator(
                    $directoryIterator,
                    RecursiveIteratorIterator::LEAVES_ONLY,
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );

                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isFile() && $fileInfo->isReadable()) {
                        if ($hasFilter) {
                            $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                            if (! in_array($ext, $filterExts, true)) {
                                continue;
                            }
                        }

                        yield $fileInfo->getPathname();
                    }
                }
            } catch (UnexpectedValueException) {
                // Ignore unreadable subdirectories gracefully
            }
        } else {
            try {
                $iterator = new DirectoryIterator($path);
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isFile() && ! $fileInfo->isDot() && $fileInfo->isReadable()) {
                        if ($hasFilter) {
                            $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                            if (! in_array($ext, $filterExts, true)) {
                                continue;
                            }
                        }

                        yield $fileInfo->getPathname();
                    }
                }
            } catch (UnexpectedValueException) {
                // Ignore unreadable directory gracefully
            }
        }
    }

    /**
     * @return Generator<string>
     */
    public function listDirectories(string $path): Generator
    {
        if (! $this->isAccessible($path)) {
            return;
        }

        try {
            $iterator = new DirectoryIterator($path);
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDir() && ! $fileInfo->isDot() && $fileInfo->isReadable()) {
                    yield $fileInfo->getPathname();
                }
            }
        } catch (UnexpectedValueException) {
            // Ignore unreadable directory gracefully
        }
    }

    /**
     * @return array{
     *     name: string,
     *     original_filename: string,
     *     extension: string,
     *     mime_type: string|null,
     *     file_type_group: string,
     *     file_size: int,
     *     checksum_sha256: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function getDirectoryDetails(string $fullPath): array
    {
        $folderName = basename($fullPath);
        $mtime = filemtime($fullPath) ?: time();

        $itemCount = 0;
        try {
            $inner = new DirectoryIterator($fullPath);
            foreach ($inner as $item) {
                if (! $item->isDot()) {
                    $itemCount++;
                }
            }
        } catch (\Throwable) {
            // Ignore if subfolder unreadable
        }

        $checksum = hash('sha256', 'folder:'.mb_strtolower(trim($folderName)));

        return [
            'name' => $folderName,
            'original_filename' => $folderName,
            'extension' => 'folder',
            'mime_type' => 'directory',
            'file_type_group' => 'folder',
            'file_size' => 0,
            'checksum_sha256' => $checksum,
            'metadata' => [
                'item_count' => $itemCount,
                'folder_modified_at' => date('Y-m-d H:i:s', $mtime),
            ],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     original_filename: string,
     *     extension: string,
     *     mime_type: string|null,
     *     file_type_group: string,
     *     file_size: int,
     *     checksum_sha256: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function getFileDetails(string $fullPath): array
    {
        $filename = basename($fullPath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $name = pathinfo($fullPath, PATHINFO_FILENAME).($extension !== '' ? '.'.$extension : '');
        $fileSize = (int) (filesize($fullPath) ?: 0);
        $mtime = filemtime($fullPath) ?: time();

        $mimeType = null;
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($fullPath);
            if ($detected !== false) {
                $mimeType = $detected;
            }
        }

        $typeGroup = $this->determineTypeGroup($extension, $mimeType);
        $checksum = hash_file('sha256', $fullPath) ?: hash('sha256', $fullPath.$fileSize);

        $metadata = [
            'file_modified_at' => date('Y-m-d H:i:s', $mtime),
            'system_permissions' => substr(sprintf('%o', fileperms($fullPath) ?: 0), -4),
        ];

        if ($typeGroup === 'image') {
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo !== false) {
                $metadata['image_width'] = $imageInfo[0];
                $metadata['image_height'] = $imageInfo[1];
                if (isset($imageInfo['bits'])) {
                    $metadata['image_bits'] = $imageInfo['bits'];
                }
            }
        }

        return [
            'name' => $name,
            'original_filename' => $filename,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'file_type_group' => $typeGroup,
            'file_size' => $fileSize,
            'checksum_sha256' => $checksum,
            'metadata' => $metadata,
        ];
    }

    protected function determineTypeGroup(string $extension, ?string $mimeType): string
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'tiff', 'tif', 'avif'];
        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv'];
        $videoExtensions = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v'];
        $audioExtensions = ['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'wma'];
        $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'iso'];
        $codeExtensions = ['php', 'js', 'ts', 'html', 'css', 'json', 'xml', 'sql', 'py', 'java', 'c', 'cpp', 'sh', 'yml', 'yaml', 'go', 'rs'];

        if (in_array($extension, $imageExtensions, true) || ($mimeType && str_starts_with($mimeType, 'image/'))) {
            return 'image';
        }

        if (in_array($extension, $documentExtensions, true) || ($mimeType && (str_contains($mimeType, 'pdf') || str_contains($mimeType, 'msword') || str_contains($mimeType, 'officedocument')))) {
            return 'document';
        }

        if (in_array($extension, $videoExtensions, true) || ($mimeType && str_starts_with($mimeType, 'video/'))) {
            return 'video';
        }

        if (in_array($extension, $audioExtensions, true) || ($mimeType && str_starts_with($mimeType, 'audio/'))) {
            return 'audio';
        }

        if (in_array($extension, $archiveExtensions, true) || ($mimeType && (str_contains($mimeType, 'zip') || str_contains($mimeType, 'compressed') || str_contains($mimeType, 'tar')))) {
            return 'archive';
        }

        if (in_array($extension, $codeExtensions, true)) {
            return 'code';
        }

        return 'other';
    }
}
