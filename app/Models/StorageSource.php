<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $driver
 * @property string $base_path
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $last_scanned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StorageSource extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'driver',
        'base_path',
        'description',
        'is_active',
        'last_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scanned_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FileLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(FileLocation::class);
    }

    /**
     * @return HasMany<ScanHistory, $this>
     */
    public function scanHistories(): HasMany
    {
        return $this->hasMany(ScanHistory::class);
    }
}
