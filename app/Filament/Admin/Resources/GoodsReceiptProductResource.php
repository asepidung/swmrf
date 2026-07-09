<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;
use App\Filament\Admin\Resources\GoodsReceiptProductResource\RelationManagers;
use App\Models\GoodsReceiptProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GoodsReceiptProductResource extends Resource
{
    protected static ?string $model = GoodsReceiptProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?int $navigationSort = 16;
    protected static ?string $navigationLabel = 'Beef Receipt';
    protected static ?string $modelLabel = 'Beef Receipt';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Goods Receipt Information')
                    ->schema([
                        Forms\Components\TextInput::make('gr_number')
                            ->label('GR Number')
                            ->disabled(),
                        Forms\Components\TextInput::make('po_number_display')
                            ->label('PO Number')
                            ->disabled()
                            ->formatStateUsing(fn ($record) => $record?->purchaseProduct?->po_number ?? '-'),
                        Forms\Components\TextInput::make('supplier_name_display')
                            ->label('Supplier')
                            ->disabled()
                            ->formatStateUsing(fn ($record) => $record?->supplier?->name ?? '-'),
                        Forms\Components\DatePicker::make('receive_date')
                            ->label('Receive Date')
                            ->disabled(),
                        Forms\Components\TextInput::make('sj_number')
                            ->label('Surat Jalan Number')
                            ->disabled(),
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Products Received')
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
                                    ->label('Barcode')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->columnSpan(2),
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Product')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->columnSpan(2),
                                Forms\Components\Select::make('grade_id')
                                    ->relationship('grade', 'name')
                                    ->label('Grade')
                                    ->hiddenLabel()
                                    ->disabled(),
                                Forms\Components\TextInput::make('weight')
                                    ->label('Weight')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->numeric(),
                                Forms\Components\TextInput::make('qty_pcs')
                                    ->label('Pcs')
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
                    ->label('GR Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('receive_date')
                    ->label('Receive Date')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('sj_number')
                    ->label('Delivery Number')
                    ->searchable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('purchaseProduct.po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptProduct $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
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
                    ->label('Supplier'),
                Tables\Filters\Filter::make('receive_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
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
            ->actions([])
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
            'input' => Pages\InputGoodsReceiptProduct::route('/{record}/input'),
            'view' => Pages\ViewGoodsReceiptProduct::route('/{record}'),
            'labeling' => Pages\LabelingGoodsReceiptProduct::route('/{record}/labeling'),
            'scan' => Pages\ScanGoodsReceiptProduct::route('/{record}/scan'),
            'detail-list' => Pages\GoodsReceiptProductDetailList::route('/detail-list'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('GOODS RECEIPT');
    }
}
