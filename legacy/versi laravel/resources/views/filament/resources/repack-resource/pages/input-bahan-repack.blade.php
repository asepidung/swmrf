<x-filament-panels::page>
    <style>
        /* Sembunyikan header bawaan Filament */
        .fi-header {
            display: none !important;
        }

        /* Gencet tabel Filament biar slim */
        .fi-ta-table tbody tr {
            height: 32px !important;
        }

        .fi-ta-table th,
        .fi-ta-table td {
            padding: 4px 8px !important;
            font-size: 11px !important;
        }

        .fi-ta-cell>div,
        .fi-ta-text,
        .fi-ta-text-item {
            padding: 0 !important;
            margin: 0 !important;
            line-height: 1.1 !important;
        }

        .fi-ta-actions button {
            width: 22px !important;
            height: 22px !important;
        }

        /* Layout utama halaman (Asli bawaan lu yang bener) */
        .layout-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .row-atas {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.25rem;
        }

        .row-bawah {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        @media (max-width: 768px) {

            .row-atas,
            .row-bawah {
                grid-template-columns: 1fr;
            }
        }

        /* Style untuk tabel rekap (Diambil dari desain lu) */
        .table-mini {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 1.5rem;
        }

        .table-mini th {
            background-color: #f8fafc;
            padding: 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
            font-weight: bold;
        }

        .table-mini td {
            padding: 6px;
            border: 1px solid #e2e8f0;
        }

        .dark .table-mini th {
            background-color: #1f2937;
            border-color: #374151;
        }

        .dark .table-mini td {
            border-color: #374151;
        }
    </style>

    <div class="layout-wrapper">

        <div class="row-atas">
            <div class="flex flex-col justify-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xl font-black uppercase tracking-wider text-gray-950 dark:text-white border-b pb-2 mb-1 dark:border-gray-800">
                    BATCH: <span class="text-warning-600 dark:text-warning-500">{{ $record->document_no }}</span>
                </div>
                <div class="flex gap-2">
                    <x-filament::button href="{{ \App\Filament\Resources\RepackResource::getUrl('index') }}" tag="a" color="gray" icon="heroicon-m-arrow-left" size="sm" class="flex-1 text-xs">
                        KEMBALI
                    </x-filament::button>
                    <x-filament::button href="{{ \App\Filament\Resources\RepackResource::getUrl('input-hasil', ['record' => $record->id]) }}" tag="a" color="info" icon="heroicon-o-qr-code" size="sm" class="flex-1 text-xs">
                        KE HASIL
                    </x-filament::button>
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 border-2 border-warning-500 dark:bg-gray-900 dark:ring-white/10 flex flex-col justify-center">
                <form wire:submit.prevent="submitBarcode" class="w-full">
                    {{ $this->form }}
                    <button type="submit" class="hidden">Scan</button>
                </form>
            </div>
        </div>

        <div class="row-bawah">

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="p-3 border-b dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800 text-xs font-bold uppercase tracking-tight text-gray-500 flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-4 h-4 text-warning-500" /> Histori Bahan Ter-Scan
                </div>
                {{ $this->table }}
            </div>

            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 sticky top-4">

                <div class="font-bold mb-2 text-sm text-gray-700 dark:text-gray-300">BAHAN MASUK</div>
                <table class="table-mini">
                    <thead>
                        <tr>
                            <th class="text-left">NAMA BARANG</th>
                            <th>BOX</th>
                            <th class="text-right">QTY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $bahan = \App\Models\RepackMaterial::with('product')->where('repack_id', $record->id)->get();
                        $groupedBahan = $bahan->groupBy(fn($item) => $item->product->name ?? 'Unknown');
                        $totalBahanBox = 0;
                        $totalBahanQty = 0;
                        @endphp
                        @forelse($groupedBahan as $name => $items)
                        @php
                        $box = $items->count();
                        $qty = $items->sum('weight');
                        $totalBahanBox += $box;
                        $totalBahanQty += $qty;
                        @endphp
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td>{{ $name }}</td>
                            <td class="text-center">{{ $box }}</td>
                            <td class="text-right">{{ number_format($qty, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center italic text-gray-400 py-3">Kosong</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="font-bold bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td class="text-right py-2">TOTAL</td>
                            <td class="text-center py-2 text-primary-600">{{ $totalBahanBox }}</td>
                            <td class="text-right py-2 text-primary-600">{{ number_format($totalBahanQty, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="font-bold mb-2 text-sm mt-5 text-gray-700 dark:text-gray-300">HASIL KELUAR</div>
                <table class="table-mini">
                    <thead>
                        <tr>
                            <th class="text-left">NAMA BARANG</th>
                            <th>BOX</th>
                            <th class="text-right">QTY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $hasil = \App\Models\RepackResult::with('product')->where('repack_id', $record->id)->get();
                        $groupedHasil = $hasil->groupBy(fn($item) => $item->product->name);
                        $totalHasilBox = 0;
                        $totalHasilQty = 0;
                        @endphp
                        @forelse($groupedHasil as $name => $items)
                        @php
                        $box = $items->count();
                        $qty = $items->sum('weight');
                        $totalHasilBox += $box;
                        $totalHasilQty += $qty;
                        @endphp
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td>{{ $name }}</td>
                            <td class="text-center">{{ $box }}</td>
                            <td class="text-right">{{ number_format($qty, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center italic text-gray-400 py-3">Kosong</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="font-bold bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td class="text-right py-2">TOTAL</td>
                            <td class="text-center py-2 text-info-600">{{ $totalHasilBox }}</td>
                            <td class="text-right py-2 text-info-600">{{ number_format($totalHasilQty, 2) }}</td>
                        </tr>
                        @php $balance = $totalHasilQty - $totalBahanQty; @endphp
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 {{ $balance < 0 ? 'bg-red-50 text-red-600 dark:bg-red-900/30' : 'bg-green-50 text-green-600 dark:bg-green-900/30' }}">
                            <td class="text-right py-2 uppercase tracking-wide">BALANCE</td>
                            <td colspan="2" class="text-right py-2 pr-2 text-sm">{{ number_format($balance, 2) }} Kg</td>
                        </tr>
                    </tfoot>
                </table>

            </div>
        </div>
    </div>

    <script>
        function focusScanner() {
            setTimeout(() => {
                const scanner = document.getElementById('scanner_input');
                if (scanner) scanner.focus();
            }, 100);
        }

        document.addEventListener('DOMContentLoaded', focusScanner);
        window.addEventListener('focus-scanner', focusScanner);
        document.addEventListener('refreshTable', focusScanner);
    </script>
</x-filament-panels::page>