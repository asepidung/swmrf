<x-filament-panels::page>
    <form wire:submit="processSave">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" color="primary">
                Save Goods Receipt
            </x-filament::button>
        </div>
    </form>

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
