<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left Side: Barcode Scanner & Stats -->
        <div class="space-y-6 md:col-span-1">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <form wire:submit.prevent="scan">
                    <div class="mb-4">
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
                    
                    <div class="flex gap-2">
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-500 text-white font-bold py-2 px-4 rounded-lg">
                            {{ __('Scan') }}
                        </button>
                    </div>
                </form>
                
                <div class="mt-4">
                    {{ $this->manualInputAction }}
                </div>
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
                        <span class="font-bold text-danger-600">{{ $this->getMissingCount() }} items</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">{{ __('Unexpected (Found)') }}</span>
                        <span class="font-bold text-warning-600">{{ $this->getUnexpectedCount() }} items</span>
                    </div>
                </div>
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
        });
        
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
