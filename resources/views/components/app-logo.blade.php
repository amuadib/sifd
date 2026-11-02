@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'SIFD')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg overflow-hidden bg-sky-950/20 border border-cyan-500/30 p-0.5 shadow-xs">
            <x-app-logo-icon class="size-7" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'SIFD')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg overflow-hidden bg-sky-950/20 border border-cyan-500/30 p-0.5 shadow-xs">
            <x-app-logo-icon class="size-7" />
        </x-slot>
    </flux:brand>
@endif
