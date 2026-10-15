<?php

namespace App\Filament\Admin\Resources\CostingResource\Pages;

use App\Filament\Admin\Resources\CostingResource;
use App\Models\Costing;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Tidak ada tombol Create polos -- costing hanya lahir dari Draft Costing: boning yang sudah
 * `kunci` dan belum punya costing (issue #480). Pola sama dengan Draft Carcass.
 */
class ListCostings extends ListRecords
{
    protected static string $resource = CostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Boning terkunci yang belum di-costing: pola "Draft" modul lain (mis. Draft Carcass).
            Actions\Action::make('draft')
                ->label(__('Draft Costing'))
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->badge(fn (): int => Costing::boningsWaiting()->count())
                ->authorize(fn (): bool => auth()->user()?->can('create', Costing::class) ?? false)
                ->url(fn (): string => CostingResource::getUrl('draft')),
        ];
    }
}
