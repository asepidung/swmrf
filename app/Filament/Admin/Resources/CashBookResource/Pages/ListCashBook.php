<?php

namespace App\Filament\Admin\Resources\CashBookResource\Pages;

use App\Filament\Admin\Resources\CashBookResource;
use Filament\Resources\Pages\ListRecords;

class ListCashBook extends ListRecords
{
    protected static string $resource = CashBookResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
