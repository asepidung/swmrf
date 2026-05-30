<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CattlePurchaseOrderResource\Pages;
use App\Models\CattlePurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;

class CattlePurchaseOrderResource extends Resource
{
    protected static ?string $model = CattlePurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'PURCHASE ORDER';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationLabel = 'PO Cattle';

    /* Mengubah format string mata uang menjadi angka float */
    public static function parseNumber($value): float
    {
        if (blank($value)) return 0.0;
        $val = (string) $value;

        if (preg_match('/^-?\d+(\.\d{1,2})?$/', $val)) {
            return (float) $val;
        }

        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
        return (float) $val;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('PO Header')
                    ->schema([
                        /* Dropdown Supplier dengan preload agar data langsung muncul */
                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('po_date')
                            ->label('Shipping Date')
                            ->required(),

                        Forms\Components\TextInput::make('note')
                            ->label('Header Note')
                            ->columnSpanFull()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                    ])->columns(2),

                Forms\Components\Section::make('Cattle Details')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                /* Dropdown Kategori Sapi dengan preload */
                                Forms\Components\Select::make('cattle_category_id')
                                    ->relationship('cattleCategory', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->hiddenLabel()
                                    ->placeholder('Category')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Category Name')
                                            ->required()
                                            ->unique('cattle_categories', 'name')
                                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                                        Forms\Components\Toggle::make('is_active')
                                            ->default(true)
                                            ->label('Active'),
                                    ]),

                                /* Input Qty dengan format ribuan otomatis */
                                Forms\Components\TextInput::make('qty_head')
                                    ->required()
                                    ->hiddenLabel()
                                    ->placeholder('Qty / Head')
                                    ->formatStateUsing(fn($state) => $state ? number_format(self::parseNumber($state), 0, ',', '.') : '')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->dehydrateStateUsing(fn($state) => self::parseNumber($state)),

                                /* Input Harga per Kg dengan format ribuan otomatis */
                                Forms\Components\TextInput::make('price_per_kg')
                                    ->prefix('Rp')
                                    ->required()
                                    ->hiddenLabel()
                                    ->placeholder('Price / Kg')
                                    ->formatStateUsing(fn($state) => $state ? number_format(self::parseNumber($state), 0, ',', '.') : '')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->dehydrateStateUsing(fn($state) => self::parseNumber($state)),

                                Forms\Components\TextInput::make('note')
                                    ->hiddenLabel()
                                    ->placeholder('Item Note')
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add Cattle')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')

            // 1. KLIK BARIS ARAHKAN KE HALAMAN VIEW
            ->recordUrl(fn($record) => Pages\ViewCattlePurchaseOrder::getUrl([$record->id]))

            ->columns([
                Tables\Columns\TextColumn::make('po_number')->label('PO No.')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('PO Date')->date('d-M-Y')->sortable(),
                Tables\Columns\TextColumn::make('po_date')->label('Shipping Date')->date('d-M-Y')->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->searchable(),
                Tables\Columns\TextColumn::make('note')->label('Note')->limit(50),
            ])
            ->filters([
                /* Filter berdasarkan tanggal pembuatan PO */
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From PO Date')
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until PO Date')
                            ->default(now()),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn($q, $date) => $q->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'],
                                fn($q, $date) => $q->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])

            // 2. KOSONGKAN ACTIONS BIAR TABEL BERSIH (Tombol pindah ke View)
            ->actions([])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCattlePurchaseOrders::route('/'),
            'create' => Pages\CreateCattlePurchaseOrder::route('/create'),
            // 3. DAFTARKAN ROUTE VIEW DI SINI
            'view' => Pages\ViewCattlePurchaseOrder::route('/{record}'),
            'edit' => Pages\EditCattlePurchaseOrder::route('/{record}/edit'),
        ];
    }
}
