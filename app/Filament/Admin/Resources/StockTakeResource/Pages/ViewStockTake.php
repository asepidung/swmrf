<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockTake extends ViewRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan')
                ->label(__('Scan Items'))
                ->icon('heroicon-o-qr-code')
                ->url(fn () => StockTakeResource::getUrl('scan', ['record' => $this->record]))
                ->visible(fn () => $this->record->status === 'IN_PROGRESS'),
                
            Actions\Action::make('finish')
                ->label(__('Finish Opname'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Selesaikan Stock Opname?'))
                ->modalDescription(__('Semua barang yang berstatus "MISSING" akan dihapus dari sistem. Barang "UNEXPECTED" akan ditambahkan. Transaksi ini tidak dapat dibatalkan.'))
                ->visible(fn () => $this->record->status === 'IN_PROGRESS')
                ->action(function () {
                    \Illuminate\Support\Facades\DB::transaction(function () {
                        // 1. Bypass Freeze Check
                        \App\Services\WarehouseFreezeService::$bypassed = true;
                        
                        $record = $this->record;
                        $warehouseId = $record->warehouse_id;
                        $now = now();
                        
                        // 2. Handle MISSING items (Delete from BeefStock)
                        $missingItems = $record->items()->where('status', 'MISSING')->get();
                        foreach ($missingItems as $item) {
                            $stock = \App\Models\BeefStock::where('barcode', $item->barcode)
                                ->where('warehouse_id', $warehouseId)
                                ->first();
                                
                            if ($stock) {
                                // Log movement
                                \App\Models\BeefStockMovement::create([
                                    'product_id' => $stock->product_id,
                                    'warehouse_id' => $warehouseId,
                                    'condition' => $stock->grade_id,
                                    'barcode' => $stock->barcode,
                                    'transaction_type' => 'STOCK_TAKE_LOSS',
                                    'reference_document' => $record->document_number,
                                    'weight_in' => 0,
                                    'weight_out' => $stock->weight,
                                    'pcs_in' => 0,
                                    'pcs_out' => $stock->qty_pcs,
                                    'note' => 'Stock Take Loss (Missing)',
                                    'created_by' => auth()->id(),
                                ]);
                                
                                $stock->delete();
                            }
                        }
                        
                        // 3. Handle UNEXPECTED items (Insert into BeefStock)
                        $unexpectedItems = $record->items()->where('status', 'UNEXPECTED')->get();
                        foreach ($unexpectedItems as $item) {
                            \App\Models\BeefStock::create([
                                'barcode' => $item->barcode,
                                'product_id' => $item->product_id,
                                'warehouse_id' => $warehouseId,
                                'grade_id' => $item->grade_id,
                                'weight' => $item->weight,
                                'qty_pcs' => $item->qty_pcs,
                                'ph_level' => $item->ph_level,
                                'pack_date' => $item->pack_date,
                                'origin' => \App\Helpers\BarcodeHelper::getOrigin($item->barcode),
                                'status' => 'available',
                                'note' => $item->note,
                            ]);
                            
                            // Log movement
                            \App\Models\BeefStockMovement::create([
                                'product_id' => $item->product_id,
                                'warehouse_id' => $warehouseId,
                                'condition' => $item->grade_id,
                                'barcode' => $item->barcode,
                                'transaction_type' => 'STOCK_TAKE_FOUND',
                                'reference_document' => $record->document_number,
                                'weight_in' => $item->weight,
                                'weight_out' => 0,
                                'pcs_in' => $item->qty_pcs,
                                'pcs_out' => 0,
                                'note' => 'Stock Take Found (Unexpected)',
                                'created_by' => auth()->id(),
                            ]);
                        }
                        
                        // 4. Update Opname Status
                        $record->update(['status' => 'COMPLETED']);
                        
                        // Re-enable freeze check
                        \App\Services\WarehouseFreezeService::$bypassed = false;
                    });
                    
                    \Filament\Notifications\Notification::make()
                        ->title(__('Stock Opname Selesai'))
                        ->body(__('Rekonsiliasi stok berhasil dilakukan.'))
                        ->success()
                        ->send();
                }),
                
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === 'DRAFT' || $this->record->status === 'IN_PROGRESS'),
        ];
    }
}
