<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use Filament\Resources\Pages\Page;
use App\Models\Carcass;

class PrintCarcass extends Page
{
    protected static string $resource = CarcassResource::class;

    protected static string $view = 'filament.admin.resources.carcass-resource.pages.print-carcass';

    public Carcass $record;

    public function mount(Carcass $record): void
    {
        $this->record = $record->load(['weighing.receiving.purchaseCattle', 'weighing.receiving.supplier', 'items.weighingItem', 'creator']);
    }

    public function getTitle(): string
    {
        return 'Print Carcass - ' . $this->record->carcass_number;
    }
}
