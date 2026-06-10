<x-filament-panels::page>
    <style>
        /* 1. HIDE SIDEBAR & TOPBAR KHUSUS HALAMAN INI BIAR FULL SCREEN */
        aside.fi-sidebar {
            display: none !important;
        }

        .fi-topbar {
            display: none !important;
        }

        main.fi-main {
            padding-left: 0 !important;
            padding-top: 0 !important;
        }

        .fi-header {
            display: none !important;
        }

        /* 2. GENCET TABEL HISTORY (TENGAH) BIAR SLIM */
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

        /* 3. STYLE TABEL REKAP MINI (KANAN) */
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

        /* 4. CUSTOM GRID SYSTEM BIAR TIDAK TERGANTUNG TAILWIND COMPILE */
        .repack-grid {
            display: grid !important;
            grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
            gap: 16px !important;
            align-items: start !important;
            width: 100% !important;
        }

        .repack-col-3 {
            grid-column: span 3 / span 3 !important;
        }

        .repack-col-6 {
            grid-column: span 6 / span 6 !important;
        }

        .repack-col-9 {
            grid-column: span 9 / span 9 !important;
        }

        .repack-col-12 {
            grid-column: span 12 / span 12 !important;
        }

        @media (max-width: 1024px) {
            .repack-col-3, .repack-col-6, .repack-col-9 {
                grid-column: span 12 / span 12 !important;
            }
        }
        /* 5. CUSTOM WRAPPER PADDING */
        .repack-wrapper {
            padding: 16px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* 6. CUSTOM HEADER STYLES */
        .repack-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding-bottom: 12px !important;
            margin-bottom: 16px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            width: 100% !important;
        }
        
        .dark .repack-header {
            border-bottom-color: #374151 !important;
        }

        .repack-header-actions {
            display: flex !important;
            gap: 8px !important;
            align-items: center !important;
        }
    </style>

    <div class="repack-wrapper flex flex-col gap-4">
        <!-- Header Banner Sleek & Simple -->
        <div class="repack-header">
            <div class="repack-header-actions">
                <x-filament::button href="{{ \App\Filament\Admin\Resources\RepackResource::getUrl('index') }}" tag="a" color="gray" variant="outlined" icon="heroicon-m-arrow-left">
                    {{ __('Kembali') }}
                </x-filament::button>
                <x-filament::button href="{{ \App\Filament\Admin\Resources\RepackResource::getUrl('input-bahan', ['record' => $record->id]) }}" tag="a" color="success" variant="outlined" icon="heroicon-m-arrow-right">
                    {{ __('Tambah Bahan') }}
                </x-filament::button>
            </div>
            <div class="repack-header-actions">
                <div class="text-lg font-black uppercase tracking-wider text-gray-950 dark:text-white px-2">
                    BATCH: <span class="text-warning-600 dark:text-warning-500">{{ $record->doc_no }}</span>
                </div>
                <h4 class="text-info-600 dark:text-info-500 font-bold text-xl m-0 uppercase tracking-wide">
                    {{ __('INPUT HASIL & LABELING') }}
                </h4>
            </div>
        </div>

        <div class="repack-grid">
            <!-- Left Column: Form Input Label (Col 3) -->
            <div class="repack-col-3 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">
                <div class="text-xs font-bold text-info-600 uppercase mb-4 border-b pb-2 flex items-center gap-2">
                    <x-heroicon-o-pencil-square class="w-4 h-4" /> {{ __('FORM INPUT LABEL') }}
                </div>
                <form wire:submit.prevent="create">
                    {{ $this->form }}
                    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <x-filament::button type="submit" color="info" class="w-full" icon="heroicon-m-printer" id="submit_btn_label">
                            {{ __('PRINT LABEL') }}
                        </x-filament::button>
                    </div>
                </form>
            </div>

            <!-- Middle Column: History table (Col 6) -->
            <div class="repack-col-6 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl overflow-hidden">
                <div class="bg-gray-50/80 dark:bg-gray-800 p-3 border-b dark:border-gray-800 text-xs font-bold uppercase tracking-tight text-gray-500 flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-4 h-4 text-info-500" /> {{ __('Histori Hasil Scan') }}
                </div>
                {{ $this->table }}
            </div>

            <!-- Right Column: Sleek Summary Lists (Col 3) -->
            <div class="repack-col-3 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-4 pb-2 border-b dark:border-gray-800">
                    <x-heroicon-o-chart-bar-square class="w-5 h-5 text-warning-500" />
                    <span class="font-bold text-sm text-gray-800 dark:text-gray-200">{{ __('RINGKASAN PROSES') }}</span>
                </div>

                <!-- Bahan Masuk Summary Table -->
                <div class="mb-4">
                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">{{ __('BAHAN MASUK (INPUT)') }}</div>
                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th class="text-left">{{ __('NAMA BARANG') }}</th>
                                <th class="w-12 text-center">{{ __('BOX') }}</th>
                                <th class="w-20 text-right">{{ __('QTY') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-300">
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
                                <tr>
                                    <td>{{ $name }}</td>
                                    <td class="text-center">{{ $box }}</td>
                                    <td class="text-right">{{ number_format($qty, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center italic text-gray-500 py-3">{{ __('Belum ada bahan') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="font-bold bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white">
                            <tr>
                                <td class="text-right py-2">{{ __('TOTAL') }}</td>
                                <td class="text-center py-2">{{ $totalBahanBox }}</td>
                                <td class="text-right py-2">{{ number_format($totalBahanQty, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Hasil Keluar Summary Table -->
                <div class="mb-4">
                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">{{ __('HASIL KELUAR (OUTPUT)') }}</div>
                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th class="text-left">{{ __('NAMA BARANG') }}</th>
                                <th class="w-12 text-center">{{ __('BOX') }}</th>
                                <th class="w-20 text-right">{{ __('QTY') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-300">
                            @php
                            $hasil = \App\Models\RepackResult::with('product')->where('repack_id', $record->id)->get();
                            $groupedHasil = $hasil->groupBy(fn($item) => $item->product->name ?? 'Unknown');
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
                                <tr>
                                    <td>{{ $name }}</td>
                                    <td class="text-center">{{ $box }}</td>
                                    <td class="text-right">{{ number_format($qty, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center italic text-gray-500 py-3">{{ __('Belum ada hasil') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="font-bold bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white">
                            <tr>
                                <td class="text-right py-2">{{ __('TOTAL') }}</td>
                                <td class="text-center py-2">{{ $totalHasilBox }}</td>
                                <td class="text-right py-2">{{ number_format($totalHasilQty, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Lost / Balance -->
                @php $balance = $totalHasilQty - $totalBahanQty; @endphp
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-between items-center">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('SELISIH (BALANCE)') }}</span>
                    <span class="text-sm font-black px-3 py-1 rounded-lg {{ $balance < 0 ? 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400' : 'bg-green-50 text-green-600 dark:bg-green-950/30 dark:text-green-400' }}">
                        {{ number_format($balance, 2) }} Kg
                    </span>
                </div>
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
