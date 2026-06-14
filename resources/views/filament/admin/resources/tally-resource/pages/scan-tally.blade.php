<x-filament-panels::page>
    <!-- Original split scan layout -->
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
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-center text-xl font-bold py-3"
                                autofocus
                                autocomplete="off"
                                required
                            >
                        </div>
                        <div class="w-1/3">
                            <label for="pod_limit_input" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Max POD Age (Days)</label>
                            <input 
                                id="pod_limit_input"
                                type="number" 
                                required
                                min="0"
                                wire:model.live="podLimit"
                                placeholder="Limit" 
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-center text-xl font-bold py-3"
                            >
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
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400">
                            <th class="py-2">{{ __('Product') }}</th>
                            <th class="py-2 text-right">{{ __('PO Weight') }}</th>
                            <th class="py-2 text-right">{{ __('Qty Scan') }}</th>
                            <th class="py-2 text-center">{{ __('Box') }}</th>
                            <th class="py-2 text-right">{{ __('Balance') }}</th>
                            <th class="py-2 pl-4">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $totalPo = 0;
                            $totalScan = 0;
                            $totalBox = 0;
                        @endphp
                        @foreach($this->getSummaryData() as $row)
                            @php
                                $totalPo += $row['po_weight'];
                                $totalScan += $row['scanned_weight'];
                                $totalBox += $row['scanned_box'];
                            @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-3 font-medium text-gray-900 dark:text-white">{{ $row['product_name'] }}</td>
                                <td class="py-3 text-right">{{ number_format($row['po_weight'], 2) }}</td>
                                <td class="py-3 text-right font-semibold text-primary-600 dark:text-primary-400">{{ number_format($row['scanned_weight'], 2) }}</td>
                                <td class="py-3 text-center">{{ $row['scanned_box'] }}</td>
                                <td class="py-3 text-right font-medium {{ $row['balance'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                                    {{ number_format($row['balance'], 2) }}
                                </td>
                                <td class="py-3 pl-4 text-gray-500 dark:text-gray-400 text-xs">{{ $row['notes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @php
                            $totalBalance = $totalScan - $totalPo;
                        @endphp
                        <tr class="border-t-2 border-gray-200 dark:border-gray-800 font-bold text-gray-900 dark:text-white">
                            <td class="py-3">{{ __('Total') }}</td>
                            <td class="py-3 text-right">{{ number_format($totalPo, 2) }}</td>
                            <td class="py-3 text-right">{{ number_format($totalScan, 2) }}</td>
                            <td class="py-3 text-center">{{ $totalBox }}</td>
                            <td class="py-3 text-right {{ $totalBalance > 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">
                                {{ number_format($totalBalance, 2) }}
                            </td>
                            <td></td>
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

        document.addEventListener('auto-print', (event) => {
            window.open(event.detail.url, '_blank');
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

            // Poll until Alpine is ready
            const interval = setInterval(() => {
                if (window.Alpine && window.Alpine.store('sidebar')) {
                    collapseSidebar();
                    clearInterval(interval);
                }
            }, 50);
            setTimeout(() => clearInterval(interval), 3000);

            // Also hook into livewire navigates
            document.addEventListener('livewire:navigated', collapseSidebar);
            document.addEventListener('livewire:init', collapseSidebar);
        })();
    </script>
</x-filament-panels::page>
