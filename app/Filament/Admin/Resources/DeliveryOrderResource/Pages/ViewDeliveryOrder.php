<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrderReceipt;
use App\Models\BeefStock;
use App\Models\TallyItem;
use App\Models\BeefStockMovement;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ViewDeliveryOrder extends ViewRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('')
                ->tooltip(__('Back'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->iconButton()
                ->url($this->getResource()::getUrl('index')),

            Actions\Action::make('print')
                ->label('')
                ->tooltip(__('Print'))
                ->color('info')
                ->icon('heroicon-o-printer')
                ->iconButton()
                ->url(fn () => route('print.delivery-order', ['record' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('unapprove')
                ->label('')
                ->tooltip(__('Unapprove'))
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->iconButton()
                ->visible(fn () => $this->record->status === 'Approved' && !$this->record->receipt?->invoice)
                ->requiresConfirmation()
                ->modalHeading(__('Confirm Unapprove'))
                ->modalDescription(__('Apakah Anda yakin ingin membatalkan persetujuan Surat Jalan ini? Tanda terima terkait akan dihapus dan barang tolakan akan dikembalikan ke Tally.'))
                ->action(function () {
                    $receipt = DeliveryOrderReceipt::where('delivery_order_id', $this->record->id)->first();

                    DB::transaction(function () use ($receipt) {
                        if ($receipt) {
                            $receipt->delete(); // soft delete
                        }

                        // Restore rejected items back to tally if they are still in stock
                        $doNumber = $this->record->delivery_order_number;
                        $rejectedStocks = BeefStock::where('note', 'Tolakan dari DO#' . $doNumber)
                            ->where('status', 'IN_STOCK')
                            ->get();

                        foreach ($rejectedStocks as $stock) {
                            // Recreate TallyItem
                            TallyItem::create([
                                'tally_id' => $this->record->tally_id,
                                'barcode' => $stock->barcode,
                                'product_id' => $stock->product_id,
                                'warehouse_id' => $stock->warehouse_id,
                                'grade_id' => $stock->grade_id,
                                'weight' => $stock->weight,
                                'qty_pcs' => $stock->qty_pcs,
                                'ph_level' => $stock->ph_level,
                                'pack_date' => $stock->pack_date,
                                'exp_date' => $stock->exp_date,
                                'origin' => $stock->origin,
                            ]);

                            // Log movement to reverse the rejection
                            BeefStockMovement::create([
                                'product_id' => $stock->product_id,
                                'warehouse_id' => $stock->warehouse_id,
                                'condition' => $stock->grade_id,
                                'barcode' => $stock->barcode,
                                'transaction_type' => 'TALLY',
                                'reference_document' => $this->record->delivery_order_number,
                                'weight_in' => 0,
                                'weight_out' => $stock->weight,
                                'pcs_in' => 0,
                                'pcs_out' => $stock->qty_pcs,
                                'note' => 'Revert rejection of DO#' . $doNumber,
                                'created_by' => auth()->id() ?? 1,
                            ]);

                            // Delete the stock record
                            $stock->delete();
                        }

                        $this->record->update(['status' => 'Ready']);
                        $this->record->syncItemsFromTally();

                        // Delete related financial loss if exists
                        $this->record->financialLoss()->delete();

                        if ($this->record->salesOrder) {
                            $this->record->salesOrder->update(['status' => 'on_delivery']);
                        }
                    });

                    Notification::make()
                        ->title(__('Unapproved Successfully'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record->id]));
                }),
        ];
    }
}
