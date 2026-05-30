<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BeefPurchaseOrderResource\Pages;
use App\Models\BeefPurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class BeefPurchaseOrderResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = BeefPurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'PURCHASE ORDER';
    protected static ?string $navigationLabel = 'PO Beef';
    protected static ?int $navigationSort = 14;

    public static function getPermissionPrefixes(): array
    {
        return ['view_any', 'view', 'create', 'update', 'delete'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('PO Information')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->disabled(),

                        Forms\Components\Select::make('beef_requisition_id')
                            ->label('Request Reference')
                            ->relationship('requisition', 'document_number')
                            ->disabled(),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->disabled(),

                        Forms\Components\DatePicker::make('po_date')
                            ->label('PO Date')
                            ->disabled(),

                        Forms\Components\Select::make('approved_by')
                            ->label('Approved By (Finance)')
                            ->relationship('approver', 'name')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('PO Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->disabled()
                                    ->columnSpan(5),

                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->disabled()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('price')
                                    ->label('Price')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->disabled()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->disabled()
                                    ->columnSpan(3),
                            ])
                            ->columns(12)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),
                    ]),

                Forms\Components\Section::make('Summary')
                    ->schema([
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->disabled()
                            ->columnSpan(8),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Grand Total')
                            ->prefix('Rp')
                            ->numeric()
                            ->disabled()
                            ->columnSpan(4)
                            ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
                    ])->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('po_number')->label('PO Number')->searchable(),
                Tables\Columns\TextColumn::make('requisition.document_number')->label('Request Ref')->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->searchable(),
                Tables\Columns\TextColumn::make('po_date')->label('PO Date')->date()->sortable(),
                Tables\Columns\TextColumn::make('approver.name')->label('Approved By'),
                Tables\Columns\TextColumn::make('note')->label('Note')->limit(50),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-s-printer')
                    ->color('success')
                    ->tooltip('Print PO')
                    ->iconButton()
                    ->url(fn($record) => route('print.beef-po', ['id' => $record->id]))
                    ->openUrlInNewTab(),
            ])

            ->filters([
                // Filter tanggal
                Tables\Filters\Filter::make('po_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('date_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['date_from'], fn($q, $date) => $q->whereDate('po_date', '>=', $date))
                            ->when($data['date_until'], fn($q, $date) => $q->whereDate('po_date', '<=', $date));
                    })
            ]);
    }

    public static function getPages(): array
    {
        // Sengaja kita matiin 'create' dan 'edit' karena PO otomatis dari persetujuan
        return [
            'index' => Pages\ListBeefPurchaseOrders::route('/'),
            'view' => Pages\ViewBeefPurchaseOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
