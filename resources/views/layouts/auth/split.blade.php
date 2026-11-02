<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 antialiased dark:bg-[#04101e]">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-cyan-500/20 bg-gradient-to-br from-[#04101e] via-[#071e35] to-[#0f4c81]">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-cyan-500/10 via-transparent to-transparent"></div>
                <a href="{{ route('home') }}" class="relative z-20 flex items-center gap-3 text-lg font-bold tracking-tight" wire:navigate>
                    <span class="flex size-10 items-center justify-center rounded-xl p-0.5 bg-cyan-950/40 border border-cyan-500/30 shadow-md">
                        <x-app-logo-icon class="size-9" />
                    </span>
                    <div>
                        <div class="text-white">SIFD</div>
                        <div class="text-[11px] font-normal text-cyan-300">Digital File Inventory</div>
                    </div>
                </a>

                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp

                <div class="relative z-20 mt-auto">
                    <blockquote class="space-y-2">
                        <flux:heading size="lg">&ldquo;{{ trim($message) }}&rdquo;</flux:heading>
                        <footer><flux:heading class="text-cyan-300">{{ trim($author) }}</flux:heading></footer>
                    </blockquote>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex size-14 items-center justify-center rounded-2xl p-1 bg-white dark:bg-cyan-950/40 border border-cyan-500/30 shadow-lg">
                            <x-app-logo-icon class="size-12" />
                        </span>

                        <span class="font-bold text-xl text-zinc-900 dark:text-white">SIFD</span>
                        <span class="text-xs text-sky-600 dark:text-cyan-400 font-medium -mt-2">Digital File Inventory System</span>
                    </a>
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
