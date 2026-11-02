<?php

use App\DTOs\ScanResult;
use App\Jobs\ScanDirectoryJob;
use App\Models\Category;
use App\Models\ScanHistory;
use App\Models\StorageSource;
use App\Models\Tag;
use App\Services\Queue\QueueWorkerService;
use App\Services\Scanner\DirectoryScanService;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pindai Direktori')] class extends Component
{
    public string $storageSourceId = '';

    public string $targetPath = '';

    public string $scanMode = 'folder'; // folder or file

    public string $scanExtensions = '';

    public bool $scanRecursive = true;

    public bool $runInBackground = true;

    public ?string $categoryId = null;

    public string $newCategoryName = '';

    /** @var array<string> */
    public array $scanTagIds = [];

    public string $newTagName = '';

    public ?array $scanReport = null;

    public ?string $scanError = null;

    public ?string $activeScanId = null;

    public bool $hasNotifiedRunning = false;

    public function mount(): void
    {
        $first = StorageSource::where('is_active', true)->first();
        if ($first) {
            $this->storageSourceId = $first->id;
            $this->targetPath = $first->base_path;
        }
    }

    public function updatedStorageSourceId(string $value): void
    {
        $storage = StorageSource::find($value);
        if ($storage) {
            $this->targetPath = $storage->base_path;
        }
    }

    public function toggleScanTag(string $tagId): void
    {
        if (in_array($tagId, $this->scanTagIds, true)) {
            $this->scanTagIds = array_values(array_diff($this->scanTagIds, [$tagId]));
        } else {
            $this->scanTagIds[] = $tagId;
        }
    }

    public function createAndAttachScanTag(): void
    {
        $name = trim($this->newTagName);
        if ($name === '') {
            return;
        }

        $slug = Str::slug($name);
        $tag = Tag::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'color' => 'blue']
        );

        if (! in_array($tag->id, $this->scanTagIds, true)) {
            $this->scanTagIds[] = $tag->id;
        }

        $this->newTagName = '';
    }

    public function createAndSelectCategory(): void
    {
        $name = trim($this->newCategoryName);
        if ($name === '') {
            return;
        }

        $slug = Str::slug($name);
        $cat = Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name]
        );

        $this->categoryId = $cat->id;
        $this->newCategoryName = '';
    }

    public function startScan(DirectoryScanService $scanner, QueueWorkerService $workerService): void
    {
        $this->validate([
            'storageSourceId' => 'required|exists:storage_sources,id',
            'targetPath' => 'required|string|min:2',
            'scanMode' => 'required|in:file,folder',
            'categoryId' => 'nullable|exists:categories,id',
        ], [
            'storageSourceId.required' => 'Pilih salah satu sumber penyimpanan.',
            'targetPath.required' => 'Masukkan path direktori yang ingin dipindai.',
        ]);

        $this->scanReport = null;
        $this->scanError = null;

        $storage = StorageSource::findOrFail($this->storageSourceId);

        $allowedExts = ! empty(trim($this->scanExtensions))
            ? array_map('trim', explode(',', $this->scanExtensions))
            : [];

        if ($this->runInBackground) {
            $cleanExtensions = array_values(array_unique(array_filter(array_map(
                fn (string $e) => strtolower(ltrim(trim($e), '.')),
                $allowedExts
            ))));
            $extString = ! empty($cleanExtensions) ? implode(', ', $cleanExtensions) : null;

            $history = ScanHistory::create([
                'storage_source_id' => $storage->id,
                'user_id' => auth()->id(),
                'scanned_path' => $this->targetPath,
                'current_path' => $this->targetPath,
                'is_recursive' => $this->scanRecursive,
                'scan_mode' => $this->scanMode,
                'allowed_extensions' => $extString,
                'status' => 'pending',
            ]);

            ScanDirectoryJob::dispatch(
                scanHistoryId: $history->id,
                storageSourceId: $storage->id,
                targetPath: $this->targetPath,
                recursive: $this->scanRecursive,
                userId: auth()->id(),
                scanMode: $this->scanMode,
                allowedExtensions: $allowedExts,
                tagIds: $this->scanTagIds,
                categoryId: $this->categoryId ?: null
            );

            // Automatically spawn background worker so no terminal/cron is needed!
            $workerService->startBackgroundWorker();

            $this->activeScanId = $history->id;
            $this->hasNotifiedRunning = true;

            Flux::toast(
                text: __('Pemindaian telah dimasukkan ke antrean dan sedang diproses.'),
                heading: __('Pemindaian Berjalan')
            );
        } else {
            // Synchronous scan execution
            try {
                $result = $scanner->scanDirectory(
                    source: $storage,
                    targetPath: $this->targetPath,
                    recursive: $this->scanRecursive,
                    userId: auth()->id(),
                    scanMode: $this->scanMode,
                    allowedExtensions: $allowedExts,
                    tagIds: $this->scanTagIds,
                    categoryId: $this->categoryId ?: null
                );

                $this->scanReport = $result->toArray();

                Flux::toast(
                    variant: 'success',
                    heading: __('Pemindaian Selesai'),
                    text: __('Berhasil memindai :found item (+:added baru).', [
                        'found' => $result->filesFound,
                        'added' => $result->filesAdded,
                    ])
                );
            } catch (\Throwable $e) {
                $this->scanError = $e->getMessage();

                Flux::toast(
                    variant: 'danger',
                    heading: __('Pemindaian Gagal'),
                    text: $e->getMessage()
                );
            }
        }
    }

    public function cancelActiveScan(DirectoryScanService $scanner): void
    {
        if ($this->activeScanId) {
            $scanner->cancelScan($this->activeScanId);
            Flux::toast(
                variant: 'warning',
                heading: __('Menghentikan Pemindaian'),
                text: __('Permintaan pembatalan pemindaian telah dikirim.')
            );
            $this->activeScanId = null;
            $this->hasNotifiedRunning = false;
        }
    }

    public function cancelScanById(string $historyId, DirectoryScanService $scanner): void
    {
        $scanner->cancelScan($historyId);
        Flux::toast(
            variant: 'warning',
            heading: __('Menghentikan Pemindaian'),
            text: __('Pemindaian telah dibatalkan.')
        );
        if ($this->activeScanId === $historyId) {
            $this->activeScanId = null;
            $this->hasNotifiedRunning = false;
        }
    }

    public function startWorker(QueueWorkerService $workerService): void
    {
        $workerService->startBackgroundWorker();
        Flux::toast(
            variant: 'success',
            heading: __('Queue Worker Dimulai'),
            text: __('Worker antrean latar belakang telah dijalankan.')
        );
    }

    public function processQueueNow(QueueWorkerService $workerService): void
    {
        $workerService->processQueueImmediately();
        Flux::toast(
            variant: 'success',
            heading: __('Antrean Diproses'),
            text: __('Pekerjaan dalam antrean telah selesai diproses.')
        );
    }

    public function resetScan(): void
    {
        $this->scanReport = null;
        $this->scanError = null;
        $this->activeScanId = null;
        $this->hasNotifiedRunning = false;
    }

    public function with(QueueWorkerService $workerService): array
    {
        $storages = StorageSource::where('is_active', true)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        $activeScan = $workerService->getActiveScan();
        $activeProgress = null;

        if ($activeScan) {
            $this->activeScanId = $activeScan->id;
            $activeProgress = $workerService->getActiveProgress($activeScan);

            // Toast if seeing active running scan for the first time
            if (! $this->hasNotifiedRunning) {
                Flux::toast(
                    text: __('Terdapat proses pemindaian yang sedang berjalan di background.'),
                    heading: __('Pemindaian Aktif')
                );
                $this->hasNotifiedRunning = true;
            }
        } elseif ($this->activeScanId) {
            // Check completed history
            $completedHistory = ScanHistory::find($this->activeScanId);
            if ($completedHistory) {
                if ($completedHistory->status === 'completed') {
                    $this->scanReport = $completedHistory->toArray();
                    Flux::toast(
                        variant: 'success',
                        heading: __('Pemindaian Selesai'),
                        text: __('Pemindaian selesai! Ditemukan :found item (+:added baru) dalam :sec detik.', [
                            'found' => $completedHistory->files_found,
                            'added' => $completedHistory->files_added,
                            'sec' => $completedHistory->duration_seconds,
                        ])
                    );
                } elseif ($completedHistory->status === 'cancelled') {
                    Flux::toast(
                        variant: 'warning',
                        heading: __('Pemindaian Dibatalkan'),
                        text: __('Proses pemindaian direktori telah dihentikan.')
                    );
                } elseif ($completedHistory->status === 'failed') {
                    $this->scanError = $completedHistory->error_message;
                    Flux::toast(
                        variant: 'danger',
                        heading: __('Pemindaian Gagal'),
                        text: $completedHistory->error_message ?? __('Terjadi kegagalan pemindaian.')
                    );
                }
            }
            $this->activeScanId = null;
            $this->hasNotifiedRunning = false;
        }

        $pendingJobsCount = $workerService->getPendingJobsCount();

        $recentScans = ScanHistory::with('storageSource')
            ->latest()
            ->limit(8)
            ->get();

        return [
            'storages' => $storages,
            'categories' => $categories,
            'tags' => $tags,
            'recentScans' => $recentScans,
            'activeScan' => $activeScan,
            'activeProgress' => $activeProgress,
            'pendingJobsCount' => $pendingJobsCount,
        ];
    }
};
?>

<div class="flex flex-col gap-6 max-w-5xl mx-auto" @if($activeScan) wire:poll.1500ms @endif>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Pindai Direktori') }}</flux:heading>
            <flux:subheading>{{ __('Inventarisasi file atau folder dari storage Anda ke katalog sistem tanpa memodifikasi file asli.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button icon="document-duplicate" :href="route('files.index')" wire:navigate>
                {{ __('Katalog File') }}
            </flux:button>
        </div>
    </div>

    <!-- Queue & Worker Control Bar (HANYA tampil jika ada antrean yang membutuhkan worker untuk dijalankan) -->
    @if($pendingJobsCount > 0 || ($activeScan && $activeScan->status === 'pending'))
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs">
            <div class="flex items-center gap-2.5">
                <div class="flex size-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
                    <flux:icon icon="bolt" class="size-4" />
                </div>
                <div>
                    <span class="font-semibold text-amber-950 dark:text-amber-100">{{ __('Pekerjaan Antrean Menunggu:') }}</span>
                    <span class="text-amber-800 dark:text-amber-200">
                        @if($pendingJobsCount > 0)
                            <strong class="font-bold">{{ $pendingJobsCount }} job</strong> {{ __('menunggu diproses.') }}
                        @else
                            {{ __('Pemindaian sedang menunggu giliran.') }}
                        @endif
                        {{ __('Klik tombol untuk memulai worker jika belum aktif di sistem Anda.') }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <flux:button size="xs" variant="primary" icon="play" wire:click="startWorker" title="{{ __('Jalankan worker di background tanpa terminal') }}">
                    {{ __('Start Queue Worker') }}
                </flux:button>
                <flux:button size="xs" icon="arrow-path" wire:click="processQueueNow" title="{{ __('Proses antrean langsung saat ini juga') }}">
                    {{ __('Proses Sekarang') }}
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Active Scan Real-Time Progress View -->
    @if($activeScan)
        <flux:card class="p-6 border-blue-300 dark:border-blue-700/80 bg-blue-50/40 dark:bg-blue-950/20 space-y-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-blue-200 dark:border-blue-800/60">
                <div class="flex items-center gap-3">
                    <div class="relative flex size-10 items-center justify-center rounded-xl bg-blue-600 text-white font-bold shadow-xs">
                        <flux:icon icon="arrow-path" class="size-5 animate-spin" />
                        <span class="absolute -top-1 -right-1 size-3 rounded-full bg-emerald-400 border-2 border-white dark:border-zinc-900 animate-ping"></span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg" class="text-blue-950 dark:text-blue-100">
                                {{ $activeScan->status === 'pending' ? __('Menunggu Giliran Antrean...') : __('Sedang Memindai Direktori...') }}
                            </flux:heading>
                            <flux:badge size="sm" :color="$activeScan->status === 'pending' ? 'amber' : 'blue'">
                                {{ strtoupper($activeScan->status) }}
                            </flux:badge>
                        </div>
                        <flux:text class="text-xs text-blue-700 dark:text-blue-300">
                            {{ __('Storage:') }} <strong>{{ $activeScan->storageSource?->name }}</strong> &bull;
                            {{ __('Mode:') }} <strong>{{ $activeScan->scan_mode === 'folder' ? 'Level Folder' : 'Level File' }}</strong>
                        </flux:text>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button variant="danger" icon="stop" wire:click="cancelActiveScan" wire:loading.attr="disabled">
                        {{ __('Hentikan Pemindaian') }}
                    </flux:button>
                </div>
            </div>

            <!-- Current Directory Being Scanned (Live View) -->
            <div class="rounded-xl bg-zinc-900 text-zinc-100 p-3.5 font-mono text-xs space-y-1.5 shadow-inner">
                <div class="flex items-center justify-between text-zinc-400 text-[11px]">
                    <div class="flex items-center gap-2">
                        <span class="inline-block size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>{{ __('LOKASI / DIREKTORI AKTIF YANG SEDANG DIPINDAI:') }}</span>
                    </div>
                    <span class="text-zinc-500">{{ $activeProgress['duration_seconds'] ?? 0 }}s</span>
                </div>
                <div class="text-emerald-300 font-semibold truncate text-sm" title="{{ $activeProgress['current_path'] ?? $activeScan->scanned_path }}">
                    {{ $activeProgress['current_path'] ?? $activeScan->scanned_path }}
                </div>
            </div>

            <!-- Live Counters Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                <div class="p-3 rounded-lg bg-white/90 dark:bg-zinc-800/80 border border-blue-100 dark:border-blue-900/40">
                    <div class="text-xs text-zinc-500">{{ __('Item Ditemukan') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                        {{ $activeProgress['files_found'] ?? $activeScan->files_found }}
                    </div>
                </div>
                <div class="p-3 rounded-lg bg-white/90 dark:bg-zinc-800/80 border border-blue-100 dark:border-blue-900/40">
                    <div class="text-xs text-green-600 font-medium">{{ __('Item Baru') }}</div>
                    <div class="text-xl font-bold text-green-600 mt-0.5">
                        +{{ $activeProgress['files_added'] ?? $activeScan->files_added }}
                    </div>
                </div>
                <div class="p-3 rounded-lg bg-white/90 dark:bg-zinc-800/80 border border-blue-100 dark:border-blue-900/40">
                    <div class="text-xs text-blue-600 font-medium">{{ __('Lokasi Baru') }}</div>
                    <div class="text-xl font-bold text-blue-600 mt-0.5">
                        +{{ $activeProgress['locations_added'] ?? $activeScan->locations_added }}
                    </div>
                </div>
                <div class="p-3 rounded-lg bg-white/90 dark:bg-zinc-800/80 border border-blue-100 dark:border-blue-900/40">
                    <div class="text-xs text-zinc-500">{{ __('Durasi Berjalan') }}</div>
                    <div class="text-xl font-bold text-zinc-700 dark:text-zinc-300 mt-0.5 font-mono">
                        {{ $activeProgress['duration_seconds'] ?? $activeScan->duration_seconds }}s
                    </div>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Error Alert -->
    @if($scanError)
        <div class="rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 p-4 text-sm text-red-700 dark:text-red-300 space-y-1">
            <div class="font-semibold flex items-center gap-2">
                <flux:icon icon="exclamation-circle" class="size-5" />
                <span>{{ __('Proses Pemindaian Gagal') }}</span>
            </div>
            <div>{{ $scanError }}</div>
        </div>
    @endif

    <!-- Success Result Banner -->
    @if($scanReport)
        <flux:card class="p-6 bg-green-50/70 dark:bg-green-950/30 border-green-200 dark:border-green-800 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-green-200 dark:border-green-800/60">
                <div class="flex items-center gap-2.5 text-green-900 dark:text-green-100">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-green-600 text-white font-bold">
                        <flux:icon icon="check" class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-green-900 dark:text-green-100">{{ __('Pemindaian Berhasil!') }}</flux:heading>
                        <flux:text class="text-xs text-green-700 dark:text-green-300">{{ __('Item inventaris telah diperbarui di database.') }}</flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button size="sm" wire:click="resetScan">
                        {{ __('Pindai Direktori Lain') }}
                    </flux:button>
                    <flux:button size="sm" variant="primary" :href="route('files.index')" wire:navigate>
                        {{ __('Lihat di Katalog File') }}
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
                <div class="p-3 rounded-lg bg-white/80 dark:bg-zinc-800/80 border border-green-100 dark:border-green-900/40">
                    <div class="text-xs text-zinc-500">{{ __('Ditemukan') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $scanReport['files_found'] }}</div>
                </div>
                <div class="p-3 rounded-lg bg-white/80 dark:bg-zinc-800/80 border border-green-100 dark:border-green-900/40">
                    <div class="text-xs text-green-600 font-medium">{{ __('Item Baru') }}</div>
                    <div class="text-xl font-bold text-green-600 mt-0.5">+{{ $scanReport['files_added'] }}</div>
                </div>
                <div class="p-3 rounded-lg bg-white/80 dark:bg-zinc-800/80 border border-green-100 dark:border-green-900/40">
                    <div class="text-xs text-blue-600 font-medium">{{ __('Lokasi Baru') }}</div>
                    <div class="text-xl font-bold text-blue-600 mt-0.5">+{{ $scanReport['locations_added'] }}</div>
                </div>
                <div class="p-3 rounded-lg bg-white/80 dark:bg-zinc-800/80 border border-green-100 dark:border-green-900/40">
                    <div class="text-xs text-zinc-500">{{ __('Tidak Berubah') }}</div>
                    <div class="text-xl font-bold text-zinc-600 dark:text-zinc-300 mt-0.5">{{ $scanReport['files_unmodified'] }}</div>
                </div>
                <div class="p-3 rounded-lg bg-white/80 dark:bg-zinc-800/80 border border-green-100 dark:border-green-900/40">
                    <div class="text-xs text-zinc-500">{{ __('Durasi') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $scanReport['duration_seconds'] }}s</div>
                </div>
                <div class="p-3 rounded-lg bg-white/80 dark:bg-zinc-800/80 border border-green-100 dark:border-green-900/40">
                    <div class="text-xs text-red-500">{{ __('Gagal') }}</div>
                    <div class="text-xl font-bold text-red-600 mt-0.5">{{ $scanReport['files_failed'] }}</div>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Main Scanner Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Column (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">
            <flux:card class="p-6 space-y-5">
                <form wire:submit="startScan" class="space-y-5">
                    <!-- 1. Storage Source -->
                    <div class="space-y-1.5">
                        <flux:label>{{ __('Sumber Penyimpanan (Storage Source)') }}</flux:label>
                        <flux:select wire:model.live="storageSourceId" required>
                            <option value="">{{ __('-- Pilih Storage Source --') }}</option>
                            @foreach($storages as $st)
                                <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->driver }} - {{ $st->base_path }})</option>
                            @endforeach
                        </flux:select>
                        <flux:text class="text-xs text-zinc-400">
                            {{ __('Pilih penyimpanan dasar tempat direktori berada.') }}
                        </flux:text>
                    </div>

                    <!-- 2. Path Directory -->
                    <div class="space-y-1.5">
                        <flux:label>{{ __('Path Direktori yang Ingin Dipindai') }}</flux:label>
                        <flux:input
                            wire:model="targetPath"
                            placeholder="Contoh: E:\TV Series atau D:\Dokumen\Arsip 2026"
                            required
                        />
                        <flux:text class="text-xs text-zinc-400">
                            {{ __('Masukkan path absolut folder di drive lokal/NAS.') }}
                        </flux:text>
                    </div>

                    <!-- 3. Scan Mode Selector -->
                    <div class="space-y-1.5">
                        <flux:label>{{ __('Tingkat Inventarisasi (Mode Pemindaian)') }}</flux:label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $scanMode === 'folder' ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-900/20 text-amber-900 dark:text-amber-100' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                <input type="radio" wire:model.live="scanMode" value="folder" class="mt-0.5 text-amber-600 focus:ring-amber-500">
                                <div class="space-y-0.5">
                                    <div class="text-sm font-semibold flex items-center gap-1.5">
                                        <flux:icon icon="folder" class="size-4 text-amber-500" />
                                        <span>{{ __('Level Folder Saja') }}</span>
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                        {{ __('Hanya mencatat sub-folder langsung di dalamnya (cocok untuk Serial TV, Album Musik, atau Folder Proyek).') }}
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $scanMode === 'file' ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20 text-blue-900 dark:text-blue-100' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                <input type="radio" wire:model.live="scanMode" value="file" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div class="space-y-0.5">
                                    <div class="text-sm font-semibold flex items-center gap-1.5">
                                        <flux:icon icon="document" class="size-4 text-blue-500" />
                                        <span>{{ __('Level File') }}</span>
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                        {{ __('Mencatat setiap file individual di dalam folder beserta ukuran dan hash SHA-256.') }}
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 4. Mode-specific Options -->
                    @if($scanMode === 'file')
                        <div class="space-y-3 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700">
                            <!-- Extension Filter -->
                            <div class="space-y-1">
                                <flux:label>{{ __('Filter Ekstensi Tertentu (Opsional)') }}</flux:label>
                                <flux:input
                                    wire:model="scanExtensions"
                                    placeholder="Contoh: mkv, mp4, avi atau pdf, docx"
                                />
                                <flux:text class="text-xs text-zinc-400">
                                    {{ __('Pisahkan dengan koma. Kosongkan jika ingin memindai semua ekstensi.') }}
                                </flux:text>
                            </div>

                            <!-- Recursive Checkbox -->
                            <div class="pt-1">
                                <flux:checkbox
                                    wire:model="scanRecursive"
                                    :label="__('Pindai seluruh sub-folder secara mendalam (rekursif)')"
                                />
                            </div>
                        </div>
                    @endif

                    <!-- 5. Category Selection -->
                    <div class="space-y-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:label>{{ __('Kategori Hasil Scan (Opsional)') }}</flux:label>
                        <flux:text class="text-xs text-zinc-400">
                            {{ __('Semua item/folder yang ditemukan pada scan ini akan otomatis dimasukkan ke dalam kategori ini.') }}
                        </flux:text>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                            <div>
                                <flux:select wire:model="categoryId">
                                    <option value="">{{ __('-- Tanpa Kategori --') }}</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:input
                                    wire:model="newCategoryName"
                                    placeholder="Buat kategori baru..."
                                    class="flex-1"
                                    wire:keydown.enter.prevent="createAndSelectCategory"
                                />
                                <flux:button type="button" size="sm" wire:click="createAndSelectCategory">
                                    {{ __('+ Tambah') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Tag Selection -->
                    <div class="space-y-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:label>{{ __('Tautkan Tag Otomatis (Opsional)') }}</flux:label>
                        <flux:text class="text-xs text-zinc-400">
                            {{ __('Pilih tag untuk langsung disematkan ke hasil pemindaian.') }}
                        </flux:text>

                        @if($tags->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700">
                                @foreach($tags as $t)
                                    @php $isSelected = in_array($t->id, $scanTagIds, true); @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleScanTag('{{ $t->id }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition cursor-pointer {{ $isSelected ? 'bg-blue-600 text-white shadow-xs' : 'bg-white dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-600 hover:border-blue-400' }}"
                                    >
                                        @if($isSelected)
                                            <flux:icon icon="check" class="size-3" />
                                        @endif
                                        {{ $t->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-center gap-2">
                            <flux:input
                                wire:model="newTagName"
                                placeholder="Buat tag baru (misal: TV Series, 2026, Anime)..."
                                class="flex-1"
                                wire:keydown.enter.prevent="createAndAttachScanTag"
                            />
                            <flux:button type="button" size="sm" wire:click="createAndAttachScanTag">
                                {{ __('+ Tambah Tag') }}
                            </flux:button>
                        </div>
                    </div>

                    <!-- 7. Background Execution Checkbox -->
                    <div class="p-3.5 rounded-xl bg-blue-50/50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50">
                        <flux:checkbox
                            wire:model="runInBackground"
                            :label="__('Jalankan di background melalui antrean (Queue Job)')"
                            :description="__('Proses scan tidak akan memblokir browser Anda dan dapat dipantau progresnya secara live.')"
                        />
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" class="min-w-40" :disabled="(bool) $activeScan">
                            <span wire:loading.remove wire:target="startScan" class="flex items-center gap-1.5">
                                <flux:icon icon="magnifying-glass" class="size-4" />
                                {{ __('Mulai Pindai Direktori') }}
                            </span>
                            <span wire:loading wire:target="startScan" class="flex items-center gap-1.5">
                                <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                {{ __('Memproses...') }}
                            </span>
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>

        <!-- Sidebar / Panduan Column -->
        <div class="space-y-6">
            <flux:card class="p-5 space-y-3.5">
                <flux:heading size="md" class="flex items-center gap-2">
                    <flux:icon icon="cpu-chip" class="size-5 text-indigo-500" />
                    <span>{{ __('Queue & Background Scan') }}</span>
                </flux:heading>
                <flux:text class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    {{ __('Pemindaian dijalankan sebagai antrean di latar belakang. Anda tidak perlu menyalakan terminal atau setting cron, sistem akan memprosesnya secara otomatis.') }}
                </flux:text>
            </flux:card>

            <flux:card class="p-5 space-y-3.5">
                <flux:heading size="md" class="flex items-center gap-2">
                    <flux:icon icon="information-circle" class="size-5 text-blue-500" />
                    <span>{{ __('Kebijakan Read-Only') }}</span>
                </flux:heading>
                <flux:text class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    {{ __('Sistem inventaris ini bekerja secara mutlak Read-Only. File dan folder asli di harddisk Anda tidak akan pernah diubah, dipindahkan, diganti nama, atau dihapus.') }}
                </flux:text>
            </flux:card>

            <flux:card class="p-5 space-y-3.5">
                <flux:heading size="md" class="flex items-center gap-2">
                    <flux:icon icon="sparkles" class="size-5 text-amber-500" />
                    <span>{{ __('Tips Inventarisasi') }}</span>
                </flux:heading>
                <div class="space-y-2 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    <p>
                        <strong>{{ __('Untuk Serial TV / Film:') }}</strong><br>
                        {{ __('Gunakan Mode "Level Folder Saja" pada path induk (misal: E:\TV Series). Setiap serial akan tercatat sebagai satu item folder di katalog.') }}
                    </p>
                    <p>
                        <strong>{{ __('Untuk Dokumen / Media Campuran:') }}</strong><br>
                        {{ __('Gunakan Mode "Level File" dan isi filter ekstensi (misal: pdf, docx) untuk menyaring hanya berkas penting.') }}
                    </p>
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Recent Scans Table -->
    @if($recentScans->isNotEmpty())
        <flux:card class="p-0 overflow-hidden mt-2">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <flux:heading size="md">{{ __('Riwayat Pemindaian Terakhir') }}</flux:heading>
                <flux:button size="xs" :href="route('scan-histories.index')" wire:navigate>{{ __('Lihat Semua') }}</flux:button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium text-xs">
                        <tr>
                            <th class="py-2.5 px-4">{{ __('Waktu') }}</th>
                            <th class="py-2.5 px-4">{{ __('Storage') }}</th>
                            <th class="py-2.5 px-4">{{ __('Path') }}</th>
                            <th class="py-2.5 px-4 text-center">{{ __('Mode') }}</th>
                            <th class="py-2.5 px-4 text-center">{{ __('Status') }}</th>
                            <th class="py-2.5 px-4 text-center">{{ __('Ditemukan') }}</th>
                            <th class="py-2.5 px-4 text-center">{{ __('Baru') }}</th>
                            <th class="py-2.5 px-4 text-right">{{ __('Durasi') }}</th>
                            <th class="py-2.5 px-4 text-right">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 text-xs">
                        @foreach($recentScans as $scan)
                            <tr>
                                <td class="py-2.5 px-4 text-zinc-500 whitespace-nowrap">{{ $scan->created_at?->format('d M H:i') }}</td>
                                <td class="py-2.5 px-4 font-medium">{{ $scan->storageSource?->name ?? '-' }}</td>
                                <td class="py-2.5 px-4 font-mono truncate max-w-xs" title="{{ $scan->scanned_path }}">{{ $scan->scanned_path }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <flux:badge size="sm" :color="$scan->scan_mode === 'folder' ? 'amber' : 'zinc'">
                                        {{ $scan->scan_mode === 'folder' ? 'Folder' : 'File' }}
                                    </flux:badge>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <flux:badge size="sm" :color="match($scan->status) {
                                        'completed' => 'green',
                                        'running' => 'blue',
                                        'pending' => 'amber',
                                        'cancelled' => 'zinc',
                                        default => 'red',
                                    }">
                                        {{ match($scan->status) {
                                            'completed' => 'Selesai',
                                            'running' => 'Memindai',
                                            'pending' => 'Antrean',
                                            'cancelled' => 'Dibatalkan',
                                            default => 'Gagal',
                                        } }}
                                    </flux:badge>
                                </td>
                                <td class="py-2.5 px-4 text-center font-medium">{{ $scan->files_found }}</td>
                                <td class="py-2.5 px-4 text-center text-green-600 font-semibold">+{{ $scan->files_added }}</td>
                                <td class="py-2.5 px-4 text-right text-zinc-500 font-mono">{{ $scan->duration_seconds }}s</td>
                                <td class="py-2.5 px-4 text-right">
                                    @if(in_array($scan->status, ['running', 'pending'], true))
                                        <flux:button size="xs" variant="danger" wire:click="cancelScanById('{{ $scan->id }}')">
                                            {{ __('Hentikan') }}
                                        </flux:button>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
