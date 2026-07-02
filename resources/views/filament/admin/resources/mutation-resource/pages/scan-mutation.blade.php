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
    }">
        <div class="mb-6">
            {{ $this->form }}
        </div>
        
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
