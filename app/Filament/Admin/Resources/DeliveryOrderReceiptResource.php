<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryOrderReceiptResource\Pages;
use App\Models\DeliveryOrderReceipt;
use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeliveryOrderReceiptResource extends Resource
{
    protected static ?string $model = DeliveryOrderReceipt::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function getNavigationGroup(): ?string
    {
        return __('DISTRIBUTION');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermission('view_delivery_receipts');
    }

    public static function getNavigationLabel(): string
    {
        return __('Delivery Receipts');
    }

    public static function getModelLabel(): string
    {
        return __('Delivery Receipt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Delivery Receipts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Receipt Header'))
                    ->schema([
                        Forms\Components\TextInput::make('receipt_number')
                            ->label(__('Receipt Number'))
                            ->disabled(),

                        Forms\Components\TextInput::make('delivery_order_number')
                            ->label(__('DO Number'))
                            ->placeholder(fn (DeliveryOrderReceipt $record) => $record->deliveryOrder?->delivery_order_number)
                            ->disabled(),

                        Forms\Components\TextInput::make('customer_name')
                            ->label(__('Customer'))
                            ->placeholder(fn (DeliveryOrderReceipt $record) => $record->customer?->name)
                            ->disabled(),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label(__('Delivery Date'))
                            ->disabled(),

                        Forms\Components\TextInput::make('po_number')
                            ->label(__('PO Number'))
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label(__('Status'))
                            ->disabled(),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make(__('Products Received'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('product_name')
                                    ->label(__('Product'))
                                    ->placeholder(fn ($record) => $record?->product?->name)
                                    ->disabled()
                                    ->columnSpan(6),

                                Forms\Components\TextInput::make('box')
                                    ->label(__('Box'))
                                    ->disabled()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('weight')
                                    ->label(__('Weight'))
                                    ->disabled()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('notes')
                                    ->label(__('Notes'))
                                    ->disabled()
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->hiddenLabel(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label(__('Receipt Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('deliveryOrder.delivery_order_number')
                    ->label(__('DO Number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_box')
                    ->label(__('Total Box'))
                    ->numeric()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_weight')
                    ->label(__('Total Weight'))
                    ->numeric(2)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'Approved',
                        'info' => 'Invoiced',
                    ])
                    ->formatStateUsing(fn ($state) => __($state)),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_delivery_orders')),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['created_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['created_until'] ?? now()->toDateString();

                        return $query
                            ->when(
                                $from,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['created_from'])->format('d M Y');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['created_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->modalWidth('4xl'),
                Tables\Actions\Action::make('create_invoice')
                    ->label(__('Create Invoice'))
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn (DeliveryOrderReceipt $record) => $record->status === 'Approved' && auth()->user()->hasPermission('create_invoices') && !$record->trashed())
                    ->url(fn (DeliveryOrderReceipt $record) => InvoiceResource::getUrl('create', ['delivery_order_receipt_id' => $record->id])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryOrderReceipts::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
