<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    // Ikonnya kita ganti biar keren (pake box/produk)
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'PRODUCTS';
    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Product';
    protected static ?string $modelLabel = 'Product';
    protected static ?string $pluralModelLabel = 'Products';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Produk')
                    ->description('Pilih jenis struktur barang untuk menentukan penomoran otomatis.')
                    ->aside()
                    ->schema([
                        // 1. Pilih Struktur
                        Forms\Components\Select::make('structure_type')
                            ->label('Struktur Barang')
                            ->options([
                                'main' => 'BARANG UTAMA (INDUK)',
                                'sub' => 'BARANG TURUNAN (VARIAN/TS)',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('code', null);
                                $set('parent_id', null);
                            }),

                        // 2. Jika Turunan: Pilih Induknya
                        Forms\Components\Select::make('parent_id')
                            ->label('Barang Induk')
                            ->relationship('parent', 'name', fn($query) => $query->whereNull('parent_id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn(Get $get) => $get('structure_type') === 'sub')
                            ->required(fn(Get $get) => $get('structure_type') === 'sub')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) return;
                                $parent = \App\Models\Product::find($state);

                                $childCount = \App\Models\Product::where('parent_id', $state)->count();
                                $newSuffix = str_pad($childCount + 1, 2, '0', STR_PAD_LEFT);

                                $set('code', substr($parent->code, 0, 4) . $newSuffix);
                                $set('category_id', $parent->category_id);
                            }),

                        // 3. Kategori (Ditambah Tombol Kuning + Uppercase)
                        Forms\Components\Select::make('category_id')
                            ->label('Kategori (Cut)')
                            ->relationship('category', 'name')
                            ->required()
                            ->live()
                            ->disabled(fn(Get $get) => $get('structure_type') === 'sub')
                            ->dehydrated(true)
                            // TOMBOL KUNING TAMBAH KATEGORI
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Kategori Baru')
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                                Forms\Components\TextInput::make('prefix')
                                    ->label('Prefix (Angka)')
                                    ->required()
                                    ->numeric()
                                    ->maxLength(1),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalWidth('md')
                                    ->color('warning')
                                    ->icon('heroicon-m-plus-circle');
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) return;
                                $prefix = \App\Models\Category::find($state)->prefix;

                                $lastItem = \App\Models\Product::where('category_id', $state)
                                    ->whereNull('parent_id')
                                    ->orderBy('code', 'desc')
                                    ->first();

                                $nextSeq = $lastItem ? ((int)substr($lastItem->code, 1, 3) + 1) : 1;
                                $set('code', $prefix . str_pad($nextSeq, 3, '0', STR_PAD_LEFT) . '00');
                            }),

                        // 4. Input Nama (Paksa Huruf Besar)
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(255)
                            // --- TAMBAHKAN VALIDASI INI ---
                            ->unique(ignoreRecord: true) // Biar nggak bisa input nama yang sama
                            ->validationMessages([
                                'unique' => 'Nama barang ini sudah terdaftar di sistem, pakai nama lain!',
                            ])
                            // ------------------------------
                            ->placeholder('CONTOH: TENDERLOIN TS')
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state))
                            ->columnSpanFull(),

                        // 5. Kode Otomatis
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Barang (Otomatis)')
                            ->readOnly()
                            ->required(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->description(fn(Product $record): string => $record->parent_id ? "Varian dari: {$record->parent?->name}" : 'Produk Utama')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PRIME CUT' => 'success',
                        'SECONDARY CUT' => 'warning',
                        'FAT' => 'danger',
                        'OFFAL' => 'danger',
                        'LOGISTIK/SUPPORT' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Status'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Filter Kategori')
                    ->relationship('category', 'name'),
            ])
            ->defaultSort('code', 'asc');
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
