<?php

use App\Models\StorageSource;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sumber Penyimpanan')] class extends Component {
    public string $name = '';
    public string $driver = 'local';
    public string $base_path = '';
    public string $description = '';

    public function createStorageSource(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|in:local,nas_smb',
            'base_path' => 'required|string|max:1000',
            'description' => 'nullable|string|max:1000',
        ]);

        StorageSource::create([
            'name' => $this->name,
            'driver' => $this->driver,
            'base_path' => $this->base_path,
            'description' => $this->description,
            'is_active' => true,
        ]);

        $this->reset(['name', 'base_path', 'description']);
        $this->driver = 'local';
    }

    public function toggleActive(string $id): void
    {
        $source = StorageSource::findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
    }

    public function deleteStorage(string $id): void
    {
        $source = StorageSource::findOrFail($id);
        $source->delete();
    }

    public function with(): array
    {
        $sources = StorageSource::withCount('locations')->latest()->get();

        return [
            'sources' => $sources,
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Sumber Penyimpanan (Storage Sources)') }}</flux:heading>
        <flux:subheading>{{ __('Daftarkan harddisk lokal, mount point NAS, atau direktori jaringan untuk diinventarisasi.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Tambah Storage -->
        <flux:card class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Tambah Sumber Baru') }}</flux:heading>

            <form wire:submit="createStorageSource" class="space-y-4">
                <div>
                    <flux:label>{{ __('Nama Storage') }}</flux:label>
                    <flux:input wire:model="name" placeholder="Misal: Harddisk Eksternal 2TB" required />
                </div>

                <div>
                    <flux:label>{{ __('Driver') }}</flux:label>
                    <flux:select wire:model="driver">
                        <option value="local">Local Filesystem</option>
                        <option value="nas_smb">Network Share / NAS (Mounted)</option>
                    </flux:select>
                </div>

                <div>
                    <flux:label>{{ __('Base Path (Root)') }}</flux:label>
                    <flux:input wire:model="base_path" placeholder="D:\Archive atau /mnt/nas/share" required />
                    <flux:text class="text-xs text-zinc-400 mt-1">
                        {{ __('Folder dasar tempat file disimpan. Sistem hanya membaca secara read-only.') }}
                    </flux:text>
                </div>

                <div>
                    <flux:label>{{ __('Keterangan (Opsional)') }}</flux:label>
                    <flux:input wire:model="description" placeholder="Catatan lokasi atau pemilik disk" />
                </div>

                <div class="pt-2">
                    <flux:button variant="primary" type="submit" class="w-full">
                        {{ __('Simpan Storage Source') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <!-- Daftar Storage Sources -->
        <div class="lg:col-span-2 space-y-4">
            @if($sources->isEmpty())
                <flux:card class="p-8 text-center text-zinc-500 italic">
                    {{ __('Belum ada sumber penyimpanan yang terdaftar. Tambahkan sumber pertama Anda di formulir sebelah kiri.') }}
                </flux:card>
            @else
                <div class="space-y-3">
                    @foreach($sources as $source)
                        <flux:card class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-base">{{ $source->name }}</span>
                                    <flux:badge size="sm">{{ $source->driver }}</flux:badge>
                                    <flux:badge size="sm" :color="$source->is_active ? 'green' : 'zinc'">
                                        {{ $source->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </flux:badge>
                                </div>
                                <div class="text-xs font-mono text-zinc-500">{{ $source->base_path }}</div>
                                @if($source->description)
                                    <div class="text-xs text-zinc-400">{{ $source->description }}</div>
                                @endif
                                <div class="text-xs text-zinc-400 pt-1">
                                    {{ $source->locations_count }} {{ __('lokasi file tercatat') }} &bull;
                                    {{ __('Scan terakhir:') }} {{ $source->last_scanned_at ? $source->last_scanned_at->format('d M Y H:i') : 'Belum pernah' }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <flux:button size="sm" wire:click="toggleActive('{{ $source->id }}')">
                                    {{ $source->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="deleteStorage('{{ $source->id }}')" wire:confirm="Hapus sumber penyimpanan ini? Rekaman lokasi terkait akan terhapus.">
                                    {{ __('Hapus') }}
                                </flux:button>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
