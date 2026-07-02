<x-filament-panels::page>
    <div x-data="{
        init() {
            setTimeout(() => {
                if ($refs.barcodeInput) {
                    $refs.barcodeInput.focus();
                }
            }, 300);
            window.addEventListener('focus-barcode', () => {
                setTimeout(() => {
                    if ($refs.barcodeInput) {
                        $refs.barcodeInput.focus();
                    }
                }, 100);
            });
        }
    }">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
            <!-- Bagian Kiri: Info & Form Scan -->
            <div class="xl:col-span-1 space-y-6">
                <!-- Card Info Mutasi -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-information-circle class="w-5 h-5 text-gray-500" />
                            <span>Detail Mutasi</span>
                        </div>
                    </x-slot>
                    
                    <div class="flex flex-wrap items-center gap-x-8 gap-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tanggal</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->mutation_date->format('d F Y') }}</p>
                        </div>
                        
                        <div class="hidden sm:block w-px h-8 bg-gray-200 dark:bg-white/10"></div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dari Gudang</p>
                            <div class="flex items-center gap-2">
                                <span class="flex w-2 h-2 rounded-full bg-rose-500"></span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->fromWarehouse->name }}</p>
                            </div>
                        </div>

                        <div class="hidden sm:block w-px h-8 bg-gray-200 dark:bg-white/10"></div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ke Gudang</p>
                            <div class="flex items-center gap-2">
                                <span class="flex w-2 h-2 rounded-full bg-emerald-500"></span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->toWarehouse->name }}</p>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                <!-- Card Scan -->
                <x-filament::section class="border-2 border-primary-500 shadow-sm shadow-primary-500/20 relative overflow-hidden">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary-500/10 rounded-full blur-2xl"></div>
                    
                    <div class="text-center mb-6 relative z-10">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 mb-3 ring-4 ring-primary-50 dark:ring-primary-900/10">
                            <x-heroicon-o-qr-code class="w-7 h-7" />
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Scan Barang</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Arahkan scanner atau ketik barcode secara manual</p>
                    </div>
                    
                    <div class="relative z-10">
                        {{ $this->form }}
                    </div>
                </x-filament::section>
            </div>

            <!-- Bagian Kanan: Tabel -->
            <div class="xl:col-span-2">
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-list-bullet class="w-5 h-5 text-gray-500" />
                            <span>Daftar Barang yang Di-scan</span>
                        </div>
                    </x-slot>
                    
                    <div class="mt-2">
                        {{ $this->table }}
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
