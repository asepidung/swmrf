<x-filament-panels::page>
    <div x-data="{ activeTab: 'scan' }">
        <x-filament::tabs label="Content tabs" class="mb-6">
            <x-filament::tabs.item
                :active="$activeTab === 'scan'"
                x-on:click="activeTab = 'scan'"
                icon="heroicon-o-qr-code"
            >
                Scan Karton Utuh
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'weigh'"
                x-on:click="activeTab = 'weigh'"
                icon="heroicon-o-scale"
            >
                Timbang Ulang
            </x-filament::tabs.item>
        </x-filament::tabs>

        <!-- Tab Scan -->
        <div x-show="activeTab === 'scan'" class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    Scan Barcode Karton Utuh
                </x-slot>
                
                <form wire:submit.prevent="processScan" class="space-y-6">
                    {{ $this->scanForm }}

                    <div class="flex justify-end hidden">
                        <button type="submit" id="submit_scan_btn"></button>
                    </div>
                </form>
            </x-filament::section>
        </div>

        <!-- Tab Weigh -->
        <div x-show="activeTab === 'weigh'" x-cloak class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    Timbang & Buat Label Baru
                </x-slot>
                
                <form wire:submit.prevent="processWeigh" class="space-y-6">
                    {{ $this->weighForm }}

                    <div class="flex justify-end hidden">
                        <button type="submit" id="submit_weigh_btn"></button>
                    </div>
                </form>
            </x-filament::section>
        </div>
    </div>

    <!-- Table -->
    <x-filament::section>
        <x-slot name="heading">
            Daftar Barang Retur (Sales Return No: {{ $record->return_number }})
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

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
