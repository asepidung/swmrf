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
                x-on:click="activeTab = 'weigh'"
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
        document.addEventListener('auto-print', (event) => {
            window.open(event.detail.url, '_blank');
        });
    </script>
</x-filament-panels::page>
