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
                    ->action(function (PurchaseProduct $record) {
                        // Satu PO satu penerimaan yang sedang dikerjakan (temuan pelatihan 28 Oktober 2026: dua orang
                        // menekan Process GR dan lahir dua GRB). Selama masih ada GR yang belum terkunci untuk PO ini,
                        // orang kedua dibawa ke GR yang sama, bukan dibuatkan yang baru. Penerimaan bertahap tetap
                        // jalan: GR berikutnya baru lahir setelah yang sebelumnya terkunci.
                        $gr = \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                            // Kunci baris PO supaya dua klik bersamaan berurutan, bukan bersamaan.
                            PurchaseProduct::whereKey($record->id)->lockForUpdate()->first();

                            return GoodsReceiptProduct::where('purchase_product_id', $record->id)
                                ->where('is_locked', false)
                                ->orderBy('id')
                                ->first()
                                ?? GoodsReceiptProduct::create([
                                    'purchase_product_id' => $record->id,
                                    'supplier_id' => $record->supplier_id,
                                    'receive_date' => now()->format('Y-m-d'),
                                    'created_by' => auth()->id(),
                                ]);
                        });

                        // Redirect to the Input page
                        return redirect(GoodsReceiptProductResource::getUrl('input', ['record' => $gr->id]));
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
                        $record->update(['status' => 'canceled']);
                        \Filament\Notifications\Notification::make()->title(__('Purchase order cancelled'))->success()->send();
                    }),
            ]);
    }
}
