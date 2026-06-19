<?php

namespace App\Filament\Admin\Resources\ReceivableResource\Pages;

use App\Filament\Admin\Resources\ReceivableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReceivables extends ListRecords
{
    protected static string $resource = ReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
