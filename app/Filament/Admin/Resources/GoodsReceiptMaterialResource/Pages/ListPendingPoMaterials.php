<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use App\Models\PurchaseMaterial;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;

class ListPendingPoMaterials extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GoodsReceiptMaterialResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-material-resource.pages.list-pending-po-materials';

    public function table(Table $table): Table
    {
        return $table
            ->query(PurchaseMaterial::query()->whereIn('status', ['pending', 'partial']))
            ->columns([
                TextColumn::make('po_number')->label(__('PO Number'))->searchable()->sortable(),
                TextColumn::make('po_date')->label(__('PO Date'))->date()->sortable(),
                TextColumn::make('supplier.name')->label(__('Supplier'))->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'partial' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('approvedBy.name')->label(__('Approved By')),
            ])
            ->actions([
                Action::make('process_gr')
                    ->label(__('Process GR'))
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->button()
                    ->url(fn (PurchaseMaterial $record): string => GoodsReceiptMaterialResource::getUrl('create', ['po_id' => $record->id])),
                Action::make('cancel_po')
                    ->label(__('Cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading(__('Cancel Purchase Order'))
                    ->modalDescription(__('Cancel this purchase order?'))
                    // Membatalkan PO menutup pesanan yang sudah terbit.
                    ->visible(fn (): bool => auth()->user()?->isProgrammer()
                        || (auth()->user()?->hasPermission('cancel_purchase_materials') ?? false))
                    ->action(function (PurchaseMaterial $record) {
                        // Status dibaca ulang dengan baris terkunci; PO yang sudah punya uang muka atau penerimaan tidak dibatalkan
                        // diam-diam, karena uangnya sudah keluar (PO-01).
                        $alasan = \Illuminate\Support\Facades\DB::transaction(function () use ($record): ?string {
                            $po = $record::query()->lockForUpdate()->find($record->getKey());

                            if (! $po || in_array($po->status, ['canceled', 'completed'], true)) {
                                return __('This purchase order is already closed.');
                            }

                            if (\App\Models\SupplierPayment::totalPaidFor($po) > 0) {
                                return __('This purchase order already has a down payment, so it cannot be cancelled here.');
                            }

                            if ($po->goodsReceipts()->exists()) {
                                return __('This purchase order already has goods receipts, so it cannot be cancelled.');
                            }

                            $po->update(['status' => 'canceled']);

                            return null;
                        });

                        if ($alasan !== null) {
                            \Filament\Notifications\Notification::make()->title($alasan)->danger()->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()->title(__('Purchase order cancelled'))->success()->send();
                    }),
            ]);
    }
}
