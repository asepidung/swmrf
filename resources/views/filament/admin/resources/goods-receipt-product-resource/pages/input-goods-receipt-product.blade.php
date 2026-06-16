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
                <x-filament::button href="{{ \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('drafts') }}" tag="a" color="gray" variant="outlined" icon="heroicon-m-arrow-left">
                    {{ __('Kembali ke Draft PO') }}
                </x-filament::button>
            </div>
            <div class="repack-header-actions">
                <div class="text-lg font-black uppercase tracking-wider text-gray-950 dark:text-white px-2">
                    PO: <span class="text-warning-600 dark:text-warning-500">{{ $record->purchaseProduct->po_number }}</span>
                    &nbsp;|&nbsp; GR: <span class="text-primary-600 dark:text-primary-500">{{ $record->gr_number }}</span>
                </div>
                <h4 class="text-info-600 dark:text-info-500 font-bold text-xl m-0 uppercase tracking-wide">
                    {{ __('INPUT GOODS RECEIPT BEEF') }}
                </h4>
            </div>
        </div>

        <div class="repack-grid">
            <!-- Left Column: Form Input Label (Col 3) -->
            <div class="repack-col-3 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">
                <div class="text-xs font-bold text-info-600 uppercase mb-4 border-b pb-2 flex items-center gap-2">
                    <x-heroicon-o-pencil-square class="w-4 h-4" /> {{ __('FORM INPUT PENERIMAAN') }}
                </div>
                <form wire:submit.prevent="create">
                    {{ $this->form }}
                    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <x-filament::button type="submit" color="info" class="w-full" icon="heroicon-m-check-circle" id="submit_btn_gr">
                            {{ __('PROSES / PRINT') }}
                        </x-filament::button>
                    </div>
                </form>
            </div>

            <!-- Middle Column: History table (Col 6) -->
            <div class="repack-col-6 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl overflow-hidden">
                <div class="bg-gray-50/80 dark:bg-gray-800 p-3 border-b dark:border-gray-800 text-xs font-bold uppercase tracking-tight text-gray-500 flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-4 h-4 text-info-500" /> {{ __('Daftar Barang yang Diterima') }}
                </div>
                {{ $this->table }}
            </div>

            <!-- Right Column: Summary & Finalization (Col 3) -->
            <div class="repack-col-3 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-4 pb-2 border-b dark:border-gray-800">
                    <x-heroicon-o-chart-bar-square class="w-5 h-5 text-warning-500" />
                    <span class="font-bold text-sm text-gray-800 dark:text-gray-200">{{ __('Fulfillment PO') }}</span>
                </div>

                <!-- PO Summary Table -->
                <div class="mb-4">
                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th class="text-left">{{ __('BARANG') }}</th>
                                <th class="w-16 text-center">{{ __('PO (Kg)') }}</th>
                                <th class="w-16 text-center">{{ __('RECV (Kg)') }}</th>
                                <th class="w-8 text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-300">
                            @php
                            $poItems = $record->purchaseProduct->items;
                            @endphp
                            @foreach($poItems as $poItem)
                                @php
                                $ordered = $poItem->qty;
                                $received = \App\Models\GoodsReceiptProductItem::whereHas('goodsReceiptProduct', function($q) use($record) {
                                    $q->where('purchase_product_id', $record->purchase_product_id);
                                })->where('product_id', $poItem->product_id)->sum('weight');
                                $done = $received >= $ordered;
                                @endphp
                                <tr>
                                    <td>{{ $poItem->product->name }}</td>
                                    <td class="text-center font-bold">{{ number_format($ordered, 2) }}</td>
                                    <td class="text-center font-bold {{ $done ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                        {{ number_format($received, 2) }}
                                    </td>
                                    <td class="text-center">
                                        @if($done)
                                            <x-heroicon-m-check-circle class="w-4 h-4 text-green-500 inline" />
                                        @else
                                            <x-heroicon-m-minus-circle class="w-4 h-4 text-amber-500 inline" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Grand Total Display -->
                @php
                $grSubtotal = \App\Models\GoodsReceiptProductItem::where('goods_receipt_product_id', $record->id)->sum('subtotal');
                $isTax11 = (bool) ($record->supplier->is_tax_11 ?? false);
                $tax = $isTax11 ? ($grSubtotal * 0.11) : 0;
                $netTotal = $grSubtotal + $tax;
                @endphp
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 flex flex-col gap-2">
                    <div class="flex justify-between items-center text-xs text-gray-500">
                        <span>Subtotal (GR):</span>
                        <span class="font-bold text-gray-800 dark:text-gray-200">Rp {{ number_format($grSubtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($isTax11)
                    <div class="flex justify-between items-center text-xs text-gray-500">
                        <span>PPN (11%):</span>
                        <span class="font-bold text-gray-800 dark:text-gray-200">Rp {{ number_format($tax, 0, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between items-center text-sm font-black border-t pt-2 dark:border-gray-700 text-primary-600 dark:text-primary-400">
                        <span>Net Total:</span>
                        <span>Rp {{ number_format($netTotal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Finalize Button -->
                <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <x-filament::button wire:click="completeGr" color="primary" class="w-full" icon="heroicon-m-lock-closed">
                        {{ __('Finalize Receipt') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <x-filament::modal id="partial-confirmation-modal" width="md">
        <x-slot name="heading">
            PO Quantity Not Fulfilled
        </x-slot>

        <x-slot name="description">
            Total kuantitas barang yang diterima di seluruh Goods Receipt untuk PO ini kurang dari kuantitas pemesanan awal. Apakah masih ada pengiriman parsial berikutnya?
        </x-slot>

        <x-slot name="footerActions">
            <x-filament::button wire:click="confirmPartial" color="info">
                Ya, Tunggu Parsial
            </x-filament::button>

            <x-filament::button wire:click="forceCompleted" color="success">
                Tidak, Tutup PO (Selesai)
            </x-filament::button>
            
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'partial-confirmation-modal' })">
                Batal
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <script>
        function focusField() {
            setTimeout(() => {
                const scanInput = document.getElementById('scan_barcode_field');
                if (scanInput && !scanInput.disabled) {
                    scanInput.focus();
                } else {
                    const qtyInput = document.getElementById('qty_input_field');
                    if (qtyInput && !qtyInput.disabled) {
                        qtyInput.focus();
                    }
                }
            }, 100);
        }
        document.addEventListener('DOMContentLoaded', focusField);
        window.addEventListener('auto-print', event => {
            if (event.detail.url) window.open(event.detail.url, '_blank');
            focusField();
        });
        document.addEventListener('refreshTable', focusField);
        
        // Listen to changes in input_mode to refocus appropriate field
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            succeed(({ snapshot, effect }) => {
                focusField();
            })
        });
    </script>
</x-filament-panels::page>
