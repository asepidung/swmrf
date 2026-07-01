<x-filament-panels::page>
    <style>
        .fi-header {
            display: none !important;
        }

        /* Auto hide sidebar and expand main content on this page */
        aside.fi-sidebar {
            display: none !important;
        }
        .fi-main-ctn {
            padding-inline-start: 0 !important;
        }
        :root {
            --sidebar-width: 0px !important;
            --collapsed-sidebar-width: 0px !important;
        }

        table.fi-ta-table th,
        table.fi-ta-table tbody td {
            padding-top: 0.25rem !important;
            padding-bottom: 0.25rem !important;
            padding-left: 8px !important;
            padding-right: 8px !important;
            height: auto !important;
        }

        table.fi-ta-table tbody td>div,
        table.fi-ta-table tbody td>div>div,
        table.fi-ta-table tbody td>div>div>div {
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            min-height: unset !important;
            line-height: 1.2 !important;
            gap: 0 !important;
        }

        .fi-ta-text,
        .fi-ta-text-item,
        .fi-ta-text-item-label {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 1.1 !important;
            font-size: 13px !important;
            white-space: nowrap !important;
            letter-spacing: -0.1px !important;
        }

        .fi-badge {
            padding: 2px 6px !important;
            min-height: 18px !important;
            line-height: 18px !important;
            font-size: 11px !important;
        }

        .fi-ta-actions {
            gap: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            justify-content: center !important;
        }

        .fi-ta-actions button {
            padding: 4px !important;
            min-height: 24px !important;
            height: 24px !important;
            width: 24px !important;
            margin: 0 !important;
        }

        .fi-ta-actions button svg {
            width: 16px !important;
            height: 16px !important;
        }
    </style>

    <div class="mb-6 flex items-center justify-between rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <x-filament::button
            href="{{ \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('input', ['record' => $record->id]) }}"
            tag="a"
            color="gray"
            icon="heroicon-m-arrow-left">
            {{ __('BACK') }}
        </x-filament::button>

        <div class="text-lg font-bold uppercase tracking-wider text-gray-950 dark:text-white">
            {{ __('SCAN GOODS RECEIPT') }}: <span class="text-primary-600 dark:text-primary-400">{{ $record->gr_number }}</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: start; width: 100%;">
        <!-- Left Side: Barcode Scanner & Scanned Items List -->
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <form wire:submit.prevent="scan">
                    <div class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label for="barcode_input" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ __('Scan Barcode Here') }}</label>
                            <input 
                                id="barcode_input"
                                type="text" 
                                wire:model="barcode"
                                placeholder="{{ __('Scan Barcode Here') }}" 
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-center text-xl font-bold py-3 uppercase tracking-wider"
                                autofocus
                                autocomplete="off"
                                required
                            >
                        </div>
                        <div class="w-1/3">
                            <label for="warehouse_select" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ __('Warehouse') }}</label>
                            <select 
                                id="warehouse_select" 
                                wire:model.live="warehouse_id"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-center text-sm font-semibold py-3"
                            >
                                @foreach(\App\Models\Warehouse::all() as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="hidden">Scan</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{ $this->table }}
            </div>
        </div>

        <!-- Right Side: PO Summary Table -->
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-lg font-bold mb-4">{{ __('PO Summary') }}</h3>
            <div class="overflow-x-auto font-sans">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 font-bold">
                            <th class="py-2">{{ __('Product') }}</th>
                            <th class="py-2 px-4 text-right">{{ __('Order Qty') }}</th>
                            <th class="py-2 px-4 text-right">{{ __('Receive Qty (Kg)') }}</th>
                            <th class="py-2 px-4 text-center">{{ __('Receive Pcs') }}</th>
                            <th class="py-2 pl-4 text-right">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $totalPo = 0;
                            $totalScan = 0;
                            $totalPcs = 0;
                        @endphp
                        @foreach($this->getSummaryData() as $row)
                            @php
                                $totalPo += $row['po_weight'];
                                $totalScan += $row['scanned_weight'];
                                $totalPcs += $row['scanned_pcs'];
                            @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-3 font-medium text-gray-900 dark:text-white">{{ $row['product_name'] }}</td>
                                <td class="py-3 px-4 text-right">{{ number_format($row['po_weight'], 2) }}</td>
                                <td class="py-3 px-4 text-right font-semibold text-primary-600 dark:text-primary-400">{{ number_format($row['scanned_weight'], 2) }}</td>
                                <td class="py-3 px-4 text-center">{{ $row['scanned_pcs'] }}</td>
                                <td class="py-3 pl-4 text-right font-medium {{ $row['balance'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                                    {{ number_format($row['balance'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @php
                            $totalBalance = $totalScan - $totalPo;
                        @endphp
                        <tr class="border-t-2 border-gray-200 dark:border-gray-800 font-bold text-gray-900 dark:text-white">
                            <td class="py-3">{{ __('Total') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($totalPo, 2) }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($totalScan, 2) }}</td>
                            <td class="py-3 px-4 text-center">{{ $totalPcs }}</td>
                            <td class="py-3 pl-4 text-right {{ $totalBalance > 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">
                                {{ number_format($totalBalance, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('focus-barcode', () => {
            setTimeout(() => {
                const input = document.getElementById('barcode_input');
                if (input) input.focus();
            }, 50);
        });

        // Initial focus on mount
        window.addEventListener('load', () => {
            const input = document.getElementById('barcode_input');
            if (input) input.focus();
        });
        
        // Livewire page update hook
        document.addEventListener('livewire:init', () => {
            Livewire.on('focus-barcode', () => {
                setTimeout(() => {
                    const input = document.getElementById('barcode_input');
                    if (input) input.focus();
                }, 50);
            });
        });

        // Auto collapse sidebar when loaded or nav update
        (function() {
            const collapseSidebar = () => {
                if (window.Alpine && window.Alpine.store('sidebar')) {
                    window.Alpine.store('sidebar').close();
                }
            };

            const interval = setInterval(() => {
                if (window.Alpine && window.Alpine.store('sidebar')) {
                    collapseSidebar();
                    clearInterval(interval);
                }
            }, 50);
            setTimeout(() => clearInterval(interval), 3000);

            document.addEventListener('livewire:navigated', collapseSidebar);
            document.addEventListener('livewire:init', collapseSidebar);
        })();
    </script>
</x-filament-panels::page>
