<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-4 mt-6">
            <x-filament::button type="submit">
                Save Changes
            </x-filament::button>

            <x-filament::button color="gray" tag="a" href="{{ filament()->getUrl() }}">
                Cancel
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
