<?php

use App\Models\Category;
use App\Models\FileLocation;
use App\Models\InventoryFile;
use App\Models\ScanHistory;
use App\Models\StorageSource;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

test('models generate valid uuid primary keys', function () {
    $storage = StorageSource::create([
        'name' => 'Local Harddisk',
        'driver' => 'local',
        'base_path' => 'D:\\TestFiles',
    ]);

    $category = Category::create([
        'name' => 'Dokumen Kerja',
        'slug' => 'dokumen-kerja',
    ]);

    $tag = Tag::create([
        'name' => 'Penting',
        'slug' => 'penting',
        'color' => 'red',
    ]);

    $file = InventoryFile::create([
        'category_id' => $category->id,
        'name' => 'Laporan 2026.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_type_group' => 'document',
        'file_size' => 1048576,
        'checksum_sha256' => hash('sha256', 'dummy file content'),
    ]);

    $location = FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'reports/2026/laporan.pdf',
        'original_filename' => 'laporan.pdf',
        'last_verified_at' => now(),
        'is_accessible' => true,
    ]);

    $user = User::factory()->create();

    $history = ScanHistory::create([
        'storage_source_id' => $storage->id,
        'user_id' => $user->id,
        'scanned_path' => 'D:\\TestFiles\\reports',
        'is_recursive' => true,
        'files_found' => 1,
        'files_added' => 1,
        'locations_added' => 1,
        'duration_seconds' => 0.45,
    ]);

    expect(Str::isUuid($storage->id))->toBeTrue()
        ->and(Str::isUuid($category->id))->toBeTrue()
        ->and(Str::isUuid($tag->id))->toBeTrue()
        ->and(Str::isUuid($file->id))->toBeTrue()
        ->and(Str::isUuid($location->id))->toBeTrue()
        ->and(Str::isUuid($history->id))->toBeTrue();
});

test('inventory file has correct relationships and multi-location support', function () {
    $storage1 = StorageSource::create([
        'name' => 'PC Utama',
        'driver' => 'local',
        'base_path' => 'C:\\Data',
    ]);

    $storage2 = StorageSource::create([
        'name' => 'NAS Backup',
        'driver' => 'local',
        'base_path' => 'Z:\\Backup',
    ]);

    $file = InventoryFile::create([
        'name' => 'Design System.fig',
        'extension' => 'fig',
        'mime_type' => 'application/octet-stream',
        'file_type_group' => 'other',
        'file_size' => 5242880, // 5 MB
        'checksum_sha256' => hash('sha256', 'unique fig content'),
    ]);

    $loc1 = FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage1->id,
        'relative_path' => 'projects/design.fig',
        'original_filename' => 'design.fig',
    ]);

    $loc2 = FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage2->id,
        'relative_path' => 'backup/2026/design_copy.fig',
        'original_filename' => 'design_copy.fig',
    ]);

    $tag = Tag::create(['name' => 'UI/UX', 'slug' => 'ui-ux']);
    $file->tags()->attach($tag->id);

    expect($file->locations)->toHaveCount(2)
        ->and($file->tags)->toHaveCount(1)
        ->and($file->formatted_size)->toBe('5 MB')
        ->and($loc1->full_path)->toBe('C:\\Data\\projects/design.fig');
});

test('inventory file enforces unique checksum and soft deletes', function () {
    $file = InventoryFile::create([
        'name' => 'test.txt',
        'extension' => 'txt',
        'file_size' => 100,
        'checksum_sha256' => 'abc123uniquehash',
    ]);

    expect(fn () => InventoryFile::create([
        'name' => 'duplicate.txt',
        'extension' => 'txt',
        'file_size' => 100,
        'checksum_sha256' => 'abc123uniquehash',
    ]))->toThrow(Exception::class);

    $file->delete();
    expect(InventoryFile::find($file->id))->toBeNull()
        ->and(InventoryFile::withTrashed()->find($file->id))->not->toBeNull();
});
