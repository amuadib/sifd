<?php

use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kategori File')] class extends Component {
    public string $name = '';
    public string $slug = '';
    public string $description = '';

    public function updatedName(string $value): void
    {
        $this->slug = Str::slug($value);
    }

    public function createCategory(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories,slug',
            'description' => 'nullable|string|max:1000',
        ]);

        Category::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
        ]);

        $this->reset(['name', 'slug', 'description']);
    }

    public function deleteCategory(string $id): void
    {
        $cat = Category::findOrFail($id);
        $cat->delete();
    }

    public function with(): array
    {
        $categories = Category::withCount('files')->latest()->get();

        return [
            'categories' => $categories,
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Kategori File') }}</flux:heading>
        <flux:subheading>{{ __('Kelompokkan file ke dalam kategori logis (misal: Laporan Keuangan, Desain Grafis, Arsip SK).') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Tambah Kategori -->
        <flux:card class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Tambah Kategori Baru') }}</flux:heading>

            <form wire:submit="createCategory" class="space-y-4">
                <div>
                    <flux:label>{{ __('Nama Kategori') }}</flux:label>
                    <flux:input wire:model.live="name" placeholder="Misal: Dokumen Legal" required />
                </div>

                <div>
                    <flux:label>{{ __('Slug URL') }}</flux:label>
                    <flux:input wire:model="slug" placeholder="dokumen-legal" required />
                </div>

                <div>
                    <flux:label>{{ __('Deskripsi') }}</flux:label>
                    <flux:input wire:model="description" placeholder="Keterangan singkat kategori" />
                </div>

                <div class="pt-2">
                    <flux:button variant="primary" type="submit" class="w-full">
                        {{ __('Simpan Kategori') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <!-- Daftar Kategori -->
        <div class="lg:col-span-2 space-y-4">
            @if($categories->isEmpty())
                <flux:card class="p-8 text-center text-zinc-500 italic">
                    {{ __('Belum ada kategori yang dibuat.') }}
                </flux:card>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($categories as $category)
                        <flux:card class="p-5 flex flex-col justify-between space-y-3">
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-base">{{ $category->name }}</span>
                                    <flux:badge size="sm" color="zinc">{{ $category->files_count }} file</flux:badge>
                                </div>
                                <div class="text-xs font-mono text-zinc-400">{{ $category->slug }}</div>
                                @if($category->description)
                                    <div class="text-xs text-zinc-500 pt-1">{{ $category->description }}</div>
                                @endif
                            </div>

                            <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                <flux:button size="xs" variant="danger" wire:click="deleteCategory('{{ $category->id }}')" wire:confirm="Hapus kategori ini? File yang menggunakannya tidak akan terhapus.">
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
