<x-filament-panels::page>
    <div 
        x-data="{ barcode: @entangle('barcode') }"
        x-on:focus-barcode.window="$refs.barcodeInput.focus()"
        x-init="setTimeout(() => $refs.barcodeInput.focus(), 500)"
        class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-6 max-w-3xl mx-auto w-full"
    >
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                {{ __('Scan Barcode Temuan Barang') }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2">
                {{ __('Gunakan scanner untuk memindai barcode fisik pada barang yang tidak ada di stok.') }}
            </p>
        </div>

        <form wire:submit.prevent="scan" class="flex flex-col items-center gap-4">
            <input 
                x-ref="barcodeInput"
                type="text" 
                x-model="barcode"
                class="w-full max-w-lg text-center text-3xl p-4 border-2 border-primary-500 rounded-lg focus:ring-4 focus:ring-primary-500/20 bg-gray-50 dark:bg-gray-800 dark:text-white font-mono placeholder-gray-300 dark:placeholder-gray-600 uppercase"
                placeholder="{{ __('SCAN BARCODE DISINI') }}"
                autofocus
                autocomplete="off"
            >
            
            <div class="flex gap-3 mt-4 justify-center">
                {{ $this->manualInputAction }}
            </div>
        </form>

    </div>

    <x-filament-actions::modals />

    <script>
        document.addEventListener('livewire:init', () => {
            document.addEventListener('auto-print', (event) => {
                const printUrl = event.detail[0]?.url || event.detail?.url;
                if (printUrl) {
                    window.open(printUrl, '_blank');
                }
            });
        });
    </script>
</x-filament-panels::page>
