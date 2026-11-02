<?php

use App\Models\Tag;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tag File')] class extends Component {
    public string $name = '';
    public string $slug = '';
    public string $color = 'blue';

    public function updatedName(string $value): void
    {
        $this->slug = Str::slug($value);
    }

    public function createTag(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tags,slug',
            'color' => 'required|string|max:30',
        ]);

        Tag::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
        ]);

        $this->reset(['name', 'slug']);
        $this->color = 'blue';
    }

    public function deleteTag(string $id): void
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();
    }

    public function with(): array
    {
        $tags = Tag::withCount('files')->latest()->get();

        return [
            'tags' => $tags,
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Tag Inventaris') }}</flux:heading>
        <flux:subheading>{{ __('Kelola label dan penanda untuk mempermudah pencarian tematik.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Tambah Tag -->
        <flux:card class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Tambah Tag Baru') }}</flux:heading>

            <form wire:submit="createTag" class="space-y-4">
                <div>
                    <flux:label>{{ __('Nama Tag') }}</flux:label>
                    <flux:input wire:model.live="name" placeholder="Misal: Arsip 2026, Urgent, Rahasia" required />
                </div>

                <div>
                    <flux:label>{{ __('Slug') }}</flux:label>
                    <flux:input wire:model="slug" placeholder="arsip-2026" required />
                </div>

                <div>
                    <flux:label>{{ __('Warna Badge') }}</flux:label>
                    <flux:select wire:model="color">
                        <option value="blue">Biru (Blue)</option>
                        <option value="green">Hijau (Green)</option>
                        <option value="red">Merah (Red)</option>
                        <option value="amber">Kuning / Oranye (Amber)</option>
                        <option value="purple">Ungu (Purple)</option>
                        <option value="cyan">Cyan</option>
                        <option value="zinc">Abu-abu (Zinc)</option>
                    </flux:select>
                </div>

                <div class="pt-2">
                    <flux:button variant="primary" type="submit" class="w-full">
                        {{ __('Simpan Tag') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <!-- Daftar Tag -->
        <div class="lg:col-span-2 space-y-4">
            @if($tags->isEmpty())
                <flux:card class="p-8 text-center text-zinc-500 italic">
                    {{ __('Belum ada tag yang dibuat.') }}
                </flux:card>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach($tags as $tag)
                        <flux:card class="p-4 flex items-center justify-between gap-3">
                            <div class="space-y-1">
                                <flux:badge :color="$tag->color">{{ $tag->name }}</flux:badge>
                                <div class="text-xs text-zinc-400 font-mono">{{ $tag->slug }}</div>
                                <div class="text-xs text-zinc-500">{{ $tag->files_count }} file ditautkan</div>
                            </div>

                            <flux:button size="xs" variant="danger" wire:click="deleteTag('{{ $tag->id }}')" wire:confirm="Hapus tag ini?">
                                {{ __('Hapus') }}
                            </flux:button>
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
