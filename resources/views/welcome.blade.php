<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'SIFD') }} - Sistem Inventaris File Digital</title>

        <link rel="icon" href="{{ asset('images/logo.jpg') }}" type="image/jpeg">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.jpg') }}">

        @fonts

        <!-- Theme Detection Script (Executed immediately to prevent FOUC) -->
        <script>
            (function () {
                const isAuth = {{ auth()->check() ? 'true' : 'false' }};
                let appearance = 'system';

                if (isAuth) {
                    // Match theme from dashboard user settings
                    appearance = localStorage.getItem('flux.appearance') || 'system';
                }

                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark);

                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();

            function toggleWelcomeTheme() {
                const isDark = document.documentElement.classList.contains('dark');
                const newTheme = isDark ? 'light' : 'dark';

                if (newTheme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }

                // If user is authenticated, keep dashboard preference in sync
                const isAuth = {{ auth()->check() ? 'true' : 'false' }};
                if (isAuth) {
                    localStorage.setItem('flux.appearance', newTheme);
                }

                if (window.Flux && typeof window.Flux.applyAppearance === 'function') {
                    window.Flux.applyAppearance(newTheme);
                }
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-slate-50 dark:bg-[#04101e] text-slate-800 dark:text-slate-100 antialiased selection:bg-cyan-500 selection:text-white transition-colors duration-200">
        @php
            $totalFiles = \App\Models\InventoryFile::count();
            $totalStorages = \App\Models\StorageSource::where('is_active', true)->count();
            $totalCategories = \App\Models\Category::count();
            $totalTags = \App\Models\Tag::count();
        @endphp

        <!-- Ambient Glows (Light Ice-Blue & Dark Cyan-Navy) -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
            <div class="absolute -top-40 -left-40 size-[550px] rounded-full bg-sky-200/40 dark:bg-cyan-600/15 blur-[120px] transition-colors duration-300"></div>
            <div class="absolute top-1/4 -right-40 size-[600px] rounded-full bg-cyan-100/50 dark:bg-sky-600/15 blur-[140px] transition-colors duration-300"></div>
            <div class="absolute -bottom-40 left-1/3 size-[650px] rounded-full bg-blue-100/40 dark:bg-blue-900/20 blur-[130px] transition-colors duration-300"></div>
            <!-- Circuit Grid Overlay -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#0284c70a_1px,transparent_1px),linear-gradient(to_bottom,#0284c70a_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,#0ea5e90a_1px,transparent_1px),linear-gradient(to_bottom,#0ea5e90a_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
        </div>

        <!-- Navigation Bar -->
        <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-white/80 dark:bg-[#04101e]/80 border-b border-sky-100 dark:border-cyan-500/15 transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
                <!-- Brand -->
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <div class="flex size-11 items-center justify-center rounded-xl p-1 bg-sky-50 dark:bg-[#071e35] border border-sky-200 dark:border-cyan-500/30 shadow-xs group-hover:border-sky-400 dark:group-hover:border-cyan-400 group-hover:shadow-[0_0_15px_rgba(6,182,212,0.25)] transition-all duration-300">
                        <img src="{{ asset('images/logo.jpg') }}" alt="SIFD Logo" class="size-9 object-contain rounded-lg" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-bold tracking-tight text-slate-900 dark:text-white group-hover:text-sky-600 dark:group-hover:text-cyan-300 transition">SIFD</span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wider bg-sky-100 text-sky-700 border border-sky-200 dark:bg-cyan-950/60 dark:text-cyan-400 dark:border-cyan-500/30">
                                Inventaris
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">Digital File Inventory System</p>
                    </div>
                </a>

                <!-- Desktop Nav Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <a href="#features" class="hover:text-sky-600 dark:hover:text-cyan-400 transition">Keunggulan</a>
                    <a href="#architecture" class="hover:text-sky-600 dark:hover:text-cyan-400 transition">Arsitektur</a>
                    <a href="#readonly" class="hover:text-sky-600 dark:hover:text-cyan-400 transition">Kebijakan Read-Only</a>
                    <a href="#stats" class="hover:text-sky-600 dark:hover:text-cyan-400 transition">Statistik</a>
                </nav>

                <!-- Actions & Theme Switcher -->
                <div class="flex items-center gap-3">
                    <!-- Light / Dark Mode Toggle Button -->
                    <button
                        type="button"
                        onclick="toggleWelcomeTheme()"
                        class="size-10 rounded-xl flex items-center justify-center border border-sky-200 dark:border-cyan-500/30 bg-white/90 dark:bg-[#071e35]/90 text-slate-700 dark:text-cyan-300 hover:border-sky-400 dark:hover:border-cyan-400 shadow-xs hover:shadow-md transition-all duration-200"
                        title="Ganti Tema (Light / Dark)"
                        aria-label="Toggle Theme"
                    >
                        <!-- Sun Icon shown in Dark Mode -->
                        <flux:icon icon="sun" class="size-5 hidden dark:block text-amber-300" />
                        <!-- Moon Icon shown in Light Mode -->
                        <flux:icon icon="moon" class="size-5 block dark:hidden text-sky-700" />
                    </button>

                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-sky-600 to-cyan-500 text-white hover:from-sky-500 hover:to-cyan-400 shadow-md shadow-sky-600/20 dark:shadow-cyan-900/30 hover:shadow-sky-500/30 transition-all duration-200"
                            wire:navigate
                        >
                            <flux:icon icon="home" class="size-4" />
                            <span>Buka Dashboard</span>
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition"
                            wire:navigate
                        >
                            Masuk
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-sky-600 to-cyan-500 text-white hover:from-sky-500 hover:to-cyan-400 shadow-md shadow-sky-600/20 dark:shadow-cyan-900/30 hover:shadow-sky-500/30 transition-all duration-200"
                                wire:navigate
                            >
                                <span>Daftar</span>
                                <flux:icon icon="arrow-right" class="size-4" />
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-12 pb-20 md:pt-20 md:pb-28 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                    <!-- Left Hero Content -->
                    <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                        <!-- Top Pill Badge -->
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-sky-300/80 dark:border-cyan-500/30 bg-sky-50 dark:bg-cyan-950/40 text-xs font-medium text-sky-800 dark:text-cyan-300 shadow-xs backdrop-blur-sm">
                            <span class="size-2 rounded-full bg-sky-500 dark:bg-cyan-400 animate-pulse"></span>
                            <span>Sistem Inventaris File Digital &bull; Reference-Based Catalog</span>
                        </div>

                        <!-- Main Headline -->
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-[1.15]">
                            Katalog Terpusat & Temukan File Digital Anda
                            <span class="block mt-2 bg-gradient-to-r from-sky-600 via-cyan-600 to-blue-700 dark:from-cyan-400 dark:via-sky-300 dark:to-blue-200 bg-clip-text text-transparent">
                                Lintas Berbagai Storage
                            </span>
                        </h1>

                        <!-- Subtitle -->
                        <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto lg:mx-0 font-normal leading-relaxed">
                            SIFD menginventarisasi dan memetakan file dari drive lokal, NAS, dan jaringan tanpa pernah mengubah, memindahkan, atau menyentuh file asli Anda. Solusi terpercaya untuk katalog & penemuan aset digital skala besar.
                        </p>

                        <!-- Highlights Quick Badges -->
                        <div class="pt-2 flex flex-wrap items-center justify-center lg:justify-start gap-4 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/90 dark:bg-[#071e35]/70 border border-sky-200 dark:border-cyan-500/20 shadow-xs">
                                <flux:icon icon="shield-check" class="size-4 text-sky-600 dark:text-cyan-400" />
                                <span>Strictly Read-Only Guarantee</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/90 dark:bg-[#071e35]/70 border border-sky-200 dark:border-cyan-500/20 shadow-xs">
                                <flux:icon icon="bolt" class="size-4 text-amber-500 dark:text-amber-400" />
                                <span>Background Queue Scanning</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/90 dark:bg-[#071e35]/70 border border-sky-200 dark:border-cyan-500/20 shadow-xs">
                                <flux:icon icon="finger-print" class="size-4 text-blue-600 dark:text-sky-400" />
                                <span>SHA-256 Checksum Index</span>
                            </div>
                        </div>

                        <!-- CTA Buttons -->
                        <div class="pt-4 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            @auth
                                <a
                                    href="{{ route('files.index') }}"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-xl text-base font-semibold bg-gradient-to-r from-sky-600 via-cyan-600 to-cyan-500 text-white hover:from-sky-500 hover:to-cyan-400 shadow-lg shadow-sky-600/25 dark:shadow-cyan-900/40 hover:shadow-sky-500/35 transition-all duration-200 group"
                                    wire:navigate
                                >
                                    <flux:icon icon="magnifying-glass" class="size-5" />
                                    <span>Jelajahi Katalog File</span>
                                    <flux:icon icon="arrow-right" class="size-4 group-hover:translate-x-1 transition-transform" />
                                </a>
                                <a
                                    href="{{ route('scanner.index') }}"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-200 bg-white/90 dark:bg-[#071e35]/90 hover:bg-sky-50 dark:hover:bg-[#0c2f52] border border-sky-300 dark:border-cyan-500/25 hover:border-sky-400 dark:hover:border-cyan-400/50 shadow-xs transition-all duration-200"
                                    wire:navigate
                                >
                                    <flux:icon icon="folder-plus" class="size-5 text-sky-600 dark:text-cyan-400" />
                                    <span>Pindai Direktori Baru</span>
                                </a>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-xl text-base font-semibold bg-gradient-to-r from-sky-600 via-cyan-600 to-cyan-500 text-white hover:from-sky-500 hover:to-cyan-400 shadow-lg shadow-sky-600/25 dark:shadow-cyan-900/40 hover:shadow-sky-500/35 transition-all duration-200 group"
                                    wire:navigate
                                >
                                    <flux:icon icon="arrow-right-start-on-rectangle" class="size-5" />
                                    <span>Masuk ke Sistem Inventaris</span>
                                    <flux:icon icon="arrow-right" class="size-4 group-hover:translate-x-1 transition-transform" />
                                </a>
                                <a
                                    href="#architecture"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-200 bg-white/90 dark:bg-[#071e35]/90 hover:bg-sky-50 dark:hover:bg-[#0c2f52] border border-sky-300 dark:border-cyan-500/25 hover:border-sky-400 dark:hover:border-cyan-400/50 shadow-xs transition-all duration-200"
                                >
                                    <flux:icon icon="information-circle" class="size-5 text-sky-600 dark:text-cyan-400" />
                                    <span>Pelajari Arsitektur</span>
                                </a>
                            @endauth
                        </div>
                    </div>

                    <!-- Right Hero Visual Showcase -->
                    <div class="lg:col-span-5 relative">
                        <!-- Glow Frame -->
                        <div class="relative mx-auto max-w-md lg:max-w-none rounded-3xl p-6 sm:p-8 bg-white/95 dark:bg-gradient-to-b dark:from-[#071e35]/90 dark:to-[#04101e]/90 border border-sky-200 dark:border-cyan-500/30 shadow-xl shadow-sky-500/5 dark:shadow-2xl backdrop-blur-xl transition-all duration-200">
                            <!-- Floating Circuit Decorative Header -->
                            <div class="flex items-center justify-between pb-6 border-b border-sky-100 dark:border-cyan-500/20">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-3 rounded-full bg-red-400 dark:bg-red-500/80"></div>
                                    <div class="size-3 rounded-full bg-amber-400 dark:bg-amber-500/80"></div>
                                    <div class="size-3 rounded-full bg-emerald-400 dark:bg-emerald-500/80"></div>
                                    <span class="text-xs font-mono text-sky-700 dark:text-cyan-300 ml-2">sifd://catalog-core</span>
                                </div>
                                <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-500/30">
                                    READ-ONLY ACTIVE
                                </span>
                            </div>

                            <!-- Big Logo Presentation -->
                            <div class="py-6 flex flex-col items-center text-center space-y-4">
                                <div class="relative group">
                                    <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-sky-400 to-cyan-500 opacity-20 dark:opacity-30 blur-xl group-hover:opacity-40 dark:group-hover:opacity-60 transition duration-500"></div>
                                    <img
                                        src="{{ asset('images/logo.jpg') }}"
                                        alt="SIFD Digital Folder Logo"
                                        class="relative size-36 sm:size-44 object-contain rounded-2xl shadow-lg border border-sky-200 dark:border-cyan-500/40 p-2 bg-sky-50/50 dark:bg-[#04101e]"
                                    />
                                </div>

                                <div>
                                    <h3 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Sistem Inventaris File Digital</h3>
                                    <p class="text-xs text-sky-600 dark:text-cyan-300 font-mono mt-1">Reference-Based Metadata Indexing</p>
                                </div>
                            </div>

                            <!-- Live Mock File Catalog Row -->
                            <div class="space-y-2.5 pt-2">
                                <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pratinjau Data Entri:</div>

                                <div class="rounded-xl border border-sky-200/80 dark:border-cyan-500/20 bg-sky-50/60 dark:bg-[#0c2f52]/40 p-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-8 rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 flex items-center justify-center shrink-0">
                                            <flux:icon icon="folder" class="size-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-medium text-slate-900 dark:text-white truncate">Westworld (Koleksi Serial TV)</div>
                                            <div class="text-[10px] text-sky-600 dark:text-cyan-400 font-mono truncate">12 episode &bull; 1080p &bull; NAS Storage</div>
                                        </div>
                                    </div>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 border border-sky-200 dark:bg-cyan-950 dark:text-cyan-300 dark:border-cyan-500/30 shrink-0 font-medium">
                                        Folder
                                    </span>
                                </div>

                                <div class="rounded-xl border border-sky-200/80 dark:border-cyan-500/20 bg-sky-50/60 dark:bg-[#0c2f52]/40 p-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-8 rounded-lg bg-blue-100 text-blue-700 dark:bg-sky-500/20 dark:text-sky-300 flex items-center justify-center shrink-0 text-xs font-bold uppercase">
                                            PDF
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-medium text-slate-900 dark:text-white truncate">Laporan_Tahunan_Inventaris_2026.pdf</div>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono truncate">4.8 MB &bull; SHA-256 Terverifikasi</div>
                                        </div>
                                    </div>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 border border-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:border-blue-500/30 shrink-0 font-medium">
                                        Dokumen
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Counter Section -->
        <section id="stats" class="py-12 border-y border-sky-200 dark:border-cyan-500/15 bg-white/70 dark:bg-[#071e35]/40 backdrop-blur-sm transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                    <div class="space-y-1">
                        <div class="text-3xl sm:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-sky-600 to-cyan-600 dark:from-cyan-400 dark:to-sky-300">
                            {{ number_format($totalFiles) }}
                        </div>
                        <div class="text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300">File Terinventarisasi</div>
                        <div class="text-[11px] text-sky-600 dark:text-cyan-400 font-mono">Di Seluruh Storage</div>
                    </div>

                    <div class="space-y-1">
                        <div class="text-3xl sm:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-sky-600 dark:from-sky-400 dark:to-blue-300">
                            {{ number_format($totalStorages) }}
                        </div>
                        <div class="text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300">Storage Sumber Aktif</div>
                        <div class="text-[11px] text-blue-600 dark:text-sky-400 font-mono">Local Disk & NAS</div>
                    </div>

                    <div class="space-y-1">
                        <div class="text-3xl sm:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-cyan-600 to-teal-600 dark:from-cyan-300 dark:to-teal-300">
                            {{ number_format($totalCategories) }}
                        </div>
                        <div class="text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300">Kategori Terdaftar</div>
                        <div class="text-[11px] text-teal-600 dark:text-teal-400 font-mono">Klasifikasi Terstruktur</div>
                    </div>

                    <div class="space-y-1">
                        <div class="text-3xl sm:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-blue-300 dark:to-cyan-300">
                            100%
                        </div>
                        <div class="text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300">Read-Only Safety</div>
                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono">Original Files Untouched</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Core Features Grid -->
        <section id="features" class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-14">
                <div class="text-center max-w-3xl mx-auto space-y-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-sky-600 dark:text-cyan-400">Arsitektur & Keunggulan</span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                        Didesain Khusus untuk Katalog File Skala Besar
                    </h2>
                    <p class="text-sm sm:text-base text-slate-600 dark:text-slate-300">
                        SIFD memisahkan sepenuhnya antara pencatatan inventaris dan penyimpanan fisik, memberikan fleksibilitas tanpa risiko kehilangan atau rusaknya data asli.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Feature 1: Read-Only Policy -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#071e35]/60 border border-sky-200/80 dark:border-cyan-500/20 hover:border-sky-400 dark:hover:border-cyan-400/50 hover:bg-sky-50/40 dark:hover:bg-[#0c2f52]/50 shadow-xs hover:shadow-md transition-all duration-300 space-y-3 group">
                        <div class="size-12 rounded-xl bg-sky-50 dark:bg-cyan-500/10 border border-sky-200 dark:border-cyan-500/30 flex items-center justify-center text-sky-600 dark:text-cyan-400 group-hover:scale-110 group-hover:bg-sky-100 dark:group-hover:bg-cyan-500/20 transition-all">
                            <flux:icon icon="shield-check" class="size-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Strictly Read-Only Policy</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            SIFD tidak pernah mengunggah, mengubah isi, mengubah nama, memindahkan, atau menghapus file fisik di drive penyimpanan Anda. File asli 100% aman di tempat asalnya.
                        </p>
                    </div>

                    <!-- Feature 2: Connector-Based Architecture -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#071e35]/60 border border-sky-200/80 dark:border-cyan-500/20 hover:border-sky-400 dark:hover:border-cyan-400/50 hover:bg-sky-50/40 dark:hover:bg-[#0c2f52]/50 shadow-xs hover:shadow-md transition-all duration-300 space-y-3 group">
                        <div class="size-12 rounded-xl bg-blue-50 dark:bg-sky-500/10 border border-blue-200 dark:border-sky-500/30 flex items-center justify-center text-blue-600 dark:text-sky-400 group-hover:scale-110 group-hover:bg-blue-100 dark:group-hover:bg-sky-500/20 transition-all">
                            <flux:icon icon="circle-stack" class="size-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Konektor Multi-Storage</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Mendukung penyimpanan lokal, external hard drive, dan Network Attached Storage (NAS) melalui konektor independen tanpa mengikat logika aplikasi ke satu vendor.
                        </p>
                    </div>

                    <!-- Feature 3: Background Queue Scanner -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#071e35]/60 border border-sky-200/80 dark:border-cyan-500/20 hover:border-sky-400 dark:hover:border-cyan-400/50 hover:bg-sky-50/40 dark:hover:bg-[#0c2f52]/50 shadow-xs hover:shadow-md transition-all duration-300 space-y-3 group">
                        <div class="size-12 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 flex items-center justify-center text-amber-600 dark:text-amber-400 group-hover:scale-110 group-hover:bg-amber-100 dark:group-hover:bg-amber-500/20 transition-all">
                            <flux:icon icon="bolt" class="size-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Pemindai Antrean Background</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Pindai ratusan ribu file di latar belakang tanpa memberatkan server atau antarmuka. Dilengkapi pemantauan path real-time dan tombol penghentian darurat.
                        </p>
                    </div>

                    <!-- Feature 4: SHA-256 Checksum & Dedup -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#071e35]/60 border border-sky-200/80 dark:border-cyan-500/20 hover:border-sky-400 dark:hover:border-cyan-400/50 hover:bg-sky-50/40 dark:hover:bg-[#0c2f52]/50 shadow-xs hover:shadow-md transition-all duration-300 space-y-3 group">
                        <div class="size-12 rounded-xl bg-cyan-50 dark:bg-blue-500/10 border border-cyan-200 dark:border-blue-500/30 flex items-center justify-center text-cyan-600 dark:text-blue-400 group-hover:scale-110 group-hover:bg-cyan-100 dark:group-hover:bg-blue-500/20 transition-all">
                            <flux:icon icon="finger-print" class="size-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Deteksi Duplikasi & Multi-Lokasi</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Satu rekaman file inventaris dapat memiliki banyak lokasi fisik. Kalkulasi hash SHA-256 mendeteksi file duplikat di berbagai folder secara akurat.
                        </p>
                    </div>

                    <!-- Feature 5: Mode Berkas & Folder Serial -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#071e35]/60 border border-sky-200/80 dark:border-cyan-500/20 hover:border-sky-400 dark:hover:border-cyan-400/50 hover:bg-sky-50/40 dark:hover:bg-[#0c2f52]/50 shadow-xs hover:shadow-md transition-all duration-300 space-y-3 group">
                        <div class="size-12 rounded-xl bg-teal-50 dark:bg-cyan-500/10 border border-teal-200 dark:border-cyan-500/30 flex items-center justify-center text-teal-600 dark:text-cyan-400 group-hover:scale-110 group-hover:bg-teal-100 dark:group-hover:bg-cyan-500/20 transition-all">
                            <flux:icon icon="folder" class="size-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Mode File & Folder Koleksi</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Pilih pemindaian per berkas individu atau per folder koleksi (cocok untuk Serial TV, album foto, dan bundle proyek) dengan penghitungan item otomatis.
                        </p>
                    </div>

                    <!-- Feature 6: Penamaan Tampilan Fleksibel -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#071e35]/60 border border-sky-200/80 dark:border-cyan-500/20 hover:border-sky-400 dark:hover:border-cyan-400/50 hover:bg-sky-50/40 dark:hover:bg-[#0c2f52]/50 shadow-xs hover:shadow-md transition-all duration-300 space-y-3 group">
                        <div class="size-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-110 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-500/20 transition-all">
                            <flux:icon icon="pencil-square" class="size-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Nama Tampilan Ramah</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            Sesuaikan nama tampilan katalog tanpa menyentuh nama file fisik pada penyimpanan disk. Tetap teratur dengan nama yang mudah dicari dan dibaca.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Architecture Section -->
        <section id="architecture" class="py-20 border-t border-sky-200 dark:border-cyan-500/15 bg-gradient-to-b from-slate-50 to-sky-50/50 dark:from-[#04101e] dark:to-[#071e35] transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                <div class="text-center max-w-3xl mx-auto space-y-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-sky-600 dark:text-cyan-400">Bagaimana SIFD Bekerja</span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                        Arsitektur Inventaris Berbasis Referensi
                    </h2>
                    <p class="text-sm sm:text-base text-slate-600 dark:text-slate-300">
                        Pemisahan tegas antara media penyimpanan fisik dan basis data katalog cerdas.
                    </p>
                </div>

                <!-- Visual Architecture Flow Diagram -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center items-stretch">
                    <!-- Step 1 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#0c2f52]/40 border border-sky-200 dark:border-cyan-500/20 shadow-xs flex flex-col justify-between space-y-4">
                        <div class="size-10 rounded-xl bg-sky-100 dark:bg-cyan-950 text-sky-700 dark:text-cyan-400 border border-sky-200 dark:border-cyan-500/40 flex items-center justify-center font-bold mx-auto">1</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-base">Storage Fisik</h4>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">Drive C:, D:, Network NAS, Harddisk Eksternal.</p>
                        </div>
                        <div class="text-[11px] font-mono text-sky-700 dark:text-cyan-300 bg-sky-50 dark:bg-cyan-950/50 py-1 px-2 rounded border border-sky-200 dark:border-transparent">
                            Original Files Stay
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#0c2f52]/40 border border-sky-200 dark:border-cyan-500/20 shadow-xs flex flex-col justify-between space-y-4">
                        <div class="size-10 rounded-xl bg-blue-100 dark:bg-sky-950 text-blue-700 dark:text-sky-400 border border-blue-200 dark:border-sky-500/40 flex items-center justify-center font-bold mx-auto">2</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-base">Konektor Read-Only</h4>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">Hanya membaca daftar file, ukuran, waktu, dan hash SHA-256.</p>
                        </div>
                        <div class="text-[11px] font-mono text-blue-700 dark:text-sky-300 bg-blue-50 dark:bg-sky-950/50 py-1 px-2 rounded border border-blue-200 dark:border-transparent">
                            No Write / No Move
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#0c2f52]/40 border border-sky-200 dark:border-cyan-500/20 shadow-xs flex flex-col justify-between space-y-4">
                        <div class="size-10 rounded-xl bg-cyan-100 dark:bg-blue-950 text-cyan-700 dark:text-blue-400 border border-cyan-200 dark:border-blue-500/40 flex items-center justify-center font-bold mx-auto">3</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-base">Database Katalog</h4>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">MariaDB menyimpan UUID, metadata, kategori, dan tag.</p>
                        </div>
                        <div class="text-[11px] font-mono text-cyan-700 dark:text-blue-300 bg-cyan-50 dark:bg-blue-950/50 py-1 px-2 rounded border border-cyan-200 dark:border-transparent">
                            Authoritative Source
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-[#0c2f52]/40 border border-sky-200 dark:border-cyan-500/20 shadow-xs flex flex-col justify-between space-y-4">
                        <div class="size-10 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/40 flex items-center justify-center font-bold mx-auto">4</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-base">Pencarian & Penemuan</h4>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">Livewire Volt UI dengan tampilan tabel, grid, filter, dan sort.</p>
                        </div>
                        <div class="text-[11px] font-mono text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 py-1 px-2 rounded border border-emerald-200 dark:border-transparent">
                            Instant Discovery
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Read-Only Policy Guarantee Banner -->
        <section id="readonly" class="py-16">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl p-8 sm:p-10 bg-gradient-to-r from-sky-50 via-cyan-50/70 to-blue-50 dark:from-[#071e35] dark:via-[#0c2f52] dark:to-[#071e35] border border-sky-300 dark:border-cyan-500/30 shadow-lg dark:shadow-2xl relative overflow-hidden transition-all duration-200">
                    <div class="absolute -right-16 -top-16 size-64 rounded-full bg-sky-200/50 dark:bg-cyan-500/10 blur-3xl pointer-events-none"></div>

                    <div class="flex flex-col md:flex-row items-center gap-8">
                        <div class="size-20 sm:size-24 rounded-2xl bg-white dark:bg-cyan-950/80 border border-sky-300 dark:border-cyan-400/40 flex items-center justify-center shrink-0 text-sky-600 dark:text-cyan-300 shadow-sm">
                            <flux:icon icon="shield-check" class="size-12 sm:size-14" />
                        </div>
                        <div class="space-y-3 text-center md:text-left">
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Prinsip Mutlak: Read-Only Policy</h3>
                            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                Keamanan file Anda adalah prioritas nomor satu. SIFD <strong>bukan</strong> sistem penyimpanan file terpusat, melainkan <strong>katalog dan sistem penemuan</strong>. Sistem kami tidak menyediakan fitur untuk memodifikasi konten, mengubah nama file pada disk, memindahkan direktori, atau menghapus file fisik asli dari storage Anda.
                            </p>
                            <div class="pt-2 flex flex-wrap items-center justify-center md:justify-start gap-4 text-xs font-mono text-sky-700 dark:text-cyan-300 font-medium">
                                <span class="flex items-center gap-1.5">&check; Original Files Remain Safe</span>
                                <span class="flex items-center gap-1.5">&check; Zero Disk Modifications</span>
                                <span class="flex items-center gap-1.5">&check; MariaDB Authoritative Source</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Footer Card -->
        <section class="py-16 border-t border-sky-200 dark:border-cyan-500/15">
            <div class="max-w-4xl mx-auto px-4 text-center space-y-6">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Siap Mengatur & Mengkatalog File Digital Anda?
                </h2>
                <p class="text-sm sm:text-base text-slate-600 dark:text-slate-300 max-w-xl mx-auto">
                    Mulai sekarang dengan memindai direktori penyimpanan pertama Anda dan nikmati pencarian file yang cepat, rapi, dan aman.
                </p>
                <div class="pt-2 flex items-center justify-center gap-4">
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl text-base font-semibold bg-gradient-to-r from-sky-600 to-cyan-500 text-white hover:from-sky-500 hover:to-cyan-400 shadow-lg shadow-sky-600/20 dark:shadow-cyan-900/30 hover:shadow-sky-500/30 transition-all duration-200"
                            wire:navigate
                        >
                            <flux:icon icon="home" class="size-5" />
                            <span>Buka Dashboard</span>
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl text-base font-semibold bg-gradient-to-r from-sky-600 to-cyan-500 text-white hover:from-sky-500 hover:to-cyan-400 shadow-lg shadow-sky-600/20 dark:shadow-cyan-900/30 hover:shadow-sky-500/30 transition-all duration-200"
                            wire:navigate
                        >
                            <span>Mulai Sekarang</span>
                            <flux:icon icon="arrow-right" class="size-5" />
                        </a>
                    @endauth
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-sky-200 dark:border-cyan-500/15 bg-slate-100/90 dark:bg-[#030d18] py-10 text-xs text-slate-500 dark:text-slate-400 transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="size-6 object-contain rounded" />
                    <span class="font-bold text-slate-800 dark:text-slate-200">SIFD</span>
                    <span class="text-slate-400 dark:text-slate-600">&bull;</span>
                    <span>Sistem Inventaris File Digital</span>
                </div>

                <div class="flex items-center gap-6">
                    <a href="#features" class="hover:text-sky-600 dark:hover:text-cyan-300 transition">Keunggulan</a>
                    <a href="#architecture" class="hover:text-sky-600 dark:hover:text-cyan-300 transition">Arsitektur</a>
                    <a href="#readonly" class="hover:text-sky-600 dark:hover:text-cyan-300 transition">Read-Only Safety</a>
                </div>

                <div class="text-slate-400 dark:text-slate-500">
                    &copy; {{ date('Y') }} SIFD. All rights reserved.
                </div>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
