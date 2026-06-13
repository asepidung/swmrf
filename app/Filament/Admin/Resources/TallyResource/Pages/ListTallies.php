<?php

namespace App\Filament\Admin\Resources\TallyResource\Pages;

use App\Filament\Admin\Resources\TallyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTallies extends ListRecords
{
    protected static string $resource = TallyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('draft')
                ->label(__('Draft Tally'))
                ->icon('heroicon-m-clipboard-document-list')
                ->color('info')
                ->url(fn (): string => $this->getResource()::getUrl('draft')),
        ];
    }
}
