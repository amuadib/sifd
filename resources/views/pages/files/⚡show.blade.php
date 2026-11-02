<?php

use App\Models\Category;
use App\Models\InventoryFile;
use App\Models\Tag;
use App\Services\Inventory\InventoryFileService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail File')] class extends Component {
    public InventoryFile $file;
    public string $selectedTagId = '';
    public ?string $selectedCategoryId = null;

    public bool $showEditNameModal = false;
    public string $editingName = '';

    public bool $showDeleteModal = false;

    public function deleteFile(InventoryFileService $inventoryFileService): void
    {
        $name = $this->file->name;
        $inventoryFileService->deleteFromCatalog($this->file);

        session()->flash('status', __('Data file :name telah dihapus dari katalog.', ['name' => $name]));

        $this->redirect(route('files.index'), navigate: true);
    }

    public function mount(InventoryFile $file): void
    {
        $this->file = $file->load(['category', 'tags', 'locations.storageSource']);
        $this->selectedCategoryId = $this->file->category_id;
    }

    public function openEditNameModal(): void
    {
        $this->editingName = $this->file->name;
        $this->showEditNameModal = true;
    }

    public function saveFileName(): void
    {
        $this->validate([
            'editingName' => 'required|string|min:1|max:255',
        ], [
            'editingName.required' => 'Nama file katalog tidak boleh kosong.',
            'editingName.max' => 'Nama file katalog maksimal 255 karakter.',
        ]);

        $this->file->update([
            'name' => trim($this->editingName),
        ]);

        $this->showEditNameModal = false;
        $this->file->refresh();
    }

    public function updateCategory(): void
    {
        $this->file->update([
            'category_id' => $this->selectedCategoryId ?: null,
        ]);
        $this->file->refresh();
    }

    public function attachTag(): void
    {
        if (! empty($this->selectedTagId)) {
            $this->file->tags()->syncWithoutDetaching([$this->selectedTagId]);
            $this->selectedTagId = '';
            $this->file->refresh();
        }
    }

    public function detachTag(string $tagId): void
    {
        $this->file->tags()->detach($tagId);
        $this->file->refresh();
    }

    public function with(): array
    {
        $categories = Category::orderBy('name')->get();
        $availableTags = Tag::whereNotIn('id', $this->file->tags->pluck('id'))->orderBy('name')->get();

        return [
            'categories' => $categories,
            'availableTags' => $availableTags,
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2 text-xs text-zinc-500">
                <a href="{{ route('files.index') }}" wire:navigate class="hover:text-blue-600">{{ __('Semua File') }}</a>
                <span>/</span>
                <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $file->name }}</span>
            </div>
            <flux:heading size="xl" class="break-all">{{ $file->name }}</flux:heading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button icon="pencil-square" wire:click="openEditNameModal">
                {{ __('Edit Nama') }}
            </flux:button>
            <flux:button variant="danger" icon="trash" wire:click="$set('showDeleteModal', true)">
                {{ __('Hapus') }}
            </flux:button>
            <flux:button icon="arrow-left" :href="route('files.index')" wire:navigate>
                {{ __('Kembali') }}
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Column: Overview & Preview -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Preview Box -->
            <flux:card class="p-6 space-y-4">
                <flux:heading size="lg">{{ __('Pratinjau File') }}</flux:heading>
                <div class="flex min-h-64 items-center justify-center rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30 p-6 text-center">
                    <div class="space-y-3">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl {{ $file->is_directory ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200' }} font-bold uppercase text-lg">
                            @if($file->is_directory)
                                <flux:icon icon="folder" class="size-9" />
                            @else
                                {{ $file->extension }}
                            @endif
                        </div>
                        <div class="flex items-center justify-center gap-2">
                            <div class="font-medium text-base text-zinc-900 dark:text-zinc-100 break-all">{{ $file->name }}</div>
                            <button type="button" wire:click="openEditNameModal" class="text-zinc-400 hover:text-blue-600 transition" title="{{ __('Edit Nama') }}">
                                <flux:icon icon="pencil-square" class="size-4" />
                            </button>
                        </div>
                            <div class="text-sm text-zinc-500">
                                @if($file->is_directory)
                                    {{ __('Direktori / Folder Koleksi') }} &bull; {{ isset($file->metadata['item_count']) ? $file->metadata['item_count'] . ' item di dalamnya' : 'Folder' }}
                                @else
                                    {{ $file->mime_type ?? 'MIME tidak diketahui' }} &bull; {{ $file->formatted_size }}
                                @endif
                            </div>
                        </div>
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
                            {{ $file->is_directory ? 'Folder / Koleksi' : ucfirst($file->file_type_group) }}
                        </flux:badge>
                    </div>
                </div>
            </flux:card>

            <!-- Physical Locations -->
            <flux:card class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Lokasi Fisik File') }}</flux:heading>
                        <flux:subheading>{{ __('File ini ditemukan di :count lokasi penyimpanan.', ['count' => $file->locations->count()]) }}</flux:subheading>
                    </div>
                    <flux:badge color="green">{{ $file->locations->count() }} Lokasi</flux:badge>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                    @forelse($file->locations as $location)
                        <div class="p-4 space-y-2 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm">{{ $location->storageSource?->name ?? 'Storage Tidak Dikenal' }}</span>
                                    <flux:badge size="sm">{{ $location->storageSource?->driver ?? 'local' }}</flux:badge>
                                </div>
                                <flux:badge size="sm" :color="$location->is_accessible ? 'green' : 'red'">
                                    {{ $location->is_accessible ? 'Tersedia' : 'Tidak Terjangkau' }}
                                </flux:badge>
                            </div>

                            <div class="space-y-1 text-xs font-mono text-zinc-600 dark:text-zinc-400">
                                <div><strong class="text-zinc-400">Path Relatif:</strong> {{ $location->relative_path }}</div>
                                <div><strong class="text-zinc-400">Path Lengkap:</strong> {{ $location->full_path }}</div>
                                <div><strong class="text-zinc-400">Nama File Asli:</strong> {{ $location->original_filename }}</div>
                            </div>

                            <div class="text-xs text-zinc-400 pt-1">
                                {{ __('Verifikasi Terakhir:') }} {{ $location->last_verified_at ? $location->last_verified_at->format('d M Y H:i:s') : '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-sm text-zinc-500 italic">
                            {{ __('Belum ada lokasi fisik yang terdaftar untuk file ini.') }}
                        </div>
                    @endforelse
                </div>
            </flux:card>

            <!-- Technical Metadata (JSON) -->
            <flux:card class="p-6 space-y-4">
                <flux:heading size="lg">{{ __('Metadata Teknis') }}</flux:heading>
                @if(empty($file->metadata))
                    <flux:text class="text-sm text-zinc-500 italic">{{ __('Tidak ada metadata teknis tambahan.') }}</flux:text>
                @else
                    <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/50 p-4 border border-zinc-200 dark:border-zinc-700">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            @foreach($file->metadata as $key => $val)
                                <div>
                                    <dt class="text-xs font-medium text-zinc-400 capitalize">{{ str_replace('_', ' ', $key) }}</dt>
                                    <dd class="font-mono text-xs text-zinc-800 dark:text-zinc-200 mt-0.5">
                                        {{ is_array($val) ? json_encode($val) : (string)$val }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </flux:card>
        </div>

        <!-- Sidebar Column: File Attributes, Tags, Categories -->
        <div class="space-y-6">
            <!-- File Info Card -->
            <flux:card class="p-6 space-y-4">
                <flux:heading size="lg">{{ __('Informasi File') }}</flux:heading>

                <div class="space-y-3 text-sm">
                    <div>
                        <div class="text-xs text-zinc-400">{{ __('Nama Katalog (Display Name)') }}</div>
                        <div class="font-medium break-all flex items-center justify-between gap-1 mt-0.5">
                            <span>{{ $file->name }}</span>
                            <button type="button" wire:click="openEditNameModal" class="text-xs text-blue-600 dark:text-blue-400 hover:underline shrink-0">
                                {{ __('Ubah') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-zinc-400">{{ __('Ukuran Asli') }}</div>
                        <div class="font-medium">{{ $file->formatted_size }} ({{ number_format($file->file_size) }} bytes)</div>
                    </div>

                    <div>
                        <div class="text-xs text-zinc-400">{{ __('Ekstensi') }}</div>
                        <div class="font-medium uppercase">{{ $file->extension }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-zinc-400">{{ __('MIME Type') }}</div>
                        <div class="font-mono text-xs">{{ $file->mime_type ?? '-' }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-zinc-400">{{ __('SHA-256 Checksum') }}</div>
                        <div class="font-mono text-xs break-all bg-zinc-100 dark:bg-zinc-800 p-2 rounded-lg text-zinc-700 dark:text-zinc-300 select-all">
                            {{ $file->checksum_sha256 }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-zinc-400">{{ __('Pertama Kali Dipindai') }}</div>
                        <div class="text-xs">{{ $file->created_at ? $file->created_at->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
            </flux:card>

            <!-- Category Card -->
            <flux:card class="p-6 space-y-4">
                <flux:heading size="lg">{{ __('Kategori') }}</flux:heading>
                <div class="space-y-3">
                    <flux:select wire:model="selectedCategoryId" wire:change="updateCategory">
                        <option value="">{{ __('-- Tanpa Kategori --') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:text class="text-xs text-zinc-400">
                        {{ __('Ubah kategori untuk mengelompokkan file secara logis.') }}
                    </flux:text>
                </div>
            </flux:card>

            <!-- Tags Card -->
            <flux:card class="p-6 space-y-4">
                <flux:heading size="lg">{{ __('Tag File') }}</flux:heading>

                <div class="flex flex-wrap gap-1.5 min-h-8">
                    @forelse($file->tags as $tag)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                            {{ $tag->name }}
                            <button
                                type="button"
                                wire:click="detachTag('{{ $tag->id }}')"
                                class="text-zinc-400 hover:text-red-500"
                                title="{{ __('Hapus tag') }}"
                            >
                                &times;
                            </button>
                        </span>
                    @empty
                        <flux:text class="text-xs text-zinc-400 italic">{{ __('Belum ada tag yang ditautkan.') }}</flux:text>
                    @endforelse
                </div>

                @if($availableTags->isNotEmpty())
                    <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 space-y-2">
                        <div class="text-xs font-medium text-zinc-500">{{ __('Tambah Tag:') }}</div>
                        <div class="flex items-center gap-2">
                            <flux:select wire:model="selectedTagId" class="flex-1">
                                <option value="">{{ __('-- Pilih Tag --') }}</option>
                                @foreach($availableTags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:button size="sm" variant="primary" wire:click="attachTag" :disabled="empty($selectedTagId)">
                                {{ __('Tambah') }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

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
                    <strong class="text-zinc-900 dark:text-zinc-100 block mt-1 font-mono break-all">{{ $file->name }}</strong>
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
                            wire:model="editingName"
                            placeholder="Masukkan nama tampilan baru..."
                            required
                            autofocus
                        />
                        @error('editingName')
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
