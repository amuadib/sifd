<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tag extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    /**
     * @return BelongsToMany<InventoryFile, $this>
     */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(InventoryFile::class, 'file_tag');
    }
}
