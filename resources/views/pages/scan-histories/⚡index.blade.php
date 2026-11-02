<?php

use App\Models\ScanHistory;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat Scan')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        $histories = ScanHistory::with(['storageSource', 'user'])
            ->latest()
            ->paginate(15);

        return [
            'histories' => $histories,
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Riwayat Pemindaian Direktori') }}</flux:heading>
        <flux:subheading>{{ __('Log audit lengkap sesi pemindaian file manual di semua lokasi penyimpanan.') }}</flux:subheading>
    </div>

    <flux:card class="p-0 overflow-hidden">
        @if($histories->isEmpty())
            <div class="p-12 text-center text-zinc-500 italic">
                {{ __('Belum ada riwayat pemindaian yang dicatat.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                        <tr>
                            <th class="py-3 px-4">{{ __('Waktu') }}</th>
                            <th class="py-3 px-4">{{ __('Storage') }}</th>
                            <th class="py-3 px-4">{{ __('Path Dipindai') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('Ditemukan') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('File Baru') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('Lokasi Baru') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('Tak Berubah') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Durasi') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($histories as $history)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition">
                                <td class="py-3 px-4 text-zinc-500 whitespace-nowrap">
                                    {{ $history->created_at?->format('d M Y H:i:s') }}
                                </td>
                                <td class="py-3 px-4 font-medium whitespace-nowrap">
                                    {{ $history->storageSource?->name ?? 'Dihapus' }}
                                </td>
                                <td class="py-3 px-4 font-mono text-xs max-w-xs truncate" title="{{ $history->scanned_path }}">
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="sm" :color="$history->scan_mode === 'folder' ? 'amber' : 'zinc'">
                                            {{ $history->scan_mode === 'folder' ? 'Folder' : 'File' }}
                                        </flux:badge>
                                        <span class="truncate">{{ $history->scanned_path }}</span>
                                    </div>
                                    @if($history->scan_mode === 'file' && ! $history->is_recursive)
                                        <span class="text-zinc-400 font-sans text-xs ml-1">(Non-rekursif)</span>
                                    @endif
                                    @if($history->allowed_extensions)
                                        <div class="text-[10px] text-zinc-400 font-sans mt-0.5">Ekstensi: {{ $history->allowed_extensions }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center font-medium">{{ $history->files_found }}</td>
                                <td class="py-3 px-4 text-center text-green-600 font-medium">+{{ $history->files_added }}</td>
                                <td class="py-3 px-4 text-center text-blue-600 font-medium">+{{ $history->locations_added }}</td>
                                <td class="py-3 px-4 text-center text-zinc-400">{{ $history->files_unmodified }}</td>
                                <td class="py-3 px-4 text-right text-zinc-500 whitespace-nowrap">{{ $history->duration_seconds }}s</td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <flux:badge size="sm" :color="$history->status === 'completed' ? 'green' : ($history->status === 'running' ? 'blue' : 'red')">
                                        {{ $history->status }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
                {{ $histories->links() }}
            </div>
        @endif
    </flux:card>
</div>
