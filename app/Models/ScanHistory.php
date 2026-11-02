<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $storage_source_id
 * @property int|null $user_id
 * @property string $scanned_path
 * @property string|null $current_path
 * @property bool $is_recursive
 * @property string $scan_mode
 * @property string|null $allowed_extensions
 * @property int $files_found
 * @property int $files_added
 * @property int $locations_added
 * @property int $files_unmodified
 * @property int $files_failed
 * @property float $duration_seconds
 * @property string $status
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScanHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'storage_source_id',
        'user_id',
        'scanned_path',
        'current_path',
        'is_recursive',
        'scan_mode',
        'allowed_extensions',
        'files_found',
        'files_added',
        'locations_added',
        'files_unmodified',
        'files_failed',
        'duration_seconds',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'is_recursive' => 'boolean',
            'files_found' => 'integer',
            'files_added' => 'integer',
            'locations_added' => 'integer',
            'files_unmodified' => 'integer',
            'files_failed' => 'integer',
            'duration_seconds' => 'float',
        ];
    }

    /**
     * @return BelongsTo<StorageSource, $this>
     */
    public function storageSource(): BelongsTo
    {
        return $this->belongsTo(StorageSource::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
