<?php

namespace App\DTOs;

readonly class ScanResult
{
    /**
     * @param  array<string>  $errors
     */
    public function __construct(
        public int $filesFound,
        public int $filesAdded,
        public int $locationsAdded,
        public int $filesUnmodified,
        public int $filesFailed,
        public float $durationSeconds,
        public ?string $historyId = null,
        public array $errors = [],
    ) {}

    /**
     * @return array{
     *     files_found: int,
     *     files_added: int,
     *     locations_added: int,
     *     files_unmodified: int,
     *     files_failed: int,
     *     duration_seconds: float,
     *     history_id: string|null,
     *     errors: array<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'files_found' => $this->filesFound,
            'files_added' => $this->filesAdded,
            'locations_added' => $this->locationsAdded,
            'files_unmodified' => $this->filesUnmodified,
            'files_failed' => $this->filesFailed,
            'duration_seconds' => $this->durationSeconds,
            'history_id' => $this->historyId,
            'errors' => $this->errors,
        ];
    }
}
