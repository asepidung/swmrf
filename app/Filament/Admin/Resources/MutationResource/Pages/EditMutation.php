<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMutation extends EditRecord
{
    protected static string $resource = MutationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan')
                ->label(__('Scan Goods'))
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn ($record) => MutationResource::getUrl('scan', ['record' => $record]))
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
