<x-filament-panels::page>
    <x-filament-panels::form wire:submit="processSave">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

    {{--
        Pertanyaan saat barang yang datang kurang dari yang dipesan.

        Tiga hal diperbaiki di sini.

        Teksnya dulu ditulis langsung tanpa __(), jadi tetap berbahasa Inggris
        apa pun bahasa yang dipilih -- dan tidak ada error yang memberitahu.

        Warnanya dulu menyesatkan: tombol "tutup PO" berwarna HIJAU, sehingga
        terbaca sebagai pilihan yang aman dan dianjurkan. Padahal justru itu
        yang menutup pintu -- sisa pesanan tidak akan diterima lagi. Yang hijau
        seharusnya yang bisa dibatalkan, bukan yang mengakhiri.

        Dan akibatnya kalimatnya kurang: pertanyaannya tidak menyebutkan
        konsekuensi menutup PO sama sekali.
    --}}
    <x-filament::modal id="partial-confirmation-modal" width="lg">
        <x-slot name="heading">
            {{ __('Received quantity is less than ordered') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Is the rest still coming in a later delivery? Closing the purchase order means the remaining quantity will never be received.') }}
        </x-slot>

        <x-slot name="footerActions">
            {{-- Pilihan yang bisa dibatalkan: PO tetap terbuka. --}}
            <x-filament::button wire:click="confirmPartial" color="primary">
                {{ __('Yes, wait for the rest') }}
            </x-filament::button>

            {{-- Menutup PO. Diberi warna peringatan, bukan hijau, karena
                 sesudah ini sisa pesanan tidak bisa diterima lagi. --}}
            <x-filament::button wire:click="forceCompleted" color="warning">
                {{ __('No, close this purchase order') }}
            </x-filament::button>

            <x-filament::button
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'partial-confirmation-modal' })"
            >
                {{ __('Cancel') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
