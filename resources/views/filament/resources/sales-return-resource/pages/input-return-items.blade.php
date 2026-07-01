<style>
    /* Replace 'Summary' text with 'Grand Total' and color it yellow */
    .fi-ta-summary-header-cell {
        font-size: 0 !important;
    }
    .fi-ta-summary-header-cell::after {
        content: "Grand Total";
        font-size: 1rem !important;
        font-weight: bold !important;
        color: #eab308 !important;
    }
</style>

<x-filament-panels::page>
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
                Timbang Ulang
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
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('auto-print', (data) => {
                const url = data[0].url || data.url;
                
                let printIframe = document.getElementById('print-iframe');
                if (!printIframe) {
                    printIframe = document.createElement('iframe');
                    printIframe.id = 'print-iframe';
                    printIframe.style.display = 'none';
                    document.body.appendChild(printIframe);
                }

                printIframe.onload = function() {
                    try {
                        printIframe.contentWindow.focus();
                        printIframe.contentWindow.print();
                    } catch (e) {
                        console.error('Print failed:', e);
                    }
                };

                printIframe.src = url;
            });
        });
    </script>
</x-filament-panels::page>
