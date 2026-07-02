<x-filament-panels::page>
    <div x-data="{
        init() {
            window.addEventListener('focus-barcode', () => {
                setTimeout(() => {
                    if ($refs.barcodeInput) {
                        $refs.barcodeInput.focus();
                    }
                }, 100);
            });
        }
    }" class="space-y-6">

        <!-- Info & Scan Card -->
        <x-filament::section class="border-2 border-primary-500 shadow-sm relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-primary-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -left-12 -bottom-12 w-32 h-32 bg-primary-500/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row gap-8 md:items-end justify-between">
                <!-- Info Meta -->
                <div class="flex flex-wrap items-center gap-6 pb-2">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tanggal</p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $record->mutation_date->format('d M Y') }}</p>
                    </div>
                    
                    <!-- Divider -->
                    <div class="hidden md:block w-px h-8 bg-gray-200 dark:bg-white/10"></div>
                    
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dari Gudang</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="flex w-2 h-2 rounded-full bg-rose-500"></span>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $record->fromWarehouse->name }}</p>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="hidden md:block w-px h-8 bg-gray-200 dark:bg-white/10"></div>

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ke Gudang</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="flex w-2 h-2 rounded-full bg-emerald-500"></span>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $record->toWarehouse->name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Scan Input -->
                <div class="w-full md:w-96 shrink-0">
                    {{ $this->form }}
                </div>
            </div>
        </x-filament::section>

        <!-- Tabel Barang -->
        <div class="mt-4">
            {{ $this->table }}
        </div>
        
    </div>
</x-filament-panels::page>
