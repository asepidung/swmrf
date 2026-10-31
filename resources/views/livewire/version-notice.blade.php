<div>
    {{-- Riwayat lengkap: dibuka dari "Ver ..." di footer (peristiwa `open-version-history`). --}}
    <div x-data x-on:open-version-history.window="$dispatch('open-modal', { id: 'version-history' })"></div>

    <x-filament::modal id="version-history" width="lg">
        <x-slot name="heading">{{ __('Version history') }}</x-slot>

        <div class="space-y-5">
            @foreach ($entries as $entry)
                @include('livewire.version-entry', ['entry' => $entry, 'current' => $current])
            @endforeach
        </div>
    </x-filament::modal>

    {{-- Yang baru: sekali per versi per pengguna. --}}
    @if ($unseen !== [])
        <x-filament::modal id="version-whats-new" width="lg" :close-button="false" :close-by-clicking-away="false" :close-by-escaping="false">
            <x-slot name="heading">{{ __('What is new') }}</x-slot>

            <div class="space-y-5">
                @foreach ($unseen as $entry)
                    @include('livewire.version-entry', ['entry' => $entry, 'current' => $current])
                @endforeach
            </div>

            <x-slot name="footerActions">
                <x-filament::button wire:click="acknowledge" color="primary">{{ __('Got it') }}</x-filament::button>
            </x-slot>
        </x-filament::modal>

        <div x-data x-init="$nextTick(() => $dispatch('open-modal', { id: 'version-whats-new' }))"></div>
    @endif
</div>
