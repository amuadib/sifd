<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Inventaris File')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-duplicate" :href="route('files.index')" :current="request()->routeIs('files.*')" wire:navigate>
                        {{ __('Semua File') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="magnifying-glass" :href="route('scanner.index')" :current="request()->routeIs('scanner.*')" wire:navigate>
                        {{ __('Pindai Direktori') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="circle-stack" :href="route('storage-sources.index')" :current="request()->routeIs('storage-sources.*')" wire:navigate>
                        {{ __('Storage Sources') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="folder" :href="route('categories.index')" :current="request()->routeIs('categories.*')" wire:navigate>
                        {{ __('Kategori') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="tag" :href="route('tags.index')" :current="request()->routeIs('tags.*')" wire:navigate>
                        {{ __('Tag') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clock" :href="route('scan-histories.index')" :current="request()->routeIs('scan-histories.*')" wire:navigate>
                        {{ __('Riwayat Scan') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="px-3 py-2.5 rounded-xl border border-cyan-500/20 bg-cyan-950/20 text-xs space-y-1">
                <div class="flex items-center gap-1.5 font-medium text-cyan-400">
                    <span class="size-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    <span>{{ __('Strictly Read-Only') }}</span>
                </div>
                <p class="text-[11px] text-zinc-400 leading-tight">
                    {{ __('File storage fisik aman & tidak pernah dimodifikasi.') }}
                </p>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
