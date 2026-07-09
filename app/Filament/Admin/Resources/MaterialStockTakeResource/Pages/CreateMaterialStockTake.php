<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Material;
use App\Models\MaterialStockTakeItem;
use Illuminate\Support\Facades\DB;

class CreateMaterialStockTake extends CreateRecord
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Fetch all materials that are shown in stock
        $materials = Material::where('show_in_stock', true)->get();

        $items = [];
        foreach ($materials as $material) {
            // Get current system stock
            $systemQty = $material->stocks()->sum('qty') ?? 0;

            $items[] = [
                'material_stock_take_id' => $record->id,
                'material_id' => $material->id,
                'system_qty' => $systemQty,
                'physical_qty' => null,
                'difference_qty' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert all items
        if (!empty($items)) {
            MaterialStockTakeItem::insert($items);
        }
    }
}
