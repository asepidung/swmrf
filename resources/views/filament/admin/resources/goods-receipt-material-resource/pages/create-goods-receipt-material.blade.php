<x-filament-panels::page>
    <x-filament-panels::form wire:submit="processSave">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

    <x-filament::modal id="partial-confirmation-modal" width="md">
        <x-slot name="heading">
            PO Quantity Not Fulfilled
        </x-slot>

        <x-slot name="description">
            The received quantity is less than the ordered quantity. Will there be a partial arrival later?
        </x-slot>

        <x-slot name="footerActions">
            <x-filament::button wire:click="confirmPartial" color="info">
                Yes, Wait for Partial
            </x-filament::button>

            <x-filament::button wire:click="forceCompleted" color="success">
                No, Mark PO as Completed
            </x-filament::button>
            
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'partial-confirmation-modal' })">
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
