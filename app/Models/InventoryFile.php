<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $category_id
 * @property bool $is_directory
 * @property string $name
 * @property string $extension
 * @property string|null $mime_type
 * @property string $file_type_group
 * @property int $file_size
 * @property string $checksum_sha256
 * @property array<string, mixed>|null $metadata
 * @property string|null $preview_path
 * @property string $preview_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $formatted_size
 */
class InventoryFile extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id',
        'is_directory',
        'name',
        'extension',
        'mime_type',
        'file_type_group',
        'file_size',
        'checksum_sha256',
        'metadata',
        'preview_path',
        'preview_status',
    ];

    protected function casts(): array
    {
        return [
            'is_directory' => 'boolean',
            'file_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<FileLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(FileLocation::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'file_tag');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedSize(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->is_directory && $this->file_size <= 0) {
                    return 'Folder';
                }

                $bytes = $this->file_size;
                if ($bytes <= 0) {
                    return '0 B';
                }

                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $i = (int) floor(log($bytes, 1024));
                $i = min($i, count($units) - 1);

                return round($bytes / pow(1024, $i), 2).' '.$units[$i];
            }
        );
    }
}
