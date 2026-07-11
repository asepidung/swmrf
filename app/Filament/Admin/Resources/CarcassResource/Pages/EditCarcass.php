<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        // Menyembunyikan tombol cancel bawaan
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeSave(): void
    {
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
