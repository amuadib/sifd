<?php

use App\Models\InventoryFile;
use App\Models\FileLocation;
use App\Models\StorageSource;
use App\Models\ScanHistory;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard Inventaris')] class extends Component {
    public function with(): array
    {
        $totalFiles = InventoryFile::count();
        $totalBytes = (int) InventoryFile::sum('file_size');
        $totalLocations = FileLocation::count();
        $totalStorages = StorageSource::count();

        $typeStats = InventoryFile::selectRaw('file_type_group, COUNT(*) as count, SUM(file_size) as size')
            ->groupBy('file_type_group')
            ->orderByDesc('count')
            ->get();

        $recentScans = ScanHistory::with('storageSource')
            ->latest()
            ->limit(5)
            ->get();

        $storages = StorageSource::withCount('locations')
            ->latest()
            ->get();

        $formattedTotalSize = $this->formatBytes($totalBytes);

        return [
            'totalFiles' => $totalFiles,
            'totalBytes' => $totalBytes,
            'formattedTotalSize' => $formattedTotalSize,
            'totalLocations' => $totalLocations,
            'totalStorages' => $totalStorages,
            'typeStats' => $typeStats,
            'recentScans' => $recentScans,
            'storages' => $storages,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Dashboard Inventaris Digital') }}</flux:heading>
            <flux:subheading>{{ __('Katalog terpusat metadata file dari berbagai sumber penyimpanan.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" icon="document-duplicate" :href="route('files.index')" wire:navigate>
                {{ __('Buka Katalog File') }}
            </flux:button>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2">
            <flux:text class="text-sm font-medium text-zinc-500">{{ __('Total File Unik') }}</flux:text>
            <div class="flex items-baseline justify-between">
                <flux:heading size="xl">{{ number_format($totalFiles) }}</flux:heading>
                <flux:badge color="blue" size="sm">{{ __('File Kanonikal') }}</flux:badge>
            </div>
            <flux:text class="text-xs text-zinc-400">{{ __('Deduplikasi via SHA-256') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-sm font-medium text-zinc-500">{{ __('Total Ukuran Terkatalog') }}</flux:text>
            <div class="flex items-baseline justify-between">
                <flux:heading size="xl">{{ $formattedTotalSize }}</flux:heading>
                <flux:badge color="purple" size="sm">{{ __('Volume') }}</flux:badge>
            </div>
            <flux:text class="text-xs text-zinc-400">{{ __('Berdasarkan ukuran file asli') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-sm font-medium text-zinc-500">{{ __('Titik Lokasi Fisik') }}</flux:text>
            <div class="flex items-baseline justify-between">
                <flux:heading size="xl">{{ number_format($totalLocations) }}</flux:heading>
                <flux:badge color="green" size="sm">{{ __('Multi-Lokasi') }}</flux:badge>
            </div>
            <flux:text class="text-xs text-zinc-400">{{ __('Tercatat di berbagai direktori') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-sm font-medium text-zinc-500">{{ __('Storage Sources') }}</flux:text>
            <div class="flex items-baseline justify-between">
                <flux:heading size="xl">{{ number_format($totalStorages) }}</flux:heading>
                <flux:badge color="amber" size="sm">{{ __('Sumber Aktif') }}</flux:badge>
            </div>
            <flux:text class="text-xs text-zinc-400">{{ __('Local & Network Storage') }}</flux:text>
        </flux:card>
    </div>

    <!-- File Types & Storage Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Distribution by Type -->
        <flux:card class="lg:col-span-1 space-y-4">
            <flux:heading size="lg">{{ __('Distribusi Jenis File') }}</flux:heading>
            @if($typeStats->isEmpty())
                <flux:text class="text-sm text-zinc-500 italic">{{ __('Belum ada file yang dipindai.') }}</flux:text>
            @else
                <div class="space-y-3">
                    @foreach($typeStats as $stat)
                        <div class="flex items-center justify-between text-sm">
                            <span class="capitalize font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $stat->file_type_group }}
                            </span>
                            <div class="flex items-center gap-3">
                                <span class="text-zinc-500">{{ number_format($stat->count) }} file</span>
                                <flux:badge size="sm" color="zinc">{{ $this->formatBytes((int)$stat->size) }}</flux:badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        <!-- Registered Storage Sources -->
        <flux:card class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Sumber Penyimpanan Terdaftar') }}</flux:heading>
                <flux:button size="sm" :href="route('storage-sources.index')" wire:navigate>{{ __('Kelola') }}</flux:button>
            </div>
            @if($storages->isEmpty())
                <flux:text class="text-sm text-zinc-500 italic">{{ __('Belum ada sumber penyimpanan yang didaftarkan.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500">
                            <tr>
                                <th class="pb-2">{{ __('Nama Storage') }}</th>
                                <th class="pb-2">{{ __('Driver') }}</th>
                                <th class="pb-2">{{ __('Base Path') }}</th>
                                <th class="pb-2 text-right">{{ __('File Tercatat') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($storages as $storage)
                                <tr>
                                    <td class="py-2.5 font-medium">{{ $storage->name }}</td>
                                    <td class="py-2.5"><flux:badge size="sm">{{ $storage->driver }}</flux:badge></td>
                                    <td class="py-2.5 text-zinc-500 font-mono text-xs">{{ $storage->base_path }}</td>
                                    <td class="py-2.5 text-right font-medium">{{ number_format($storage->locations_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    </div>

    <!-- Recent Scans -->
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Riwayat Scan Terkini') }}</flux:heading>
            <flux:button size="sm" :href="route('scan-histories.index')" wire:navigate>{{ __('Lihat Semua') }}</flux:button>
        </div>
        @if($recentScans->isEmpty())
            <flux:text class="text-sm text-zinc-500 italic">{{ __('Belum ada sesi pemindaian yang dicatat.') }}</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500">
                        <tr>
                            <th class="pb-2">{{ __('Tanggal') }}</th>
                            <th class="pb-2">{{ __('Storage') }}</th>
                            <th class="pb-2">{{ __('Path Dipindai') }}</th>
                            <th class="pb-2 text-center">{{ __('Ditemukan') }}</th>
                            <th class="pb-2 text-center">{{ __('File Baru') }}</th>
                            <th class="pb-2 text-center">{{ __('Lokasi Baru') }}</th>
                            <th class="pb-2 text-right">{{ __('Durasi') }}</th>
                            <th class="pb-2 text-right">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($recentScans as $scan)
                            <tr>
                                <td class="py-2.5 text-zinc-500">{{ $scan->created_at?->format('d M Y H:i') }}</td>
                                <td class="py-2.5 font-medium">{{ $scan->storageSource?->name ?? '-' }}</td>
                                <td class="py-2.5 text-zinc-500 font-mono text-xs">{{ $scan->scanned_path }}</td>
                                <td class="py-2.5 text-center">{{ $scan->files_found }}</td>
                                <td class="py-2.5 text-center text-green-600 font-medium">+{{ $scan->files_added }}</td>
                                <td class="py-2.5 text-center text-blue-600 font-medium">+{{ $scan->locations_added }}</td>
                                <td class="py-2.5 text-right">{{ $scan->duration_seconds }}s</td>
                                <td class="py-2.5 text-right">
                                    <flux:badge size="sm" :color="$scan->status === 'completed' ? 'green' : ($scan->status === 'running' ? 'blue' : 'red')">
                                        {{ $scan->status }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
