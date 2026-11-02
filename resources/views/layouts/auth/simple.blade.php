<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 antialiased dark:bg-gradient-to-b dark:from-[#04101e] dark:via-[#071e35] dark:to-[#0c2f52]">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium mb-3 group" wire:navigate>
                    <span class="flex size-14 items-center justify-center rounded-2xl p-1 bg-white/80 dark:bg-cyan-950/40 border border-cyan-500/30 shadow-lg group-hover:scale-105 transition-transform duration-200">
                        <x-app-logo-icon class="size-12" />
                    </span>
                    <span class="font-bold text-xl text-zinc-900 dark:text-white tracking-tight">SIFD</span>
                    <span class="text-xs text-sky-600 dark:text-cyan-400 font-medium -mt-2">Digital File Inventory System</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
