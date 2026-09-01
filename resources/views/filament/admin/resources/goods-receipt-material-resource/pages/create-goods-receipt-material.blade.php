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
        yang menutup pintu -- sisa pesanan tidak akan diterima lagi.

        Menggantinya menjadi `warning` ternyata belum cukup: di palet aplikasi
        ini `primary` JUGA amber, sehingga kedua tombol jadi sama persis dan
        tidak ada yang bisa dibedakan. Sekarang yang menutup PO memakai garis
        tepi merah -- bentuknya berbeda, bukan cuma warnanya.

        Labelnya juga dipendekkan. Kalimat panjang berawalan Ya/Tidak pada
        kedua tombol memaksa tombol Batal turun ke baris kedua, dan itulah yang
        membuatnya terlihat berantakan. Pertanyaannya sudah ada di deskripsi,
        jadi tombolnya cukup menyebut tindakannya.

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
                {{ __('Wait for the rest') }}
            </x-filament::button>

            {{-- Menutup PO: bergaris tepi merah, bukan terisi penuh.
                 Bentuknya sengaja berbeda, bukan cuma warnanya -- di palet
                 aplikasi ini primary dan warning sama-sama amber. --}}
            <x-filament::button wire:click="forceCompleted" color="danger" outlined>
                {{ __('Close the PO') }}
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
