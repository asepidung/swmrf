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
        <!-- Card Info Mutasi -->
        <x-filament::section class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 text-sm text-gray-700 dark:text-gray-300">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-gray-400" />
                    <span class="font-medium">Tanggal:</span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $record->mutation_date->format('d F Y') }}</span>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="font-medium">Dari:</span>
                    <span class="font-bold text-rose-600 dark:text-rose-400">{{ $record->fromWarehouse->name }}</span>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="font-medium">Tujuan:</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $record->toWarehouse->name }}</span>
                </div>
            </div>
        </x-filament::section>

        <!-- Card Scan -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border-2 border-primary-500 relative overflow-hidden p-6 flex flex-col justify-center mb-6">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-primary-500/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10 flex items-center gap-4">
                <div class="flex-shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 ring-4 ring-primary-50 dark:ring-primary-900/10">
                    <x-heroicon-o-qr-code class="w-6 h-6" />
                </div>
                <div class="flex-grow">
                    {{ $this->form }}
                </div>
                <div class="hidden sm:block text-sm text-gray-500 dark:text-gray-400">
                    Arahkan scanner atau ketik barcode secara manual
                </div>
            </div>
        </div>

        <!-- Bagian Bawah: Tabel -->
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
</x-filament-panels::page>
