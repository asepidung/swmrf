<?php

namespace App\Filament\Resources\CarcassResource\Pages;

use App\Filament\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCarcass extends ViewRecord
{
    protected static string $resource = CarcassResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn($record) => route('print.carcass', $record->id))
                ->openUrlInNewTab(),

            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->color('warning'),

            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->before(function () {}),

            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
