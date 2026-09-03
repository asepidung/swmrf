<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="[
                \Filament\Actions\Action::make('save')
                    ->label(__('Record Payment'))
                    ->submit('save')
                    ->color('primary'),
                \Filament\Actions\Action::make('cancel')
                    ->label(__('Cancel'))
                    ->url($this->getResource()::getUrl('view', ['record' => $this->record->id]))
                    ->color('gray'),
            ]"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
