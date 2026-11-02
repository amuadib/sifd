<?php

use App\Models\Category;
use App\Models\FileLocation;
use App\Models\InventoryFile;
use App\Models\ScanHistory;
use App\Models\StorageSource;
use App\Models\Tag;
use App\Services\Scanner\DirectoryScanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->testDir = storage_path('framework/testing/test_scan_dir_'.uniqid());
    File::makeDirectory($this->testDir, 0755, true);
});

afterEach(function () {
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

test('scanner reads files and populates inventory files and locations without modifying files', function () {
    $subDir = $this->testDir.DIRECTORY_SEPARATOR.'subfolder';
    File::makeDirectory($subDir, 0755, true);

    $file1 = $this->testDir.DIRECTORY_SEPARATOR.'document.pdf';
    $file2 = $subDir.DIRECTORY_SEPARATOR.'photo.png';

    File::put($file1, '%PDF-1.4 test document content');
    File::put($file2, 'dummy image content');

    $originalMtime1 = filemtime($file1);
    $originalSize1 = filesize($file1);

    $storage = StorageSource::create([
        'name' => 'Test Storage',
        'driver' => 'local',
        'base_path' => $this->testDir,
    ]);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);
    $result = $service->scanDirectory($storage, $this->testDir, recursive: true);

    expect($result->filesFound)->toBe(2)
        ->and($result->filesAdded)->toBe(2)
        ->and($result->locationsAdded)->toBe(2)
        ->and(InventoryFile::count())->toBe(2)
        ->and(FileLocation::count())->toBe(2);

    $invFile = InventoryFile::where('name', 'document.pdf')->first();
    expect($invFile)->not->toBeNull()
        ->and($invFile->extension)->toBe('pdf')
        ->and($invFile->file_type_group)->toBe('document')
        ->and($invFile->file_size)->toBe($originalSize1);

    // Verify Read-Only Policy: files on disk remain identical
    expect(filemtime($file1))->toBe($originalMtime1)
        ->and(filesize($file1))->toBe($originalSize1)
        ->and(File::get($file1))->toBe('%PDF-1.4 test document content');

    // Verify ScanHistory logged
    $history = ScanHistory::where('storage_source_id', $storage->id)->first();
    expect($history)->not->toBeNull()
        ->and($history->status)->toBe('completed')
        ->and($history->files_found)->toBe(2);
});

test('scanner deduplicates files with identical content across different locations', function () {
    $folderA = $this->testDir.DIRECTORY_SEPARATOR.'folderA';
    $folderB = $this->testDir.DIRECTORY_SEPARATOR.'folderB';
    File::makeDirectory($folderA, 0755, true);
    File::makeDirectory($folderB, 0755, true);

    $content = 'identical exact content between two files in different directories';
    File::put($folderA.DIRECTORY_SEPARATOR.'report_v1.txt', $content);
    File::put($folderB.DIRECTORY_SEPARATOR.'report_backup.txt', $content);

    $storage = StorageSource::create([
        'name' => 'Multi-folder Storage',
        'driver' => 'local',
        'base_path' => $this->testDir,
    ]);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);
    $result = $service->scanDirectory($storage, $this->testDir, recursive: true);

    // 2 files on disk found, but only 1 unique InventoryFile created!
    expect($result->filesFound)->toBe(2)
        ->and($result->filesAdded)->toBe(1)
        ->and($result->locationsAdded)->toBe(2)
        ->and(InventoryFile::count())->toBe(1)
        ->and(FileLocation::count())->toBe(2);

    $file = InventoryFile::first();
    expect($file->locations)->toHaveCount(2);
});

test('scanner does not duplicate existing files and updates last verified timestamp on rescan', function () {
    $file = $this->testDir.DIRECTORY_SEPARATOR.'single.txt';
    File::put($file, 'some text data');

    $storage = StorageSource::create([
        'name' => 'Single Storage',
        'driver' => 'local',
        'base_path' => $this->testDir,
    ]);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);

    // First scan
    $firstResult = $service->scanDirectory($storage, $this->testDir, recursive: true);
    expect($firstResult->filesAdded)->toBe(1)
        ->and($firstResult->locationsAdded)->toBe(1);

    // Second scan of the same folder
    $secondResult = $service->scanDirectory($storage, $this->testDir, recursive: true);
    expect($secondResult->filesFound)->toBe(1)
        ->and($secondResult->filesAdded)->toBe(0)
        ->and($secondResult->locationsAdded)->toBe(0)
        ->and($secondResult->filesUnmodified)->toBe(1)
        ->and(InventoryFile::count())->toBe(1)
        ->and(FileLocation::count())->toBe(1);
});

test('scanner respects non-recursive flag', function () {
    $subDir = $this->testDir.DIRECTORY_SEPARATOR.'sub';
    File::makeDirectory($subDir, 0755, true);

    File::put($this->testDir.DIRECTORY_SEPARATOR.'root.txt', 'root file');
    File::put($subDir.DIRECTORY_SEPARATOR.'nested.txt', 'nested file');

    $storage = StorageSource::create([
        'name' => 'Non-recursive Storage',
        'driver' => 'local',
        'base_path' => $this->testDir,
    ]);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);
    $result = $service->scanDirectory($storage, $this->testDir, recursive: false);

    expect($result->filesFound)->toBe(1)
        ->and($result->filesAdded)->toBe(1);
});

test('scanner can inventory only directories without scanning individual files inside', function () {
    // Create folder structure like E:\TV Series
    $seriesDir = $this->testDir.DIRECTORY_SEPARATOR.'TV Series';
    $folder1 = $seriesDir.DIRECTORY_SEPARATOR.'Alice in Borderland';
    $folder2 = $seriesDir.DIRECTORY_SEPARATOR.'Wednesday';
    $folder3 = $seriesDir.DIRECTORY_SEPARATOR.'Westworld';

    File::makeDirectory($folder1, 0755, true);
    File::makeDirectory($folder2, 0755, true);
    File::makeDirectory($folder3, 0755, true);

    // Put files inside Wednesday (these should NOT be inventoried individually)
    File::put($folder2.DIRECTORY_SEPARATOR.'Episode_01.mkv', 'video stream dummy');
    File::put($folder2.DIRECTORY_SEPARATOR.'Episode_01.srt', 'subtitle text dummy');

    $storage = StorageSource::create([
        'name' => 'TV Drive',
        'driver' => 'local',
        'base_path' => $seriesDir,
    ]);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);
    $result = $service->scanDirectory($storage, $seriesDir, recursive: false, scanMode: 'folder');

    // Only the 3 folders should be inventoried
    expect($result->filesFound)->toBe(3)
        ->and($result->filesAdded)->toBe(3)
        ->and(InventoryFile::count())->toBe(3);

    $wednesday = InventoryFile::where('name', 'Wednesday')->first();
    expect($wednesday)->not->toBeNull()
        ->and($wednesday->is_directory)->toBeTrue()
        ->and($wednesday->file_type_group)->toBe('folder')
        ->and($wednesday->formatted_size)->toBe('Folder')
        ->and($wednesday->metadata['item_count'])->toBe(2);

    // Ensure Episode_01.mkv was NOT recorded as a file
    expect(InventoryFile::where('name', 'Episode_01.mkv')->exists())->toBeFalse();

    // Verify deduplication: same folder name on another drive
    $anotherStorage = StorageSource::create([
        'name' => 'Backup Drive',
        'driver' => 'local',
        'base_path' => 'Z:\\Backup\\TV Series',
    ]);

    $loc = FileLocation::create([
        'inventory_file_id' => $wednesday->id,
        'storage_source_id' => $anotherStorage->id,
        'relative_path' => 'Wednesday',
        'original_filename' => 'Wednesday',
    ]);

    expect($wednesday->fresh()->locations)->toHaveCount(2);
});

test('scanner filters files by allowed extensions and ignores other files', function () {
    $file1 = $this->testDir.DIRECTORY_SEPARATOR.'document.pdf';
    $file2 = $this->testDir.DIRECTORY_SEPARATOR.'report.docx';
    $file3 = $this->testDir.DIRECTORY_SEPARATOR.'video.mkv';
    $file4 = $this->testDir.DIRECTORY_SEPARATOR.'photo.jpg';

    File::put($file1, '%PDF content');
    File::put($file2, 'DOCX content');
    File::put($file3, 'MKV video content');
    File::put($file4, 'JPG image content');

    $storage = StorageSource::create([
        'name' => 'Filtered Storage',
        'driver' => 'local',
        'base_path' => $this->testDir,
    ]);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);

    // Scan only pdf and docx files
    $result = $service->scanDirectory(
        source: $storage,
        targetPath: $this->testDir,
        recursive: false,
        allowedExtensions: ['pdf', '.DOCX']
    );

    expect($result->filesFound)->toBe(2)
        ->and($result->filesAdded)->toBe(2)
        ->and(InventoryFile::count())->toBe(2);

    expect(InventoryFile::where('name', 'document.pdf')->exists())->toBeTrue()
        ->and(InventoryFile::where('name', 'report.docx')->exists())->toBeTrue()
        ->and(InventoryFile::where('name', 'video.mkv')->exists())->toBeFalse()
        ->and(InventoryFile::where('name', 'photo.jpg')->exists())->toBeFalse();

    $history = ScanHistory::where('storage_source_id', $storage->id)->latest()->first();
    expect($history)->not->toBeNull()
        ->and($history->allowed_extensions)->toBe('pdf, docx');
});

test('scanner attaches tags to scanned items automatically', function () {
    $seriesDir = $this->testDir.DIRECTORY_SEPARATOR.'Series';
    $folder1 = $seriesDir.DIRECTORY_SEPARATOR.'Scorpion';
    $folder2 = $seriesDir.DIRECTORY_SEPARATOR.'Westworld';

    File::makeDirectory($folder1, 0755, true);
    File::makeDirectory($folder2, 0755, true);

    $storage = StorageSource::create([
        'name' => 'Series Drive',
        'driver' => 'local',
        'base_path' => $seriesDir,
    ]);

    $tag1 = Tag::create(['name' => 'TV Series', 'slug' => 'tv-series']);
    $tag2 = Tag::create(['name' => 'Favorit', 'slug' => 'favorit']);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);
    $result = $service->scanDirectory(
        source: $storage,
        targetPath: $seriesDir,
        recursive: false,
        scanMode: 'folder',
        tagIds: [$tag1->id, $tag2->id]
    );

    expect($result->filesFound)->toBe(2)
        ->and($result->filesAdded)->toBe(2);

    $westworld = InventoryFile::where('name', 'Westworld')->first();
    $scorpion = InventoryFile::where('name', 'Scorpion')->first();

    expect($westworld->tags)->toHaveCount(2)
        ->and($scorpion->tags)->toHaveCount(2)
        ->and($westworld->tags->pluck('name')->all())->toContain('TV Series', 'Favorit');
});

test('scanner assigns category to scanned items', function () {
    $seriesDir = $this->testDir.DIRECTORY_SEPARATOR.'TV_Shows';
    $folder = $seriesDir.DIRECTORY_SEPARATOR.'Wednesday';
    File::makeDirectory($folder, 0755, true);

    $storage = StorageSource::create([
        'name' => 'TV Shows Drive',
        'driver' => 'local',
        'base_path' => $seriesDir,
    ]);

    $category = Category::create([
        'name' => 'Entertainment',
        'slug' => 'entertainment',
    ]);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);
    $result = $service->scanDirectory(
        source: $storage,
        targetPath: $seriesDir,
        recursive: false,
        scanMode: 'folder',
        categoryId: $category->id
    );

    expect($result->filesFound)->toBe(1)
        ->and($result->filesAdded)->toBe(1);

    $wednesday = InventoryFile::where('name', 'Wednesday')->first();
    expect($wednesday)->not->toBeNull()
        ->and($wednesday->category_id)->toBe($category->id)
        ->and($wednesday->category->name)->toBe('Entertainment');
});

test('scanner can be cancelled gracefully during execution', function () {
    $dir = $this->testDir.DIRECTORY_SEPARATOR.'cancel_test';
    File::makeDirectory($dir, 0755, true);
    File::put($dir.DIRECTORY_SEPARATOR.'file1.txt', '1');
    File::put($dir.DIRECTORY_SEPARATOR.'file2.txt', '2');

    $storage = StorageSource::create([
        'name' => 'Cancel Storage',
        'driver' => 'local',
        'base_path' => $dir,
    ]);

    $existingHistory = ScanHistory::create([
        'storage_source_id' => $storage->id,
        'scanned_path' => $dir,
        'is_recursive' => false,
        'scan_mode' => 'file',
        'status' => 'pending',
    ]);

    // Set cancellation flag
    Cache::put("scan_cancel_{$existingHistory->id}", true, 60);

    /** @var DirectoryScanService $service */
    $service = app(DirectoryScanService::class);
    $result = $service->scanDirectory(
        source: $storage,
        targetPath: $dir,
        recursive: false,
        scanMode: 'file',
        existingHistory: $existingHistory
    );

    expect($existingHistory->fresh()->status)->toBe('cancelled')
        ->and($result->errors)->toContain('Pemindaian dibatalkan oleh pengguna.');
});
