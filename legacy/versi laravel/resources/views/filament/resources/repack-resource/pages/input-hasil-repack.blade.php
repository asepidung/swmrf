<x-filament-panels::page>
    <style>
        /* 1. HANCURKAN KEKANGAN LAYOUT FILAMENT */
        aside.fi-sidebar {
            display: none !important;
        }

        /* Buang Sidebar */
        header.fi-topbar {
            display: none !important;
        }

        /* Buang Topbar */
        .fi-header {
            display: none !important;
        }

        /* Buang Header Judul */

        /* Paksa main area jadi 100% Full Width mentok ujung layar */
        main.fi-main {
            padding: 15px !important;
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
        }

        .fi-page {
            max-width: 100% !important;
            width: 100% !important;
        }

        /* 2. LAYOUT GRID ALA BOOTSTRAP (1 ROW) */
        .my-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 15px;
            width: 100%;
            align-items: flex-start;
        }

        .my-col-3 {
            width: 25%;
        }

        .my-col-6 {
            width: 50%;
        }

        /* Kalau layarnya kecil, otomatis turun ke bawah (Responsive) */
        @media (max-width: 1024px) {
            .my-row {
                flex-wrap: wrap;
            }

            .my-col-3,
            .my-col-6 {
                width: 100%;
            }
        }

        /* 3. GENCET TABEL FILAMENT (TENGAH) BIAR SLIM */
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

        /* 4. STYLE TABEL REKAP (KANAN) */
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

    <div class="w-full">

        <div class="flex items-center justify-between mb-4 pb-3 border-b-2 border-gray-200 dark:border-gray-800">
            <div class="flex gap-2">
                <x-filament::button href="{{ \App\Filament\Resources\RepackResource::getUrl('index') }}" tag="a" color="primary" variant="outlined" icon="heroicon-m-arrow-left">
                    KEMBALI
                </x-filament::button>
                <x-filament::button href="{{ \App\Filament\Resources\RepackResource::getUrl('input-bahan', ['record' => $record->id]) }}" tag="a" color="success" variant="outlined" icon="heroicon-m-arrow-right">
                    TAMBAH BAHAN
                </x-filament::button>
            </div>
            <div>
                <h4 class="text-primary-600 font-black text-xl m-0 uppercase tracking-widest">
                    PRINT LABEL HASIL - {{ $record->document_no }}
                </h4>
            </div>
        </div>

        <div class="my-row">

            <div class="my-col-3 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">
                <div class="text-xs font-bold text-info-600 uppercase mb-4 border-b pb-2 flex items-center gap-2">
                    <x-heroicon-o-pencil-square class="w-4 h-4" /> FORM INPUT LABEL
                </div>
                <form wire:submit.prevent="create">
                    {{ $this->form }}
                    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <x-filament::button type="submit" color="info" class="w-full" icon="heroicon-m-printer" id="submit_btn_label">
                            PRINT LABEL
                        </x-filament::button>
                    </div>
                </form>
            </div>

            <div class="my-col-6 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl overflow-hidden">
                <div class="bg-gray-50/80 dark:bg-gray-800 p-2 border-b dark:border-gray-800 text-[11px] font-bold uppercase tracking-widest text-gray-500 text-center">
                    Histori Hasil Scan
                </div>
                {{ $this->table }}
            </div>

            <div class="my-col-3 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">

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
        function focusInput() {
            setTimeout(() => {
                const qtyInput = document.getElementById('qty_input_field');
                if (qtyInput) qtyInput.focus();
            }, 100);
        }
        document.addEventListener('DOMContentLoaded', focusInput);
        window.addEventListener('auto-print', event => {
            if (event.detail.url) window.open(event.detail.url, '_blank');
            focusInput();
        });
        document.addEventListener('refreshTable', focusInput);
    </script>
</x-filament-panels::page>