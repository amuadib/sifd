<?php

use App\Models\Category;
use App\Models\InventoryFile;
use App\Models\StorageSource;
use App\Models\Tag;
use App\Services\Inventory\InventoryFileService;
use Flux\Flux;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Katalog File')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $fileType = 'all';

    #[Url(as: 'storage')]
    public string $storageSourceId = 'all';

    #[Url(as: 'cat')]
    public string $categoryId = 'all';

    #[Url(as: 'view')]
    public string $viewMode = 'table'; // table or grid

    #[Url(as: 'sort')]
    public string $sortField = 'created_at';

    #[Url(as: 'dir')]
    public string $sortDirection = 'desc';

    public bool $showEditNameModal = false;

    public string $editingFileId = '';

    public string $editingFileName = '';

    public bool $showDeleteModal = false;

    public string $deletingFileId = '';

    public string $deletingFileName = '';

    /** @var array<string> */
    public array $selectedFiles = [];

    public bool $selectAll = false;

    public bool $showBulkDeleteModal = false;

    public string $bulkCategoryId = '';

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedFiles = $this->getCurrentPageFileIds();
        } else {
            $this->selectedFiles = [];
        }
    }

    public function updatedSelectedFiles(): void
    {
        $currentPageIds = $this->getCurrentPageFileIds();
        $this->selectAll = ! empty($currentPageIds) && count(array_intersect($currentPageIds, $this->selectedFiles)) === count($currentPageIds);
    }

    public function deselectAll(): void
    {
        $this->selectedFiles = [];
        $this->selectAll = false;
        $this->bulkCategoryId = '';
    }

    public function applyBulkCategory(): void
    {
        if (empty($this->selectedFiles)) {
            return;
        }

        if ($this->bulkCategoryId === '') {
            Flux::toast(
                text: __('Silakan pilih kategori tujuan terlebih dahulu.'),
                heading: __('Kategori Belum Dipilih'),
                variant: 'warning'
            );

            return;
        }

        $categoryId = $this->bulkCategoryId === 'none' ? null : $this->bulkCategoryId;
        $count = count($this->selectedFiles);

        InventoryFile::whereIn('id', $this->selectedFiles)
            ->update(['category_id' => $categoryId]);

        $categoryName = $categoryId ? Category::find($categoryId)?->name : __('Tanpa Kategori');

        Flux::toast(
            text: __('Kategori untuk :count file berhasil diubah menjadi ":cat".', [
                'count' => $count,
                'cat' => $categoryName ?? __('Tanpa Kategori'),
            ]),
            heading: __('Kategori Diperbarui'),
            variant: 'success'
        );

        $this->bulkCategoryId = '';
        $this->selectedFiles = [];
        $this->selectAll = false;
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selectedFiles)) {
            return;
        }

        $this->showBulkDeleteModal = true;
    }

    public function deleteBulkFiles(InventoryFileService $fileService): void
    {
        if (empty($this->selectedFiles)) {
            $this->showBulkDeleteModal = false;

            return;
        }

        $count = count($this->selectedFiles);
        $files = InventoryFile::whereIn('id', $this->selectedFiles)->get();

        foreach ($files as $file) {
            $fileService->deleteFromCatalog($file);
        }

        Flux::toast(
            text: __(':count file berhasil dihapus dari katalog.', ['count' => $count]),
            heading: __('Berhasil Dihapus'),
            variant: 'success'
        );

        $this->showBulkDeleteModal = false;
        $this->selectedFiles = [];
        $this->selectAll = false;
    }

    public function confirmDelete(string $fileId): void
    {
        $file = InventoryFile::findOrFail($fileId);
        $this->deletingFileId = $file->id;
        $this->deletingFileName = $file->name;
        $this->showDeleteModal = true;
    }

    public function deleteFile(InventoryFileService $fileService): void
    {
        if ($this->deletingFileId) {
            $file = InventoryFile::find($this->deletingFileId);
            if ($file) {
                $deletedName = $this->deletingFileName;
                $fileService->deleteFromCatalog($file);

                Flux::toast(
                    text: __('Data file :name telah dihapus dari katalog.', ['name' => $deletedName]),
                    heading: __('Berhasil Dihapus'),
                    variant: 'success'
                );
            }
        }

        $this->showDeleteModal = false;
        $this->deletingFileId = '';
        $this->deletingFileName = '';
    }

    public function openEditNameModal(string $fileId): void
    {
        $file = InventoryFile::findOrFail($fileId);
        $this->editingFileId = $file->id;
        $this->editingFileName = $file->name;
        $this->showEditNameModal = true;
    }

    public function saveFileName(): void
    {
        $this->validate([
            'editingFileName' => 'required|string|min:1|max:255',
        ], [
            'editingFileName.required' => 'Nama file katalog tidak boleh kosong.',
            'editingFileName.max' => 'Nama file katalog maksimal 255 karakter.',
        ]);

        if ($this->editingFileId) {
            $file = InventoryFile::findOrFail($this->editingFileId);
            $file->update([
                'name' => trim($this->editingFileName),
            ]);
        }

        $this->showEditNameModal = false;
        $this->editingFileId = '';
        $this->editingFileName = '';
    }

    public function mount(): void
    {
        if (Session::has('status')) {
            Flux::toast(
                text: (string) Session::get('status'),
                heading: __('Berhasil Dihapus'),
                variant: 'success'
            );
        }

        $allowedSorts = ['created_at', 'name', 'file_size', 'category', 'locations_count', 'file_type_group'];

        // Restore sortField from Request > Session > Cookie
        if (request()->has('sort') && in_array(request('sort'), $allowedSorts, true)) {
            $this->sortField = (string) request('sort');
        } elseif (Session::has('files_sort_field') && in_array(Session::get('files_sort_field'), $allowedSorts, true)) {
            $this->sortField = (string) Session::get('files_sort_field');
        } elseif (Cookie::has('files_sort_field') && in_array(Cookie::get('files_sort_field'), $allowedSorts, true)) {
            $this->sortField = (string) Cookie::get('files_sort_field');
        }

        // Restore sortDirection from Request > Session > Cookie
        if (request()->has('dir') && in_array(strtolower((string) request('dir')), ['asc', 'desc'], true)) {
            $this->sortDirection = strtolower((string) request('dir'));
        } elseif (Session::has('files_sort_direction')) {
            $this->sortDirection = (string) Session::get('files_sort_direction');
        } elseif (Cookie::has('files_sort_direction')) {
            $this->sortDirection = (string) Cookie::get('files_sort_direction');
        }

        // Restore viewMode from Request > Session > Cookie
        if (request()->has('view') && in_array(request('view'), ['table', 'grid'], true)) {
            $this->viewMode = (string) request('view');
        } elseif (Session::has('files_view_mode')) {
            $this->viewMode = (string) Session::get('files_view_mode');
        } elseif (Cookie::has('files_view_mode')) {
            $this->viewMode = (string) Cookie::get('files_view_mode');
        }

        // Keep session & cookie in sync
        Session::put('files_sort_field', $this->sortField);
        Session::put('files_sort_direction', $this->sortDirection);
        Session::put('files_view_mode', $this->viewMode);

        Cookie::queue('files_sort_field', $this->sortField, 60 * 24 * 30);
        Cookie::queue('files_sort_direction', $this->sortDirection, 60 * 24 * 30);
        Cookie::queue('files_view_mode', $this->viewMode, 60 * 24 * 30);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->deselectAll();
    }

    public function updatedFileType(): void
    {
        $this->resetPage();
        $this->deselectAll();
    }

    public function updatedStorageSourceId(): void
    {
        $this->resetPage();
        $this->deselectAll();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
        $this->deselectAll();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['name', 'category', 'file_size', 'locations_count', 'created_at', 'file_type_group'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        Session::put('files_sort_field', $this->sortField);
        Session::put('files_sort_direction', $this->sortDirection);

        Cookie::queue('files_sort_field', $this->sortField, 60 * 24 * 30);
        Cookie::queue('files_sort_direction', $this->sortDirection, 60 * 24 * 30);

        $this->resetPage();
        $this->deselectAll();
    }

    public function updatedSortField(): void
    {
        Session::put('files_sort_field', $this->sortField);
        Cookie::queue('files_sort_field', $this->sortField, 60 * 24 * 30);
        $this->resetPage();
        $this->deselectAll();
    }

    public function updatedSortDirection(): void
    {
        Session::put('files_sort_direction', $this->sortDirection);
        Cookie::queue('files_sort_direction', $this->sortDirection, 60 * 24 * 30);
        $this->resetPage();
        $this->deselectAll();
    }

    public function updatedViewMode(): void
    {
        Session::put('files_view_mode', $this->viewMode);
        Cookie::queue('files_view_mode', $this->viewMode, 60 * 24 * 30);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<InventoryFile>
     */
    protected function getFilteredFilesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = InventoryFile::query()
            ->with(['category', 'tags', 'locations.storageSource']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('extension', 'like', '%'.$this->search.'%')
                    ->orWhere('checksum_sha256', 'like', $this->search.'%')
                    ->orWhereHas('locations', function ($locQ) {
                        $locQ->where('original_filename', 'like', '%'.$this->search.'%')
                            ->orWhere('relative_path', 'like', '%'.$this->search.'%');
                    })
                    ->orWhereHas('tags', function ($tagQ) {
                        $tagQ->where('name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if ($this->fileType !== 'all') {
            $query->where('file_type_group', $this->fileType);
        }

        if ($this->categoryId !== 'all') {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->storageSourceId !== 'all') {
            $query->whereHas('locations', function ($q) {
                $q->where('storage_source_id', $this->storageSourceId);
            });
        }

        $allowedSorts = ['created_at', 'name', 'file_size', 'category', 'locations_count', 'file_type_group'];
        $sort = in_array($this->sortField, $allowedSorts, true) ? $this->sortField : 'created_at';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sort === 'category') {
            $query->orderBy(
                Category::select('name')->whereColumn('categories.id', 'inventory_files.category_id'),
                $direction
            );
        } elseif ($sort === 'locations_count') {
            $query->withCount('locations')->orderBy('locations_count', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query;
    }

    /**
     * @return array<string>
     */
    protected function getCurrentPageFileIds(): array
    {
        /** @var array<string> $ids */
        $ids = $this->getFilteredFilesQuery()->paginate(15)->pluck('id')->map(fn ($id) => (string) $id)->all();

        return $ids;
    }

    public function with(): array
    {
        $files = $this->getFilteredFilesQuery()->paginate(15);

        $storages = StorageSource::where('is_active', true)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return [
            'files' => $files,
            'storages' => $storages,
            'categories' => $categories,
            'tags' => $tags,
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Katalog File Digital') }}</flux:heading>
            <flux:subheading>{{ __('Daftar seluruh file yang terinventarisasi dari berbagai direktori.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <!-- View Mode Switch -->
            <div class="flex items-center border border-zinc-200 dark:border-zinc-700 rounded-lg p-0.5 bg-zinc-50 dark:bg-zinc-900">
                <button
                    type="button"
                    wire:click="$set('viewMode', 'table')"
                    class="px-2.5 py-1 text-xs font-medium rounded-md transition {{ $viewMode === 'table' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-500 hover:text-zinc-900' }}"
                >
                    {{ __('Tabel') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('viewMode', 'grid')"
                    class="px-2.5 py-1 text-xs font-medium rounded-md transition {{ $viewMode === 'grid' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-500 hover:text-zinc-900' }}"
                >
                    {{ __('Grid') }}
                </button>
            </div>

            <flux:button variant="primary" icon="magnifying-glass" :href="route('scanner.index')" wire:navigate>
                {{ __('Pindai Direktori') }}
            </flux:button>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <flux:card class="p-4 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
            <!-- Search -->
            <div class="sm:col-span-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama file, tag, path, atau hash..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <!-- Type Filter -->
            <div>
                <flux:select wire:model.live="fileType">
                    <option value="all">{{ __('Semua Jenis') }}</option>
                    <option value="folder">📁 {{ __('Folder / Serial TV') }}</option>
                    <option value="document">{{ __('Dokumen') }}</option>
                    <option value="image">{{ __('Gambar') }}</option>
                    <option value="video">{{ __('Video') }}</option>
                    <option value="audio">{{ __('Audio') }}</option>
                    <option value="archive">{{ __('Arsip (ZIP/RAR)') }}</option>
                    <option value="code">{{ __('Source Code') }}</option>
                    <option value="other">{{ __('Lainnya') }}</option>
                </flux:select>
            </div>

            <!-- Storage Filter -->
            <div>
                <flux:select wire:model.live="storageSourceId">
                    <option value="all">{{ __('Semua Storage') }}</option>
                    @foreach($storages as $st)
                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Category Filter -->
            <div>
                <flux:select wire:model.live="categoryId">
                    <option value="all">{{ __('Semua Kategori') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </flux:card>

    <!-- Bulk Actions Floating/Sticky Bar -->
    @if(! empty($selectedFiles))
        <div class="rounded-xl border border-sky-300 dark:border-cyan-500/40 bg-sky-50/95 dark:bg-[#071e35]/95 p-3.5 shadow-md flex flex-col md:flex-row md:items-center md:justify-between gap-3 transition">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg bg-sky-600 text-white font-bold text-xs shadow-xs">
                    {{ __(':count file terpilih', ['count' => count($selectedFiles)]) }}
                </span>
                <span class="text-xs text-slate-600 dark:text-slate-300 font-medium hidden sm:inline">
                    {{ __('Pilih tindakan massal:') }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Ubah Kategori Form -->
                <div class="flex items-center gap-1.5">
                    <flux:select wire:model.live="bulkCategoryId" class="text-xs min-w-44 !py-1 !h-8">
                        <option value="">{{ __('-- Ubah Kategori ke --') }}</option>
                        <option value="none">{{ __('(Tanpa Kategori)') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button size="sm" variant="primary" wire:click="applyBulkCategory" :disabled="empty($bulkCategoryId)">
                        {{ __('Terapkan') }}
                    </flux:button>
                </div>

                <div class="h-4 w-px bg-zinc-300 dark:bg-zinc-700 mx-1 hidden sm:block"></div>

                <!-- Bulk Delete Button -->
                <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmBulkDelete">
                    {{ __('Hapus Semua Terpilih') }}
                </flux:button>

                <!-- Deselect All -->
                <flux:button size="sm" wire:click="deselectAll">
                    {{ __('Batal') }}
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Content: Table or Grid View -->
    @if($files->isEmpty())
        <flux:card class="p-12 text-center space-y-3">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400">
                <flux:icon icon="document-magnifying-glass" class="size-6" />
            </div>
            <flux:heading size="lg">{{ __('Tidak ada file ditemukan') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                {{ __('Coba ubah kata kunci pencarian, sesuaikan filter, atau lakukan pemindaian direktori baru.') }}
            </flux:text>
            <div class="pt-2">
                <flux:button variant="primary" size="sm" :href="route('scanner.index')" wire:navigate>
                    {{ __('Pindai Direktori Sekarang') }}
                </flux:button>
            </div>
        </flux:card>
    @else
        @if($viewMode === 'table')
            <!-- Table View -->
            <flux:card class="p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium text-xs">
                            <tr>
                                <th class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            wire:model.live="selectAll"
                                            class="rounded border-zinc-300 dark:border-zinc-700 text-sky-600 focus:ring-sky-500 size-4 cursor-pointer"
                                            title="{{ __('Pilih Semua File di Halaman Ini') }}"
                                        />
                                        <button
                                            type="button"
                                            wire:click="sortBy('name')"
                                            class="group inline-flex items-center gap-1.5 font-medium hover:text-zinc-900 dark:hover:text-zinc-100 transition cursor-pointer select-none {{ $sortField === 'name' ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}"
                                            title="{{ __('Urutkan berdasarkan Nama File') }}"
                                        >
                                            <span>{{ __('Nama File') }}</span>
                                            <span class="inline-flex items-center">
                                                @if($sortField === 'name')
                                                    @if($sortDirection === 'asc')
                                                        <flux:icon icon="chevron-up" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                    @else
                                                        <flux:icon icon="chevron-down" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                    @endif
                                                @else
                                                    <flux:icon icon="chevron-up-down" class="size-3.5 text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-500 transition" />
                                                @endif
                                            </span>
                                        </button>
                                    </div>
                                </th>
                                <th class="py-3 px-4">
                                    <button
                                        type="button"
                                        wire:click="sortBy('category')"
                                        class="group inline-flex items-center gap-1.5 font-medium hover:text-zinc-900 dark:hover:text-zinc-100 transition cursor-pointer select-none {{ $sortField === 'category' ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}"
                                        title="{{ __('Urutkan berdasarkan Kategori') }}"
                                    >
                                        <span>{{ __('Kategori') }}</span>
                                        <span class="inline-flex items-center">
                                            @if($sortField === 'category')
                                                @if($sortDirection === 'asc')
                                                    <flux:icon icon="chevron-up" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                @else
                                                    <flux:icon icon="chevron-down" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                @endif
                                            @else
                                                <flux:icon icon="chevron-up-down" class="size-3.5 text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-500 transition" />
                                            @endif
                                        </span>
                                    </button>
                                </th>
                                <th class="py-3 px-4">
                                    <button
                                        type="button"
                                        wire:click="sortBy('file_size')"
                                        class="group inline-flex items-center gap-1.5 font-medium hover:text-zinc-900 dark:hover:text-zinc-100 transition cursor-pointer select-none {{ $sortField === 'file_size' ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}"
                                        title="{{ __('Urutkan berdasarkan Ukuran') }}"
                                    >
                                        <span>{{ __('Ukuran') }}</span>
                                        <span class="inline-flex items-center">
                                            @if($sortField === 'file_size')
                                                @if($sortDirection === 'asc')
                                                    <flux:icon icon="chevron-up" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                @else
                                                    <flux:icon icon="chevron-down" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                @endif
                                            @else
                                                <flux:icon icon="chevron-up-down" class="size-3.5 text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-500 transition" />
                                            @endif
                                        </span>
                                    </button>
                                </th>
                                <th class="py-3 px-4">
                                    <button
                                        type="button"
                                        wire:click="sortBy('locations_count')"
                                        class="group inline-flex items-center gap-1.5 font-medium hover:text-zinc-900 dark:hover:text-zinc-100 transition cursor-pointer select-none {{ $sortField === 'locations_count' ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}"
                                        title="{{ __('Urutkan berdasarkan Lokasi') }}"
                                    >
                                        <span>{{ __('Lokasi Fisik') }}</span>
                                        <span class="inline-flex items-center">
                                            @if($sortField === 'locations_count')
                                                @if($sortDirection === 'asc')
                                                    <flux:icon icon="chevron-up" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                @else
                                                    <flux:icon icon="chevron-down" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                @endif
                                            @else
                                                <flux:icon icon="chevron-up-down" class="size-3.5 text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-500 transition" />
                                            @endif
                                        </span>
                                    </button>
                                </th>
                                <th class="py-3 px-4">{{ __('Tag') }}</th>
                                <th class="py-3 px-4 text-right">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($files as $file)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                value="{{ $file->id }}"
                                                wire:model.live="selectedFiles"
                                                class="rounded border-zinc-300 dark:border-zinc-700 text-sky-600 focus:ring-sky-500 size-4 cursor-pointer shrink-0"
                                            />
                                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $file->is_directory ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }} font-semibold uppercase text-xs">
                                                @if($file->is_directory)
                                                    <flux:icon icon="folder" class="size-5" />
                                                @elseif(strtolower($file->extension) == 'safetensors')
                                                    <flux:icon icon="light-bulb" class="size-5" />
                                                @else
                                                    {{ $file->extension }}
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ route('files.show', $file->id) }}" wire:navigate class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-blue-600 truncate block">
                                                    {{ $file->name }}
                                                </a>
                                                <span class="text-xs text-zinc-400 font-mono">
                                                    {{ Str::limit($file->checksum_sha256, 12, '...') }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($file->category)
                                            <flux:badge size="sm" color="zinc">
                                                {{ $file->category->name }}
                                            </flux:badge>
                                        @else
                                            <span class="text-xs text-zinc-400 italic">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-zinc-600 dark:text-zinc-400 font-medium">
                                        @if($file->is_directory)
                                            <span class="text-xs text-zinc-500 font-mono">
                                                {{ isset($file->metadata['item_count']) ? $file->metadata['item_count'] . ' item' : 'Folder' }}
                                            </span>
                                        @else
                                            {{ $file->formatted_size }}
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-1.5">
                                                <flux:badge size="sm" color="zinc">
                                                    {{ $file->locations->count() }} {{ __('lokasi') }}
                                                </flux:badge>
                                            </div>
                                            @if($file->locations->first())
                                                <span class="text-xs text-zinc-400 truncate max-w-xs block font-mono" title="{{ $file->locations->first()->full_path }}">
                                                    {{ $file->locations->first()->original_filename }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($file->tags as $tag)
                                                <flux:badge size="sm" :color="$tag->color ?? 'zinc'">
                                                    {{ $tag->name }}
                                                </flux:badge>
                                            @empty
                                                <span class="text-xs text-zinc-400">-</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <flux:button size="xs" icon="pencil-square" wire:click="openEditNameModal('{{ $file->id }}')" title="{{ __('Edit Nama') }}">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:button size="xs" icon="eye" :href="route('files.show', $file->id)" wire:navigate>
                                                {{ __('Detail') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @else
            <!-- Grid View -->
            <div class="flex items-center justify-between px-1">
                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        wire:model.live="selectAll"
                        class="rounded border-zinc-300 dark:border-zinc-700 text-sky-600 focus:ring-sky-500 size-4 cursor-pointer"
                    />
                    <span>{{ __('Pilih Semua File di Halaman Ini') }}</span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($files as $file)
                    <flux:card class="flex flex-col justify-between p-4 hover:shadow-md transition">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <input
                                        type="checkbox"
                                        value="{{ $file->id }}"
                                        wire:model.live="selectedFiles"
                                        class="rounded border-zinc-300 dark:border-zinc-700 text-sky-600 focus:ring-sky-500 size-4 cursor-pointer mt-0.5 shrink-0"
                                    />
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $file->is_directory ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200' }} font-bold uppercase text-xs">
                                        @if($file->is_directory)
                                            <flux:icon icon="folder" class="size-6" />
                                        @elseif($file->extension == 'safetensors')
                                            <flux:icon icon="light-bulb" class="size-6" />
                                        @else
                                            {{ $file->extension }}
                                        @endif
                                    </div>
                                </div>
                                @if($file->category)
                                    <flux:badge size="sm" color="zinc">
                                        {{ $file->category->name }}
                                    </flux:badge>
                                @else
                                    <flux:badge size="sm" :color="match($file->file_type_group) {
                                        'folder' => 'amber',
                                        'document' => 'blue',
                                        'image' => 'emerald',
                                        'video' => 'purple',
                                        'audio' => 'amber',
                                        'archive' => 'red',
                                        'code' => 'cyan',
                                        default => 'zinc',
                                    }">
                                        {{ $file->is_directory ? 'Folder' : $file->file_type_group }}
                                    </flux:badge>
                                @endif
                            </div>

                            <div>
                                <a href="{{ route('files.show', $file->id) }}" wire:navigate class="font-medium text-sm text-zinc-900 dark:text-zinc-100 hover:text-blue-600 line-clamp-2" title="{{ $file->name }}">
                                    {{ $file->name }}
                                </a>
                                <div class="flex items-center justify-between text-xs text-zinc-500 mt-1">
                                    <span>
                                        @if($file->is_directory)
                                            {{ isset($file->metadata['item_count']) ? $file->metadata['item_count'] . ' item' : 'Folder' }}
                                        @else
                                            {{ $file->formatted_size }}
                                        @endif
                                    </span>
                                    <span>{{ $file->locations->count() }} lokasi</span>
                                </div>
                            </div>

                            @if($file->tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1 pt-1">
                                    @foreach($file->tags->take(3) as $tag)
                                        <flux:badge size="sm" :color="$tag->color ?? 'zinc'">{{ $tag->name }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-1.5">
                            <flux:button size="xs" icon="pencil-square" wire:click="openEditNameModal('{{ $file->id }}')">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="xs" :href="route('files.show', $file->id)" wire:navigate>
                                {{ __('Lihat Detail') }}
                            </flux:button>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif

        <div class="pt-2">
            {{ $files->links() }}
        </div>
    @endif

    <!-- Modal Konfirmasi Hapus Massal dari Katalog -->
    @if($showBulkDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                        <flux:icon icon="trash" class="size-6" />
                        <flux:heading size="lg">{{ __('Hapus Massal dari Katalog') }}</flux:heading>
                    </div>
                    <button type="button" wire:click="$set('showBulkDeleteModal', false)" class="text-zinc-400 hover:text-zinc-600">
                        <flux:icon icon="x-mark" class="size-5" />
                    </button>
                </div>

                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Apakah Anda yakin ingin menghapus :count catatan file yang dipilih dari katalog inventaris?', ['count' => count($selectedFiles)]) }}
                </p>

                <div class="rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 p-3 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                    <flux:icon icon="shield-check" class="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="space-y-1">
                        <div class="font-semibold">{{ __('Strictly Read-Only Guarantee:') }}</div>
                        <div>{{ __('Tindakan ini HANYA menghapus data entri katalog/inventaris dari database SIFD. File fisik asli di penyimpanan (Local Disk / NAS) TIDAK AKAN dihapus atau disentuh sama sekali.') }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <flux:button type="button" wire:click="$set('showBulkDeleteModal', false)">
                        {{ __('Batal') }}
                    </flux:button>
                    <flux:button variant="danger" type="button" wire:click="deleteBulkFiles">
                        {{ __('Ya, Hapus :count File dari Katalog', ['count' => count($selectedFiles)]) }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Hapus dari Katalog -->
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                        <flux:icon icon="trash" class="size-6" />
                        <flux:heading size="lg">{{ __('Hapus dari Katalog') }}</flux:heading>
                    </div>
                    <button type="button" wire:click="$set('showDeleteModal', false)" class="text-zinc-400 hover:text-zinc-600">
                        <flux:icon icon="x-mark" class="size-5" />
                    </button>
                </div>

                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Apakah Anda yakin ingin menghapus data inventaris untuk file:') }}
                    <strong class="text-zinc-900 dark:text-zinc-100 block mt-1 font-mono break-all">{{ $deletingFileName }}</strong>
                </p>

                <div class="rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 p-3 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                    <flux:icon icon="shield-check" class="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="space-y-1">
                        <div class="font-semibold">{{ __('Strictly Read-Only Guarantee:') }}</div>
                        <div>{{ __('Tindakan ini HANYA menghapus data entri katalog/inventaris dari database SIFD. File/folder fisik asli di penyimpanan TIDAK AKAN dihapus, diubah, atau disentuh sama sekali.') }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <flux:button type="button" wire:click="$set('showDeleteModal', false)">
                        {{ __('Batal') }}
                    </flux:button>
                    <flux:button variant="danger" type="button" wire:click="deleteFile">
                        {{ __('Ya, Hapus dari Katalog') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Edit Nama Tampilan File -->
    @if($showEditNameModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Edit Nama Katalog File') }}</flux:heading>
                    <button type="button" wire:click="$set('showEditNameModal', false)" class="text-zinc-400 hover:text-zinc-600">
                        <flux:icon icon="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50 p-3 text-xs text-blue-800 dark:text-blue-200 flex items-start gap-2">
                    <flux:icon icon="information-circle" class="size-4 shrink-0 mt-0.5 text-blue-500" />
                    <span>{{ __('Perubahan ini HANYA mengganti nama tampilan katalog pada sistem inventaris. File fisik asli di penyimpanan tetap tidak berubah (Strictly Read-Only).') }}</span>
                </div>

                <form wire:submit="saveFileName" class="space-y-4">
                    <div class="space-y-1">
                        <flux:label>{{ __('Nama Tampilan Baru') }}</flux:label>
                        <flux:input
                            wire:model="editingFileName"
                            placeholder="Masukkan nama tampilan baru..."
                            required
                            autofocus
                        />
                        @error('editingFileName')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <flux:button type="button" wire:click="$set('showEditNameModal', false)">
                            {{ __('Batal') }}
                        </flux:button>
                        <flux:button variant="primary" type="submit">
                            {{ __('Simpan Perubahan') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
