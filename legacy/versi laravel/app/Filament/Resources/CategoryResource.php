<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationGroup = 'PRODUCTS';
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationLabel = 'Product Category';
    protected static ?string $modelLabel = 'Product Category';
    protected static ?string $pluralModelLabel = 'Product Categories';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kategori')
                    ->description('Input prefix sesuai buku panduan produksi untuk menentukan pola kode barang.')
                    ->aside() // Membuat judul di kiri dan input di kanan agar terlihat profesional
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Kategori')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: PRIME CUT'),

                        Forms\Components\TextInput::make('prefix')
                            ->label('Kode Prefix (Angka Depan)')
                            ->numeric()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Gunakan angka 1-9 sesuai standarisasi Wijaya Meat.'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prefix')
                    ->label('Prefix')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable(),
            ])
            ->defaultSort('prefix', 'asc');
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
