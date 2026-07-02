<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ViewSalesReturn extends ViewRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Print PDF')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Print Berita Acara')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('sales-return.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('Unlock Return')
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Unlock / Cancel Approval')
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Unlock Sales Return')
                ->modalDescription('Anda yakin ingin melakukan unlock? Semua barang dari retur ini yang sudah masuk ke stok akan dihapus/ditarik kembali.')
                ->hidden(fn () => $this->record->status !== 'Approved')
                ->action(function () {
                    try {
                        DB::transaction(function () {
                            $returnItems = $this->record->items;
                            
                            foreach ($returnItems as $item) {
                                $stock = BeefStock::where('barcode', $item->barcode)->first();
                                if (!$stock) {
                                    throw new \Exception("Gagal: Barang {$item->barcode} tidak ditemukan di stok (sudah terpakai/dikirim).");
                                }
                                if ($stock->status !== 'IN_STOCK') {
                                    throw new \Exception("Gagal: Barang {$item->barcode} sudah tidak berada di stok (status: {$stock->status}).");
                                }
                            }

                            foreach ($returnItems as $item) {
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

                                BeefStock::where('barcode', $item->barcode)->delete();
                            }

                            $this->record->update(['status' => 'Draft']);
                        });
                        Notification::make()->title('Return Unlocked & Stock Reverted')->success()->send();
                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
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

            Actions\EditAction::make()
                ->label('')
                ->extraAttributes(['style' => 'gap: 0 !important;'])
                ->tooltip('Edit')
                ->hidden(fn () => $this->record->status === 'Approved'),
        ];
    }
}
