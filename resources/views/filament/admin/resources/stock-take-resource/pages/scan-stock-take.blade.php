<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left Side: Barcode Scanner & Stats -->
        <div class="space-y-6 md:col-span-1">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
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
                            placeholder="{{ $record->status === 'COMPLETED' ? __('Stock Opname Selesai') : __('Scan Barcode Here') }}" 
                            class="w-full rounded-lg shadow-sm text-center text-xl font-bold py-3 
                                {{ $record->status === 'COMPLETED' ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-500' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white' }}"
                            autofocus
                            autocomplete="off"
                            required
                            @if($record->status === 'COMPLETED') disabled @endif
                        >
                    </div>
                    <!-- Hidden submit button just to ensure Enter key submits the form -->
                    <button type="submit" class="hidden"></button>
                </form>
            </div>

            <!-- Stats -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-bold mb-4">{{ __('Opname Progress') }}</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">{{ __('Matched (Scanned)') }}</span>
                        <span class="font-bold text-success-600">{{ $this->getMatchedCount() }} items</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">{{ __('Missing (Waiting)') }}</span>
                        <a href="#" wire:click.prevent="mountAction('viewMissing')" class="inline-flex items-center gap-1 font-bold text-danger-600 hover:text-danger-500 transition-colors cursor-pointer group">
                            {{ $this->getMissingCount() }} items
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-danger-400 group-hover:translate-x-0.5 transition-transform" />
                        </a>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">{{ __('Unexpected (Found)') }}</span>
                        <a href="#" wire:click.prevent="mountAction('viewUnexpected')" class="inline-flex items-center gap-1 font-bold text-warning-600 hover:text-warning-500 transition-colors cursor-pointer group">
                            {{ $this->getUnexpectedCount() }} items
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-warning-400 group-hover:translate-x-0.5 transition-transform" />
                        </a>
                    </div>
                </div>

                @if($record->status === 'IN_PROGRESS')
                <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button wire:click.prevent="mountAction('manualInput', { barcode: '' })" type="button" class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-500 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-all active:scale-95 group">
                        <x-heroicon-o-plus-circle class="w-5 h-5 group-hover:rotate-90 transition-transform" />
                        {{ __('Input Barang Tanpa Label') }}
                    </button>
                </div>
                @endif
            </div>
        </div>

        <!-- Right Side: Data Table with Tabs -->
        <div class="md:col-span-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{ $this->table }}
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

        window.addEventListener('load', () => {
            const input = document.getElementById('barcode_input');
            if (input) input.focus();
        });
        
        document.addEventListener('livewire:init', () => {
            Livewire.on('focus-barcode', () => {
                setTimeout(() => {
                    const input = document.getElementById('barcode_input');
                    if (input) input.focus();
                }, 50);
            });
            
        document.addEventListener('auto-print', (event) => {
            const printUrl = event.detail[0]?.url || event.detail?.url;
            if (printUrl) {
                window.open(printUrl, '_blank');
            }
        });
        });
        
    </script>
</x-filament-panels::page>
