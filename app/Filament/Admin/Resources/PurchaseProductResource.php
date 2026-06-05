<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseProductResource\Pages;
use App\Models\PurchaseProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;

class PurchaseProductResource extends Resource
{
    protected static ?string $model = PurchaseProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'PURCHASE ORDER';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationLabel = 'PO Product';
    protected static ?string $modelLabel = 'PO Product';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Header Information')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\DatePicker::make('po_date')
                            ->label('PO Date')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Select::make('approved_by')
                            ->label('Approved By')
                            ->relationship('approvedBy', 'name')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->disabled()
                            ->columnSpan(12),
                    ])->columns(12),

                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship('Product', 'name')
                                    ->disabled()
                                    ->hiddenLabel()
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                Forms\Components\TextInput::make('qty')
                                    ->disabled()
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 2, ',', '.'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 0, ',', '.'))
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                Forms\Components\TextInput::make('subtotal')
                                    ->hiddenLabel()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 0, ',', '.'))
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ])
                            ->columns(12),
                    ]),

                Forms\Components\Section::make('Summary')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('grand_total_display')
                                    ->label('Grand Total')
                                    ->content(function ($record) {
                                        return 'Rp ' . number_format($record ? $record->total_amount : 0, 0, ',', '.');
                                    })
                                    ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
                            ])
                            ->columnSpan(12),
                    ])->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordAction(null)
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('po_date')
                    ->label('PO Date')
                    ->date('d-M-Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('IDR', locale: 'id'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('po_date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date')
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date')
                            ->default(now()),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['created_from'], fn($q, $date) => $q->whereDate('po_date', '>=', $date))
                            ->when($data['created_until'], fn($q, $date) => $q->whereDate('po_date', '<=', $date));
                    })
            ])
            ->actions([
                // Clean table UI
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseProducts::route('/'),
            'view' => Pages\ViewPurchaseProduct::route('/{record}'),
        ];
    }
}
