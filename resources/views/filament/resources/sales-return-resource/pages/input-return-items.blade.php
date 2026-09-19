<x-filament-panels::page>
<style>
    /* Replace 'Summary' text with 'Grand Total' and color it yellow */
    .fi-ta-summary-row-heading {
        font-size: 0 !important;
    }
    .fi-ta-summary-row-heading::after {
        content: "Grand Total";
        font-size: 0.875rem !important;
        font-weight: bold !important;
        color: #eab308 !important;
        visibility: visible !important;
        display: block !important;
    }
</style>

    @include('filament.admin.partials.mobile-not-supported-warning', [
        'backUrl' => \App\Filament\Admin\Resources\SalesReturnResource::getUrl('index'),
    ])

    @php($claimSummary = $this->claimSummary())
    @if ($claimSummary->isNotEmpty())
        <div class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Claim vs Physical Summary') }}</div>
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-4 py-2 text-left">{{ __('Product') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Claimed (Kg)') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Physical (Kg)') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Variance (Kg)') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($claimSummary as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-4 py-2">{{ $row['product_name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row['claimed'], 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row['physical'], 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row['variance'], 2) }}</td>
                            <td class="px-4 py-2">
                                @if ($row['received_without_claim'])
                                    <span class="inline-flex items-center rounded-md border border-warning-200 bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 dark:border-warning-800 dark:bg-warning-900/20 dark:text-warning-400">
                                        {{ __('Received without claim') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div x-data="{ activeTab: 'scan' }">
        <x-filament::tabs label="Content tabs" class="mb-8">
            <x-filament::tabs.item
                alpine-active="activeTab === 'scan'"
                x-on:click="activeTab = 'scan'"
                icon="heroicon-o-qr-code"
            >
                Scan Mode
            </x-filament::tabs.item>

            <x-filament::tabs.item
                alpine-active="activeTab === 'weigh'"
                x-on:click="activeTab = 'weigh'; setTimeout(() => { window.dispatchEvent(new Event('refreshTable')) }, 100)"
                icon="heroicon-o-scale"
            >
                Relabel Mode
            </x-filament::tabs.item>
        </x-filament::tabs>

        <div class="mt-6" x-bind:style="activeTab === 'weigh' ? 'display: grid; grid-template-columns: 32% 1fr; gap: 1.5rem; align-items: start; width: 100%;' : 'display: flex; flex-direction: column; gap: 1.5rem; width: 100%;'">
            <!-- Left Column: Forms -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" 
                 x-bind:style="activeTab === 'weigh' ? 'position: sticky; top: 1.5rem;' : ''">
                <!-- Tab Scan Form -->
                <div x-show="activeTab === 'scan'">
                    <form wire:submit.prevent="processScan">
                        {{ $this->scanForm }}
                        <div class="flex justify-end hidden">
                            <button type="submit" id="submit_scan_btn"></button>
                        </div>
                    </form>
                </div>

                <!-- Tab Weigh Form -->
                <div x-show="activeTab === 'weigh'" x-cloak>
                    <form wire:submit.prevent="processWeigh">
                        {{ $this->weighForm }}
                        <div class="flex justify-end hidden">
                            <button type="submit" id="submit_weigh_btn"></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{ $this->table }}
            </div>
        </div>
    </div>

    <!-- Auto Print Script -->
    <script>
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                const activeEl = document.activeElement;
                if (activeEl && activeEl.closest('.product-select-container')) {
                    e.preventDefault();
                    const qtyInput = document.getElementById('qty_input_field');
                    if (qtyInput) qtyInput.focus();
                }
            }
        });

        window.addEventListener('refreshTable', () => {
            setTimeout(() => {
                const qtyInput = document.getElementById('qty_input_field');
                if (qtyInput) qtyInput.value = '';

                const productContainer = document.querySelector('.product-select-container');
                if (productContainer) {
                    const focusTarget = productContainer.querySelector('button, input');
                    if (focusTarget) focusTarget.focus();
                }
            }, 100);
        });

        window.addEventListener('auto-print', (event) => {
            window.open(event.detail.url, '_blank');
        });
    </script>
</x-filament-panels::page>
