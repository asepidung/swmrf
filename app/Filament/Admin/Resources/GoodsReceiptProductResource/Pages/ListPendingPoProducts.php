<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Models\PurchaseProduct;
use App\Models\GoodsReceiptProduct;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;

class ListPendingPoProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = GoodsReceiptProductResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-product-resource.pages.list-pending-po-products';

    public function table(Table $table): Table
    {
        return $table
            ->query(PurchaseProduct::query()->whereIn('status', ['pending', 'partial']))
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
                TextColumn::make('approver.name')->label(__('Approved By')),
            ])
            ->actions([
                Action::make('process_gr')
                    ->label(__('Process GR'))
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->button()
                    // Tombol ini hanya MEMBUKA formulir; dokumen GRB baru lahir saat Simpan (Owner, 28 Oktober 2026).
                    // GR lama yang belum terkunci untuk PO ini (sisa alur lama) dilanjutkan, bukan dibuatkan yang baru.
                    ->url(function (PurchaseProduct $record): string {
                        $open = GoodsReceiptProduct::where('purchase_product_id', $record->id)->where('is_locked', false)->orderBy('id')->first();

                        return $open
                            ? GoodsReceiptProductResource::getUrl('input', ['record' => $open->id])
                            : GoodsReceiptProductResource::getUrl('new', ['purchaseProduct' => $record->id]);
                    }),
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
                        || (auth()->user()?->hasPermission('cancel_purchase_products') ?? false))
                    ->action(function (PurchaseProduct $record) {
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
