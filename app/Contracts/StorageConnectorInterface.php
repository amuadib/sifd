<?php

namespace App\Contracts;

interface StorageConnectorInterface
{
    /**
     * Get the driver identifier.
     */
    public function driver(): string;

    /**
     * Check if the given directory or path exists and is accessible (read-only).
     */
    public function isAccessible(string $path): bool;

    /**
     * Yield files found in the path. Strictly read-only directory iteration.
     *
     * @param  array<string>  $allowedExtensions  List of file extensions to filter by (empty means all)
     * @return \Generator<string> Yields absolute file paths
     */
    public function listFiles(string $path, bool $recursive = true, array $allowedExtensions = []): \Generator;

    /**
     * Yield direct subdirectories found in the path. Strictly read-only.
     *
     * @return \Generator<string> Yields absolute directory paths
     */
    public function listDirectories(string $path): \Generator;

    /**
     * Extract read-only metadata for a single directory.
     *
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
    public function getDirectoryDetails(string $fullPath): array;

    /**
     * Extract read-only metadata and hash for a single file.
     *
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
    public function getFileDetails(string $fullPath): array;
}
