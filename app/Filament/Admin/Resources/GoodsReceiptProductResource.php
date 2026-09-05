<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;
use App\Filament\Admin\Resources\GoodsReceiptProductResource\RelationManagers;
use App\Models\GoodsReceiptProduct;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GoodsReceiptProductResource extends Resource
{
    public static function getModelLabel(): string
    {
        return __('Beef Receipt');
    }

    public static function getNavigationLabel(): string
    {
        return __('Beef Receipt');
    }

    protected static ?string $model = GoodsReceiptProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?int $navigationSort = 16;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Goods Receipt Information'))
                    ->schema([
                        Forms\Components\TextInput::make('gr_number')
                            ->label(__('GR Number'))
                            ->disabled(),
                        Forms\Components\TextInput::make('po_number_display')
                            ->label(__('PO Number'))
                            ->disabled()
                            ->formatStateUsing(fn ($record) => $record?->purchaseProduct?->po_number ?? '-'),
                        Forms\Components\TextInput::make('supplier_name_display')
                            ->label(__('Supplier'))
                            ->disabled()
                            ->formatStateUsing(fn ($record) => $record?->supplier?->name ?? '-'),
                        Forms\Components\DatePicker::make('receive_date')
                            ->label(__('Receive Date'))
                            ->disabled(),
                        Forms\Components\TextInput::make('sj_number')
                            ->label(__('Delivery Note Number'))
                            ->disabled(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make(__('Products Received'))
                    ->schema([
                        Forms\Components\Grid::make(7)
                            ->schema([
                                Forms\Components\Placeholder::make('col_barcode')->label(__('Barcode'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_product')->label(__('Product'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_grade')->label(__('Grade')),
                                Forms\Components\Placeholder::make('col_weight')->label(__('Weight (Kg)')),
                                Forms\Components\Placeholder::make('col_pcs')->label(__('Pcs')),
                            ])
                            ->extraAttributes(['class' => 'hidden md:grid']),

                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\TextInput::make('barcode')
                                    ->label(__('Barcode'))
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->columnSpan(2),
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label(__('Product'))
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->columnSpan(2),
                                Forms\Components\Select::make('grade_id')
                                    ->relationship('grade', 'name')
                                    ->label(__('Grade'))
                                    ->hiddenLabel()
                                    ->disabled(),
                                Forms\Components\TextInput::make('weight')
                                    ->label(__('Weight'))
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->numeric(),
                                Forms\Components\TextInput::make('qty_pcs')
                                    ->label(__('Pcs'))
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->numeric(),
                            ])
                            ->columns(7)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gr_number')
                    ->label(__('GR Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('receive_date')
                    ->label(__('Receive Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('sj_number')
                    ->label(__('Delivery Number'))
                    ->searchable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('purchaseProduct.po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('Created By'))
                    ->badge()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (GoodsReceiptProduct $record): string => Pages\InputGoodsReceiptProduct::getUrl(['record' => $record]),
            )
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_goods_receipt_products')),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Tables\Filters\Filter::make('receive_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['until'] ?? now()->toDateString();
                        return $query
                            ->when(
                                $from,
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('lock')
                    ->tooltip(fn (GoodsReceiptProduct $record) => $record->is_locked ? __('Unlock') : __('Lock'))
                    ->icon(fn (GoodsReceiptProduct $record) => $record->is_locked ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (GoodsReceiptProduct $record) => $record->is_locked ? 'danger' : 'success')
                    ->hiddenLabel()
                    ->requiresConfirmation()
                    ->modalHeading(fn (GoodsReceiptProduct $record) => $record->is_locked ? __('Unlock Goods Receipt') : __('Lock Goods Receipt'))
                    ->modalDescription(fn (GoodsReceiptProduct $record) => $record->is_locked 
                        ? __('Unlock this goods receipt? The payable it created will be deleted, as long as nothing has been paid yet.') 
                        : __('Lock this goods receipt? Nothing can be changed once it is locked.'))
                    ->hidden(fn (GoodsReceiptProduct $record) => ! $record->items()->exists())
                    // Mengunci GR MENERBITKAN hutang ke pemasok; membukanya
                    // kembali MENGHAPUS hutang itu. Sebelum ini syaratnya
                    // hanya "dokumennya punya barang".
                    ->visible(fn (): bool => auth()->user()?->isProgrammer()
                        || (auth()->user()?->hasPermission('lock_goods_receipt_products') ?? false))
                    ->action(function (GoodsReceiptProduct $record) {
                        \Illuminate\Support\Facades\DB::beginTransaction();
                        try {
                            if ($record->is_locked) {
                                // Unlock logic
                                $payable = \App\Models\Payable::where('payableable_type', get_class($record))
                                    ->where('payableable_id', $record->id)
                                    ->first();
                                    
                                // Membuka kunci akan MENGHAPUS hutangnya.
                                // Kalau sudah ada uang yang dibayarkan atas
                                // hutang itu, pembayarannya jadi menunjuk ke
                                // sesuatu yang tidak ada lagi.
                                //
                                // Status payable ikut diperiksa, bukan hanya
                                // jumlah pembayarannya: baris pembayaran
                                // bernilai nol lolos dari penjumlahan
                                // sementara statusnya sudah terlanjur
                                // berubah.
                                // paid_amount, BUKAN relasi payments().
                                // Payable tidak punya relasi itu sama sekali;
                                // memanggilnya melempar "Call to undefined
                                // method". Penjagaan ini karena itu tidak
                                // pernah benar-benar bekerja -- ia meledak
                                // alih-alih menolak, dan pesan errornya tidak
                                // menjelaskan apa pun kepada pengguna.
                                $sudahDibayar = $payable && (
                                    in_array($payable->status, ['partial', 'paid'], true)
                                    || (float) $payable->paid_amount > 0
                                );

                                if ($sudahDibayar) {
                                    throw new \Exception(__('This Goods Receipt cannot be unlocked because a payment is already recorded against its payable.'));
                                }
                                
                                $record->update(['is_locked' => false]);

                                if ($payable) {
                                    // Uang muka WAJIB dilepas dulu. Tanpa ini
                                    // DP tercatat terpakai untuk utang yang
                                    // sudah tidak ada, lalu hilang permanen.
                                    $payable->releaseAdvances();
                                    $payable->delete();
                                }
                                
                                \Filament\Notifications\Notification::make()->title(__('Goods Receipt unlocked'))->success()->send();
                            } else {
                                // Lock logic
                                $record->update(['is_locked' => true]);
                                \App\Models\Payable::generateForGoodsReceiptProduct($record);
                                \Filament\Notifications\Notification::make()->title(__('Goods Receipt locked'))->success()->send();
                            }
                            
                            \Illuminate\Support\Facades\DB::commit();
                        } catch (\Exception $e) {
                            report($e);
                            \Illuminate\Support\Facades\DB::rollBack();
                            \Filament\Notifications\Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('scan')
                        ->label(__('Scan'))
                        ->icon('heroicon-o-qr-code')
                        ->color('warning')
                        ->hidden(fn (GoodsReceiptProduct $record) => $record->is_locked)
                        ->url(fn (GoodsReceiptProduct $record) => Pages\ScanGoodsReceiptProduct::getUrl(['record' => $record])),
                    Tables\Actions\Action::make('label')
                        ->label(__('Label'))
                        ->icon('heroicon-o-tag')
                        ->color('info')
                        ->hidden(fn (GoodsReceiptProduct $record) => $record->is_locked)
                        ->url(fn (GoodsReceiptProduct $record) => Pages\LabelingGoodsReceiptProduct::getUrl(['record' => $record])),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip(__('Options')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'border-s-2 border-danger-600 dark:border-danger-400 bg-danger-50 dark:bg-danger-900/50' : null)
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoodsReceiptProducts::route('/'),
            'drafts' => Pages\ListPendingPoProducts::route('/drafts'),
            'detail-list' => Pages\GoodsReceiptProductDetailList::route('/detail-list'),
            'input' => Pages\InputGoodsReceiptProduct::route('/{record}/input'),
            'view' => Pages\ViewGoodsReceiptProduct::route('/{record}'),
            'labeling' => Pages\LabelingGoodsReceiptProduct::route('/{record}/labeling'),
            'scan' => Pages\ScanGoodsReceiptProduct::route('/{record}/scan'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_goods_receipt_products',
        );
    }

    public static function getNavigationGroup(): ?string
    {
        return __('GOODS RECEIPT');
    }
}
