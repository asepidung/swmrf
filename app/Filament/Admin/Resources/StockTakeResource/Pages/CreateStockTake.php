<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Support\DocumentNumber;
use App\Models\StockTake;
use App\Models\BeefStock;
use App\Models\StockTakeItem;
use Illuminate\Support\Facades\DB;

class CreateStockTake extends CreateRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $date = \Carbon\Carbon::parse($data['date']);
        $yymm = $date->format('ym');
        
        // Bulannya sudah terkandung di prefix (ST#yymm), jadi penyaring
        // whereYear/whereMonth tidak lagi diperlukan.
        $data['document_number'] = DocumentNumber::next(
            query: StockTake::withTrashed(),
            column: 'document_number',
            prefix: 'ST#'.$yymm,
            padding: 3,
        );
        $data['created_by'] = auth()->id();
        $data['status'] = 'IN_PROGRESS';
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Take a snapshot of ALL active warehouse stock (Global Stock Take)
        // Insert them as MISSING into stock_take_items
        
        $stocks = BeefStock::where('status', 'IN_STOCK')->get();
            
        $itemsToInsert = [];
        foreach ($stocks as $stock) {
            $itemsToInsert[] = [
                'stock_take_id' => $record->id,
                'barcode' => $stock->barcode,
                'product_id' => $stock->product_id,
                'warehouse_id' => $stock->warehouse_id,
                'grade_id' => $stock->grade_id,
                'weight' => $stock->weight,
                'qty_pcs' => $stock->qty_pcs,
                'ph_level' => $stock->ph_level,
                'pack_date' => $stock->pack_date,
                'status' => 'MISSING',
                'is_manual' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Batch insert for performance
        if (!empty($itemsToInsert)) {
            $chunks = array_chunk($itemsToInsert, 500);
            foreach ($chunks as $chunk) {
                StockTakeItem::insert($chunk);
            }
        }
    }
}
