<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use App\Models\BoningCarcass;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditCarcass extends EditRecord
{
    protected static string $resource = CarcassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => CarcassResource::getUrl('print', ['record' => $this->record]))
                ->openUrlInNewTab(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => \App\Models\BoningCarcass::where('carcass_id', $record->id)->exists()),
            Actions\ForceDeleteAction::make()
                ->disabled(fn ($record) => \App\Models\BoningCarcass::where('carcass_id', $record->id)->exists()),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        if (\App\Models\BoningCarcass::where('carcass_id', $this->getRecord()->id)->exists()) {
            return [];
        }

        return [
            $this->getSaveFormAction(),
        ];
    }

    public function mount($record): void
    {
        parent::mount($record);

        if (\App\Models\BoningCarcass::where('carcass_id', $this->getRecord()->id)->exists()) {
            \Filament\Notifications\Notification::make()
                ->title(__('This Carcass has been processed into Boning and is now read-only.'))
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeSave(): void
    {
        // Sebelumnya pencegahan edit dokumen yang sudah dipakai Boning
        // HANYA di tampilan -- tombol Save disembunyikan
        // (`getFormActions()`) dan mount() cuma memperingatkan. `save()`
        // Livewire bawaan Filament sendiri tidak pernah mengecek ini,
        // jadi request yang diulang (devtools, tab basi) tetap bisa
        // menulis angka karkas yang sudah dipakai Boning yang sudah
        // terkunci.
        if (BoningCarcass::where('carcass_id', $this->getRecord()->id)->exists()) {
            Notification::make()
                ->title(__('This Carcass has been processed into Boning and is now read-only.'))
                ->danger()
                ->send();

            throw new Halt();
        }

        if (isset($this->data['items']) && is_array($this->data['items'])) {
            $filteredItems = [];
            foreach ($this->data['items'] as $key => $item) {
                $c1 = (float) ($item['carcass_1'] ?? 0);
                $c2 = (float) ($item['carcass_2'] ?? 0);
                $h = (float) ($item['hides'] ?? 0);

                // Ignore if all are 0
                if ($c1 > 0 || $c2 > 0 || $h > 0) {
                    $filteredItems[$key] = $item;
                }
            }
            $this->data['items'] = $filteredItems;
            $this->form->fill($this->data);
        }
    }
}
