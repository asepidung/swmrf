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
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Input / Scan Barang')
                ->icon('heroicon-o-bars-3-bottom-left')
                ->color('warning')
                ->url(fn () => SalesReturnResource::getUrl('input-items', ['record' => $this->record]))
                ->hidden(fn () => $this->record->status !== 'Draft'),

            Actions\Action::make('Print PDF')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Print Berita Acara')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('sales-return.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('Approve Return')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Approve Return')
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
                        $this->redirect(url()->current());
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('Unlock Return')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Unlock / Cancel Approval')
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->hidden(fn () => $this->record->status !== 'Approved')
                ->action(function () {
                    try {
                        DB::transaction(function () {
                            $returnItems = $this->record->items;
                            
                            // Validation: check if all barcodes still exist and are IN_STOCK
                            foreach ($returnItems as $item) {
                                $stock = BeefStock::where('barcode', $item->barcode)->first();
                                if (!$stock) {
                                    throw new \Exception("Gagal: Barang {$item->barcode} tidak ditemukan di stok (sudah terpakai/dikirim).");
                                }
                                if ($stock->status !== 'IN_STOCK') {
                                    throw new \Exception("Gagal: Barang {$item->barcode} sudah tidak berada di stok (status: {$stock->status}).");
                                }
                            }

                            // If validation passes, reverse the stock creation
                            foreach ($returnItems as $item) {
                                // Record reverse movement
                                BeefStockMovement::create([
                                    'product_id' => $item->product_id,
                                    'warehouse_id' => $item->warehouse_id,
                                    'condition' => $item->grade_id,
                                    'barcode' => $item->barcode,
                                    'transaction_type' => 'CANCEL_SALES_RETURN',
                                    'reference_document' => $this->record->return_number,
                                    'weight_out' => $item->weight,
                                    'pcs_out' => $item->qty_pcs,
                                    'created_by' => Auth::id() ?? 1,
                                    'note' => 'Unlock/Cancel Sales Return',
                                ]);

                                // Delete from stock
                                BeefStock::where('barcode', $item->barcode)->delete();
                            }

                            $this->record->update(['status' => 'Draft']);
                        });
                        Notification::make()->title('Return Unlocked & Stock Reverted')->success()->send();
                        $this->redirect(url()->current());
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('Back')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Back')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => $this->getResource()::getUrl('index')),

            Actions\DeleteAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Delete')
                ->icon('heroicon-o-trash'),
            
            Actions\ForceDeleteAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Force Delete')
                ->icon('heroicon-o-trash'),
            
            Actions\RestoreAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Restore')
                ->icon('heroicon-o-arrow-path'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }
}
