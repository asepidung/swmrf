<x-filament-panels::page>
    <div x-data="{ activeTab: 'scan' }">
        <x-filament::tabs label="Content tabs" class="mb-6">
            <x-filament::tabs.item
                alpine-active="activeTab === 'scan'"
                x-on:click="activeTab = 'scan'"
                icon="heroicon-o-qr-code"
            >
                Scan Karton Utuh
            </x-filament::tabs.item>

            <x-filament::tabs.item
                alpine-active="activeTab === 'weigh'"
                x-on:click="activeTab = 'weigh'"
                icon="heroicon-o-scale"
            >
                Timbang Ulang
            </x-filament::tabs.item>
        </x-filament::tabs>

        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-start;">
            <!-- Left Column: Forms -->
            <div style="flex: 1 1 350px;">
                <!-- Tab Scan Form -->
                <div x-show="activeTab === 'scan'">
                    <x-filament::section>
                        <form wire:submit.prevent="processScan">
                            {{ $this->scanForm }}

                            <div class="flex justify-end hidden">
                                <button type="submit" id="submit_scan_btn"></button>
                            </div>
                        </form>
                    </x-filament::section>
                </div>

                <!-- Tab Weigh Form -->
                <div x-show="activeTab === 'weigh'" x-cloak>
                    <x-filament::section>
                        <form wire:submit.prevent="processWeigh">
                            {{ $this->weighForm }}

                            <div class="flex justify-end hidden">
                                <button type="submit" id="submit_weigh_btn"></button>
                            </div>
                        </form>
                    </x-filament::section>
                </div>
            </div>

            <!-- Right Column: Table -->
            <div style="flex: 1 1 600px;">
                <x-filament::section>
                    {{ $this->table }}
                </x-filament::section>
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
