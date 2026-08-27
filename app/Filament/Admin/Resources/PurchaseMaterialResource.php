<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseMaterialResource\Pages;
use App\Models\PurchaseMaterial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;

class PurchaseMaterialResource extends Resource
{
    protected static ?string $model = PurchaseMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationLabel = 'PO Material';
    protected static ?string $modelLabel = 'PO Material';

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
                            ->columnSpan(['default' => 12, 'md' => 4]),

                        Forms\Components\DatePicker::make('po_date')
                            ->label('PO Date')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 4]),

                        Forms\Components\TextInput::make('materialRequisition.document_number')
                            ->label('Request Number')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 4]),

                        Forms\Components\TextInput::make('materialRequisition.user.name')
                            ->label('Requester')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 4]),

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
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_material')
                                    ->label(__('Material'))
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                Forms\Components\Placeholder::make('col_qty')
                                    ->label(__('Qty'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Forms\Components\Placeholder::make('col_price')
                                    ->label(__('Price'))
                                    ->columnSpan(['default' => 6, 'md' => 3]),
                                Forms\Components\Placeholder::make('col_subtotal')
                                    ->label(__('Subtotal'))
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ])
                            ->extraAttributes(['class' => 'hidden md:grid']),

                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->relationship('material', 'name')
                                    ->disabled()
                                    ->hiddenLabel()
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                Forms\Components\TextInput::make('qty')
                                    ->disabled()
                                    ->hiddenLabel()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 2, ',', '.'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 0, ',', '.'))
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                Forms\Components\TextInput::make('subtotal')
                                    ->hiddenLabel()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 0, ',', '.'))
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ])
                            ->columns(12),
                    ]),

                Forms\Components\Section::make('Summary')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(function ($record) {
                                        $subtotal = $record ? $record->items()->sum('subtotal') : 0;
                                        return 'Rp ' . number_format($subtotal, 0, ',', '.');
                                    }),

                                Forms\Components\Placeholder::make('tax_display')
                                    ->label('Tax / PPN (11%)')
                                    ->content(function ($record) {
                                        if (!$record) return 'Rp 0';
                                        if ($record->supplier && !$record->supplier->is_tax_11) return 'Rp 0 (Non-PKP)';
                                        
                                        $subtotal = $record->items()->sum('subtotal');
                                        $tax = $record->total_amount - $subtotal;
                                        return 'Rp ' . number_format($tax, 0, ',', '.');
                                    }),

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

                Tables\Columns\TextColumn::make('materialRequisition.document_number')
                    ->label('Request No.')
                    ->searchable(),

                Tables\Columns\TextColumn::make('materialRequisition.user.name')
                    ->label('Requester')
                    ->searchable(),

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
                        ->headerActions([])
            ->actions([
                // Clean table UI
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseMaterials::route('/'),
            'detail-list' => Pages\PurchaseMaterialDetailList::route('/detail-list'),
            'view' => Pages\ViewPurchaseMaterial::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('PURCHASE ORDER');
    }
}
