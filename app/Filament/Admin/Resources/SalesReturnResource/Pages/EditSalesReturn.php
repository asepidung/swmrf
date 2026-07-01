<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class EditSalesReturn extends EditRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Input Return Items')
                ->label('Input / Scan Barang')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('info')
                ->url(fn () => SalesReturnResource::getUrl('input-items', ['record' => $this->record]))
                ->hidden(fn () => $this->record->status !== 'Draft'),

            Actions\Action::make('Approve Return')
                ->label('Approve Return')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->hidden(fn () => $this->record->status !== 'Draft' || $this->record->items->isEmpty())
                ->action(function () {
                    try {
                        DB::transaction(function () {
                            $this->record->update(['status' => 'Approved']);

                            foreach ($this->record->items as $item) {
                                // Add to BeefStock
                                BeefStock::create([
                                    'barcode' => $item->barcode,
                                    'product_id' => $item->product_id,
                                    'warehouse_id' => $item->warehouse_id,
                                    'grade_id' => $item->grade_id,
                                    'weight' => $item->weight,
                                    'qty_pcs' => $item->qty_pcs,
                                    'ph_level' => $item->ph_level,
                                    'pack_date' => $item->pack_date,
                                    'exp_date' => $item->exp_date,
                                    'origin' => $item->origin,
                                    'status' => 'IN_STOCK',
                                    'note' => 'Sales Return ' . $this->record->return_number,
                                ]);

                                // Log to BeefStockMovement
                                BeefStockMovement::create([
                                    'product_id' => $item->product_id,
                                    'warehouse_id' => $item->warehouse_id,
                                    'condition' => $item->grade_id,
                                    'barcode' => $item->barcode,
                                    'transaction_type' => 'SALES_RETURN',
                                    'reference_document' => $this->record->return_number,
                                    'weight_in' => $item->weight,
                                    'pcs_in' => $item->qty_pcs,
                                    'created_by' => Auth::id() ?? 1,
                                    'note' => 'Sales Return from Customer',
                                ]);
                            }
                        });
                        Notification::make()->title('Return Approved & Stock Updated')->success()->send();
                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('Print PDF')
                ->label('PDF Berita Acara')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->url(fn () => route('sales-return.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
