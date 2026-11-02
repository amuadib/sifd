<?php

use App\Jobs\ScanDirectoryJob;
use App\Models\Category;
use App\Models\FileLocation;
use App\Models\InventoryFile;
use App\Models\ScanHistory;
use App\Models\StorageSource;
use App\Models\Tag;
use App\Models\User;
use App\Services\Scanner\DirectoryScanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated user cannot access inventory pages', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('files.index'))->assertRedirect(route('login'));
    $this->get(route('scanner.index'))->assertRedirect(route('login'));
    $this->get(route('storage-sources.index'))->assertRedirect(route('login'));
    $this->get(route('categories.index'))->assertRedirect(route('login'));
    $this->get(route('tags.index'))->assertRedirect(route('login'));
    $this->get(route('scan-histories.index'))->assertRedirect(route('login'));
});

test('authenticated user can view dashboard and see metrics', function () {
    $storage = StorageSource::create([
        'name' => 'Drive D',
        'driver' => 'local',
        'base_path' => 'D:\\Data',
    ]);

    $file = InventoryFile::create([
        'name' => 'document.pdf',
        'extension' => 'pdf',
        'file_type_group' => 'document',
        'file_size' => 2048,
        'checksum_sha256' => hash('sha256', 'dash_test_content'),
    ]);

    FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'doc.pdf',
        'original_filename' => 'doc.pdf',
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Inventaris Digital')
        ->assertSee('Drive D');
});

test('authenticated user can view files catalog and search', function () {
    $file1 = InventoryFile::create([
        'name' => 'Laporan_Keuangan_2026.xlsx',
        'extension' => 'xlsx',
        'file_type_group' => 'document',
        'file_size' => 1024,
        'checksum_sha256' => hash('sha256', 'file1_content'),
    ]);

    $file2 = InventoryFile::create([
        'name' => 'Logo_Perusahaan.png',
        'extension' => 'png',
        'file_type_group' => 'image',
        'file_size' => 2048,
        'checksum_sha256' => hash('sha256', 'file2_content'),
    ]);

    $this->actingAs($this->user)
        ->get(route('files.index'))
        ->assertOk()
        ->assertSee('Laporan_Keuangan_2026.xlsx')
        ->assertSee('Logo_Perusahaan.png');

    // Test livewire search filtering
    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->set('search', 'Keuangan')
        ->assertSee('Laporan_Keuangan_2026.xlsx')
        ->assertDontSee('Logo_Perusahaan.png');
});

test('user can toggle table column sort ASC and DESC including category', function () {
    $catA = Category::create(['name' => 'Alpha Category', 'slug' => 'alpha-category']);
    $catZ = Category::create(['name' => 'Zulu Category', 'slug' => 'zulu-category']);

    InventoryFile::create([
        'name' => 'A_Document.pdf',
        'category_id' => $catZ->id,
        'extension' => 'pdf',
        'file_type_group' => 'document',
        'file_size' => 500,
        'checksum_sha256' => hash('sha256', 'a_doc'),
    ]);

    InventoryFile::create([
        'name' => 'Z_Video.mkv',
        'category_id' => $catA->id,
        'extension' => 'mkv',
        'file_type_group' => 'video',
        'file_size' => 50000,
        'checksum_sha256' => hash('sha256', 'z_vid'),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->call('sortBy', 'name')
        ->assertSet('sortField', 'name')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['A_Document.pdf', 'Z_Video.mkv'])
        ->call('sortBy', 'name')
        ->assertSet('sortField', 'name')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Z_Video.mkv', 'A_Document.pdf'])
        ->call('sortBy', 'category')
        ->assertSet('sortField', 'category')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['Z_Video.mkv', 'A_Document.pdf'])
        ->call('sortBy', 'category')
        ->assertSet('sortField', 'category')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['A_Document.pdf', 'Z_Video.mkv']);
});

test('sorting preferences are preserved in session and applied across page visits', function () {
    // 1. User visits and changes sort to category and dir to asc
    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->call('sortBy', 'category')
        ->assertSet('sortField', 'category')
        ->assertSet('sortDirection', 'asc');

    expect(session('files_sort_field'))->toBe('category')
        ->and(session('files_sort_direction'))->toBe('asc');

    // 2. User navigates away to dashboard and returns to files index (no query string)
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

    // 3. User returns to files catalog without any ?sort or ?dir query params
    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->assertSet('sortField', 'category')
        ->assertSet('sortDirection', 'asc');
});

test('authenticated user can view file detail page and manage tags', function () {
    $storage = StorageSource::create([
        'name' => 'Main PC',
        'driver' => 'local',
        'base_path' => 'C:\\Files',
    ]);

    $file = InventoryFile::create([
        'name' => 'Arsip_Penting.pdf',
        'extension' => 'pdf',
        'file_type_group' => 'document',
        'file_size' => 5000,
        'checksum_sha256' => hash('sha256', 'detail_test_content'),
    ]);

    FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'docs/arsip.pdf',
        'original_filename' => 'arsip.pdf',
    ]);

    $tag = Tag::create(['name' => 'Kepegawaian', 'slug' => 'kepegawaian', 'color' => 'blue']);

    $this->actingAs($this->user)
        ->get(route('files.show', $file->id))
        ->assertOk()
        ->assertSee('Arsip_Penting.pdf')
        ->assertSee('Main PC')
        ->assertSee('docs/arsip.pdf');

    // Test attaching and detaching tag via component
    Livewire::actingAs($this->user)
        ->test('pages::files.show', ['file' => $file])
        ->set('selectedTagId', $tag->id)
        ->call('attachTag')
        ->assertSee('Kepegawaian');

    expect($file->fresh()->tags)->toHaveCount(1);

    Livewire::actingAs($this->user)
        ->test('pages::files.show', ['file' => $file])
        ->call('detachTag', $tag->id);

    expect($file->fresh()->tags)->toHaveCount(0);
});

test('user can edit inventory file display name from catalog page without changing original filename', function () {
    $storage = StorageSource::create([
        'name' => 'Storage Alpha',
        'driver' => 'local',
        'base_path' => 'E:\\Series',
    ]);

    $file = InventoryFile::create([
        'name' => 'Alice.in.Borderland.S01.COMPLETE.JAPANESE.720p',
        'extension' => 'folder',
        'file_type_group' => 'folder',
        'is_directory' => true,
        'file_size' => 0,
        'checksum_sha256' => hash('sha256', 'alice_folder'),
    ]);

    $location = FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'Alice.in.Borderland.S01.COMPLETE.JAPANESE.720p',
        'original_filename' => 'Alice.in.Borderland.S01.COMPLETE.JAPANESE.720p',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->call('openEditNameModal', $file->id)
        ->assertSet('showEditNameModal', true)
        ->assertSet('editingFileName', 'Alice.in.Borderland.S01.COMPLETE.JAPANESE.720p')
        ->set('editingFileName', 'Alice in Borderland (Season 1)')
        ->call('saveFileName')
        ->assertHasNoErrors()
        ->assertSet('showEditNameModal', false);

    // Verify catalog display name is updated
    expect($file->fresh()->name)->toBe('Alice in Borderland (Season 1)');

    // Verify physical location and original_filename remain completely unchanged (Read-Only)
    expect($location->fresh()->original_filename)->toBe('Alice.in.Borderland.S01.COMPLETE.JAPANESE.720p')
        ->and($location->fresh()->relative_path)->toBe('Alice.in.Borderland.S01.COMPLETE.JAPANESE.720p');
});

test('user can edit inventory file display name from detail page with validation', function () {
    $file = InventoryFile::create([
        'name' => 'Original_Report_Draft.docx',
        'extension' => 'docx',
        'file_type_group' => 'document',
        'file_size' => 1024,
        'checksum_sha256' => hash('sha256', 'report_draft'),
    ]);

    // Validation test: empty name fails
    Livewire::actingAs($this->user)
        ->test('pages::files.show', ['file' => $file])
        ->call('openEditNameModal')
        ->assertSet('showEditNameModal', true)
        ->set('editingName', '')
        ->call('saveFileName')
        ->assertHasErrors(['editingName' => 'required']);

    // Successful update
    Livewire::actingAs($this->user)
        ->test('pages::files.show', ['file' => $file])
        ->set('editingName', 'Laporan Tahunan 2026')
        ->call('saveFileName')
        ->assertHasNoErrors()
        ->assertSet('showEditNameModal', false);

    expect($file->fresh()->name)->toBe('Laporan Tahunan 2026');
});

test('authenticated user can manage storage sources', function () {
    Livewire::actingAs($this->user)
        ->test('pages::storage-sources.index')
        ->set('name', 'Server NAS 1')
        ->set('driver', 'local')
        ->set('base_path', 'D:\\Storage\\NAS')
        ->set('description', 'Penyimpanan cadangan')
        ->call('createStorageSource')
        ->assertHasNoErrors()
        ->assertSee('Server NAS 1');

    expect(StorageSource::where('name', 'Server NAS 1')->exists())->toBeTrue();
});

test('authenticated user can manage categories and tags', function () {
    Livewire::actingAs($this->user)
        ->test('pages::categories.index')
        ->set('name', 'Dokumen HRD')
        ->set('slug', 'dokumen-hrd')
        ->call('createCategory')
        ->assertHasNoErrors()
        ->assertSee('Dokumen HRD');

    expect(Category::where('slug', 'dokumen-hrd')->exists())->toBeTrue();

    Livewire::actingAs($this->user)
        ->test('pages::tags.index')
        ->set('name', 'Audit 2026')
        ->set('slug', 'audit-2026')
        ->set('color', 'green')
        ->call('createTag')
        ->assertHasNoErrors()
        ->assertSee('Audit 2026');

    expect(Tag::where('slug', 'audit-2026')->exists())->toBeTrue();
});

test('user can trigger directory scan from dedicated scanner page', function () {
    $tempDir = storage_path('framework/testing/page_scan_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);
    File::put($tempDir.'/sample.txt', 'sample content');

    $storage = StorageSource::create([
        'name' => 'Page Test Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $this->actingAs($this->user)
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSee('Pindai Direktori');

    Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->set('storageSourceId', $storage->id)
        ->set('targetPath', $tempDir)
        ->set('scanMode', 'file')
        ->set('scanRecursive', false)
        ->set('runInBackground', false)
        ->call('startScan')
        ->assertHasNoErrors()
        ->assertSee('Pemindaian Berhasil!');

    expect(InventoryFile::where('name', 'sample.txt')->exists())->toBeTrue();

    File::deleteDirectory($tempDir);
});

test('user can trigger folder-level scan and assign category and tags from scanner page', function () {
    $tempDir = storage_path('framework/testing/tv_series_scan_'.uniqid());
    $seriesFolder = $tempDir.'/Westworld';
    File::makeDirectory($seriesFolder, 0755, true);
    File::put($seriesFolder.'/S01E01.mkv', 'dummy mkv video');

    $storage = StorageSource::create([
        'name' => 'E Drive',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $existingCategory = Category::create(['name' => 'TV Shows', 'slug' => 'tv-shows']);
    $existingTag = Tag::create(['name' => 'Serial Barat', 'slug' => 'serial-barat', 'color' => 'purple']);

    Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->set('storageSourceId', $storage->id)
        ->set('targetPath', $tempDir)
        ->set('scanMode', 'folder')
        ->set('runInBackground', false)
        // Select category
        ->set('categoryId', $existingCategory->id)
        // Select existing tag
        ->call('toggleScanTag', $existingTag->id)
        // Create new tag inline
        ->set('newTagName', 'Sci-Fi')
        ->call('createAndAttachScanTag')
        ->call('startScan')
        ->assertHasNoErrors()
        ->assertSee('Pemindaian Berhasil!');

    $file = InventoryFile::where('name', 'Westworld')->first();
    expect($file)->not->toBeNull()
        ->and($file->is_directory)->toBeTrue()
        ->and($file->category_id)->toBe($existingCategory->id)
        ->and($file->tags)->toHaveCount(2)
        ->and($file->tags->pluck('name')->all())->toContain('Serial Barat', 'Sci-Fi')
        ->and(InventoryFile::where('name', 'S01E01.mkv')->exists())->toBeFalse();

    File::deleteDirectory($tempDir);
});

test('user can trigger scan with specific extension filter from scanner page', function () {
    $tempDir = storage_path('framework/testing/ext_filter_scan_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);
    File::put($tempDir.'/video1.mkv', 'video stream');
    File::put($tempDir.'/sub.srt', 'subtitle text');
    File::put($tempDir.'/notes.txt', 'notes');

    $storage = StorageSource::create([
        'name' => 'Media Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->set('storageSourceId', $storage->id)
        ->set('targetPath', $tempDir)
        ->set('scanMode', 'file')
        ->set('scanExtensions', 'mkv, srt')
        ->set('runInBackground', false)
        ->call('startScan')
        ->assertHasNoErrors()
        ->assertSee('Pemindaian Berhasil!');

    expect(InventoryFile::where('name', 'video1.mkv')->exists())->toBeTrue()
        ->and(InventoryFile::where('name', 'sub.srt')->exists())->toBeTrue()
        ->and(InventoryFile::where('name', 'notes.txt')->exists())->toBeFalse();

    File::deleteDirectory($tempDir);
});

test('user can create category inline directly from scanner page', function () {
    $storage = StorageSource::create([
        'name' => 'Dummy Storage',
        'driver' => 'local',
        'base_path' => storage_path('framework/testing'),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->set('newCategoryName', 'Dokumenter')
        ->call('createAndSelectCategory')
        ->assertHasNoErrors();

    $category = Category::where('name', 'Dokumenter')->first();
    expect($category)->not->toBeNull();
});

test('user can dispatch background scan to queue and cancel active scan', function () {
    Queue::fake([ScanDirectoryJob::class]);

    $tempDir = storage_path('framework/testing/bg_scan_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);
    File::put($tempDir.'/queued.txt', 'queued content');

    $storage = StorageSource::create([
        'name' => 'Background Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->set('storageSourceId', $storage->id)
        ->set('targetPath', $tempDir)
        ->set('scanMode', 'file')
        ->set('runInBackground', true)
        ->call('startScan')
        ->assertHasNoErrors();

    Queue::assertPushed(ScanDirectoryJob::class);

    $history = ScanHistory::where('storage_source_id', $storage->id)->first();
    expect($history)->not->toBeNull()
        ->and($history->status)->toBe('pending');

    // Test cancelling the active scan
    $component->call('cancelActiveScan')
        ->assertHasNoErrors();

    expect($history->fresh()->status)->toBe('cancelled');

    File::deleteDirectory($tempDir);
});

test('ScanDirectoryJob handles background execution and populates inventory', function () {
    $tempDir = storage_path('framework/testing/job_scan_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);
    File::put($tempDir.'/job_item.pdf', 'job item content');

    $storage = StorageSource::create([
        'name' => 'Job Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $history = ScanHistory::create([
        'storage_source_id' => $storage->id,
        'user_id' => $this->user->id,
        'scanned_path' => $tempDir,
        'is_recursive' => false,
        'scan_mode' => 'file',
        'status' => 'pending',
    ]);

    $job = new ScanDirectoryJob(
        scanHistoryId: $history->id,
        storageSourceId: $storage->id,
        targetPath: $tempDir,
        recursive: false,
        userId: $this->user->id,
        scanMode: 'file'
    );

    $job->handle(app(DirectoryScanService::class));

    expect($history->fresh()->status)->toBe('completed')
        ->and($history->fresh()->files_found)->toBe(1)
        ->and($history->fresh()->files_added)->toBe(1)
        ->and(InventoryFile::where('name', 'job_item.pdf')->exists())->toBeTrue();

    File::deleteDirectory($tempDir);
});

test('start queue worker button is only visible when jobs or pending scans exist', function () {
    // When idle (no pending jobs or pending scans)
    Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->assertDontSee('Start Queue Worker');

    // When there is a pending scan
    $storage = StorageSource::create([
        'name' => 'Idle Storage',
        'driver' => 'local',
        'base_path' => storage_path('framework/testing'),
    ]);

    ScanHistory::create([
        'storage_source_id' => $storage->id,
        'user_id' => $this->user->id,
        'scanned_path' => storage_path('framework/testing'),
        'is_recursive' => false,
        'scan_mode' => 'file',
        'status' => 'pending',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->assertSee('Start Queue Worker');
});

test('toasts are dispatched when starting scan and when scan completes', function () {
    Queue::fake([ScanDirectoryJob::class]);

    $tempDir = storage_path('framework/testing/toast_test_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);

    $storage = StorageSource::create([
        'name' => 'Toast Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $test = Livewire::actingAs($this->user)
        ->test('pages::scanner.index')
        ->set('storageSourceId', $storage->id)
        ->set('targetPath', $tempDir)
        ->set('runInBackground', true)
        ->call('startScan')
        ->assertDispatched('toast-show');

    $history = ScanHistory::where('storage_source_id', $storage->id)->first();
    expect($history)->not->toBeNull();

    // Mark the history as completed and simulate the next render/poll cycle
    $history->update([
        'status' => 'completed',
        'files_found' => 5,
        'files_added' => 5,
        'duration_seconds' => 1.25,
    ]);

    $test->set('activeScanId', $history->id)
        ->assertDispatched('toast-show');

    File::deleteDirectory($tempDir);
});

test('user can delete a file from catalog via files index page without deleting physical file', function () {
    $tempDir = storage_path('framework/testing/delete_test_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);
    $realFilePath = $tempDir.'/secret_document.pdf';
    File::put($realFilePath, 'important pdf content');

    $storage = StorageSource::create([
        'name' => 'Test Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $file = InventoryFile::create([
        'name' => 'secret_document.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_type_group' => 'document',
        'file_size' => 1024,
        'checksum_sha256' => hash_file('sha256', $realFilePath),
    ]);

    $tag = Tag::create(['name' => 'Important', 'slug' => 'important', 'color' => 'red']);
    $file->tags()->attach($tag->id);

    $location = FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'secret_document.pdf',
        'original_filename' => 'secret_document.pdf',
        'is_accessible' => true,
    ]);

    // Test opening confirmation modal and calling delete
    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->assertSee('secret_document.pdf')
        ->call('confirmDelete', $file->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingFileId', $file->id)
        ->assertSee('Strictly Read-Only Guarantee')
        ->call('deleteFile')
        ->assertSet('showDeleteModal', false)
        ->assertDispatched('toast-show');

    // Database record should be removed
    expect(InventoryFile::find($file->id))->toBeNull()
        ->and(FileLocation::find($location->id))->toBeNull()
        ->and(DB::table('file_tag')->where('inventory_file_id', $file->id)->count())->toBe(0);

    // CRITICAL READ-ONLY CHECK: Physical file on disk must STILL exist untouched!
    expect(File::exists($realFilePath))->toBeTrue()
        ->and(File::get($realFilePath))->toBe('important pdf content');

    File::deleteDirectory($tempDir);
});

test('user can delete a file from catalog via file detail page without deleting physical file', function () {
    $tempDir = storage_path('framework/testing/delete_show_test_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);
    $realFilePath = $tempDir.'/another_document.pdf';
    File::put($realFilePath, 'another document content');

    $storage = StorageSource::create([
        'name' => 'Detail Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $file = InventoryFile::create([
        'name' => 'another_document.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_type_group' => 'document',
        'file_size' => 2048,
        'checksum_sha256' => hash_file('sha256', $realFilePath),
    ]);

    FileLocation::create([
        'inventory_file_id' => $file->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'another_document.pdf',
        'original_filename' => 'another_document.pdf',
        'is_accessible' => true,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::files.show', ['file' => $file])
        ->assertSee('Hapus')
        ->set('showDeleteModal', true)
        ->assertSee('Strictly Read-Only Guarantee')
        ->call('deleteFile')
        ->assertRedirect(route('files.index'))
        ->assertSessionHas('status');

    // Database record should be removed
    expect(InventoryFile::find($file->id))->toBeNull();

    // CRITICAL READ-ONLY CHECK: Physical file on disk must STILL exist untouched!
    expect(File::exists($realFilePath))->toBeTrue()
        ->and(File::get($realFilePath))->toBe('another document content');

    File::deleteDirectory($tempDir);
});

test('user can select files and bulk change categories from catalog page', function () {
    $cat1 = Category::create(['name' => 'Dokumen Lama', 'slug' => 'dokumen-lama']);
    $cat2 = Category::create(['name' => 'Arsip Resmi', 'slug' => 'arsip-resmi']);

    $file1 = InventoryFile::create([
        'category_id' => $cat1->id,
        'name' => 'bulk_doc1.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_type_group' => 'document',
        'file_size' => 1024,
        'checksum_sha256' => hash('sha256', 'bulk_doc1'),
    ]);

    $file2 = InventoryFile::create([
        'category_id' => null,
        'name' => 'bulk_doc2.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_type_group' => 'document',
        'file_size' => 2048,
        'checksum_sha256' => hash('sha256', 'bulk_doc2'),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->assertDontSee('file terpilih')
        ->set('selectedFiles', [$file1->id, $file2->id])
        ->assertSee('2 file terpilih')
        ->assertSee('Hapus Semua Terpilih')
        ->assertSee('wire:model.live="bulkCategoryId"', escape: false)
        ->call('applyBulkCategory')
        ->assertDispatched('toast-show')
        ->set('bulkCategoryId', $cat2->id)
        ->call('applyBulkCategory')
        ->assertDispatched('toast-show');

    expect($file1->fresh()->category_id)->toBe($cat2->id)
        ->and($file2->fresh()->category_id)->toBe($cat2->id);

    // Test changing to none (Tanpa Kategori)
    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->set('selectedFiles', [$file1->id])
        ->set('bulkCategoryId', 'none')
        ->call('applyBulkCategory');

    expect($file1->fresh()->category_id)->toBeNull();
});

test('user can select all files using header checkbox and bulk delete from catalog without deleting physical files', function () {
    $tempDir = storage_path('framework/testing/bulk_delete_'.uniqid());
    File::makeDirectory($tempDir, 0755, true);
    $real1 = $tempDir.'/keep1.txt';
    $real2 = $tempDir.'/keep2.txt';
    File::put($real1, 'keep real file 1');
    File::put($real2, 'keep real file 2');

    $storage = StorageSource::create([
        'name' => 'Bulk Storage',
        'driver' => 'local',
        'base_path' => $tempDir,
    ]);

    $f1 = InventoryFile::create([
        'name' => 'keep1.txt',
        'extension' => 'txt',
        'mime_type' => 'text/plain',
        'file_type_group' => 'document',
        'file_size' => 15,
        'checksum_sha256' => hash_file('sha256', $real1),
    ]);

    $f2 = InventoryFile::create([
        'name' => 'keep2.txt',
        'extension' => 'txt',
        'mime_type' => 'text/plain',
        'file_type_group' => 'document',
        'file_size' => 15,
        'checksum_sha256' => hash_file('sha256', $real2),
    ]);

    FileLocation::create([
        'inventory_file_id' => $f1->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'keep1.txt',
        'original_filename' => 'keep1.txt',
        'is_accessible' => true,
    ]);

    FileLocation::create([
        'inventory_file_id' => $f2->id,
        'storage_source_id' => $storage->id,
        'relative_path' => 'keep2.txt',
        'original_filename' => 'keep2.txt',
        'is_accessible' => true,
    ]);

    // Test select all & bulk delete
    Livewire::actingAs($this->user)
        ->test('pages::files.index')
        ->set('selectAll', true)
        ->assertSet('selectAll', true)
        ->call('confirmBulkDelete')
        ->assertSet('showBulkDeleteModal', true)
        ->assertSee('Strictly Read-Only Guarantee')
        ->call('deleteBulkFiles')
        ->assertSet('showBulkDeleteModal', false)
        ->assertDispatched('toast-show');

    // Both files should be removed from database
    expect(InventoryFile::find($f1->id))->toBeNull()
        ->and(InventoryFile::find($f2->id))->toBeNull();

    // CRITICAL READ-ONLY CHECK: Physical original files on disk must STILL exist!
    expect(File::exists($real1))->toBeTrue()
        ->and(File::get($real1))->toBe('keep real file 1')
        ->and(File::exists($real2))->toBeTrue()
        ->and(File::get($real2))->toBe('keep real file 2');

    File::deleteDirectory($tempDir);
});
