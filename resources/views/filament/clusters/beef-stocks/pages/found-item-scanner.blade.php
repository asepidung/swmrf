<x-filament-panels::page>
    <style>
        /* Force compact table rows on this page by overriding Tailwind's py-4 */
        .fi-ta-table .py-4 {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
    </style>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left Side: Barcode Scanner -->
        <div class="space-y-6 md:col-span-1">
            <div class="relative rounded-2xl bg-gradient-to-tr from-primary-500/20 via-transparent to-primary-500/10 p-[1px] shadow-sm">
                <div class="rounded-2xl bg-white p-6 dark:bg-gray-900">
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center p-3 mb-4 rounded-full bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                            <x-heroicon-o-viewfinder-circle class="w-8 h-8" />
                        </div>
                        <h2 class="text-xl font-bold">{{ __('Scan Barcode Temuan') }}</h2>
                        <p class="text-sm text-gray-500 mt-2">{{ __('Gunakan scanner untuk memindai barcode fisik pada barang yang tidak ada di stok.') }}</p>
                    </div>

                <form wire:submit.prevent="scan" x-data="{
                    focusInput() {
                        setTimeout(() => {
                            $refs.barcode_input.focus();
                        }, 50);
                    }
                }"
                x-init="focusInput()"
                @close-modal.window="focusInput()">
                    <div class="mb-4">
                        <input 
                            x-ref="barcode_input"
                            id="barcode_input"
                            type="text" 
                            wire:model="barcode"
                            placeholder="{{ __('SCAN BARCODE DISINI') }}" 
                            class="w-full rounded-lg shadow-sm text-center text-xl font-bold py-3 uppercase
                                border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            autofocus
                            autocomplete="off"
                        >
                    </div>
                    <!-- Hidden submit button just to ensure Enter key submits the form -->
                    <button type="submit" class="hidden"></button>
                </form>

                <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button wire:click.prevent="mountAction('manualInput')" type="button" class="w-full flex items-center justify-center gap-2 bg-warning-500 hover:bg-warning-400 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-all active:scale-95 group">
                        <x-heroicon-o-pencil-square class="w-5 h-5 transition-transform" />
                        {{ __('Label Rusak') }}
                    </button>
                </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Data Table -->
        <div class="md:col-span-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{ $this->table }}
            </div>
        </div>
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
