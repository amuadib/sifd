<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventory_file_id
 * @property string $storage_source_id
 * @property string $relative_path
 * @property string $original_filename
 * @property Carbon|null $last_verified_at
 * @property bool $is_accessible
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryFile|null $inventoryFile
 * @property-read StorageSource|null $storageSource
 * @property-read string $full_path
 */
class FileLocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventory_file_id',
        'storage_source_id',
        'relative_path',
        'original_filename',
        'last_verified_at',
        'is_accessible',
    ];

    protected function casts(): array
    {
        return [
            'last_verified_at' => 'datetime',
            'is_accessible' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<InventoryFile, $this>
     */
    public function inventoryFile(): BelongsTo
    {
        return $this->belongsTo(InventoryFile::class, 'inventory_file_id');
    }

    /**
     * @return BelongsTo<StorageSource, $this>
     */
    public function storageSource(): BelongsTo
    {
        return $this->belongsTo(StorageSource::class, 'storage_source_id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullPath(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $base = $this->storageSource instanceof StorageSource ? $this->storageSource->base_path : '';
                $relative = ltrim($this->relative_path, '/\\');

                if (empty($base)) {
                    return $relative;
                }

                $separator = str_contains($base, '/') ? '/' : '\\';

                return rtrim($base, '/\\').$separator.$relative;
            }
        );
    }
}
