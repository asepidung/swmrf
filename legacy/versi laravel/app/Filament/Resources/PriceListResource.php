<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceListResource\Pages;
use App\Models\PriceList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'SALES';
    protected static ?string $navigationLabel = 'Price List';
    protected static ?string $pluralModelLabel = 'Price Lists';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                /*
                 * Bagian form utama untuk menentukan grup customer pemilik daftar harga. 
                 */
                Forms\Components\Section::make('Informasi Daftar Harga')
                    ->schema([
                        Forms\Components\Select::make('customer_group_id')
                            ->label('Grup Customer')
                            ->relationship('customerGroup', 'name')
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn(Unique $rule) => $rule->whereNull('deleted_at')
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Satu grup hanya dapat memiliki satu Price List aktif.'),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => auth()->id()),
                    ]),

                /*
                 * Bagian form berulang untuk menambahkan rincian harga per produk beserta catatannya.
                 */
                Forms\Components\Section::make('Detail Harga Produk')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Produk')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('price')
                                    ->label('Harga')
                                    ->prefix('Rp')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('note')
                                    ->label('Catatan (Opsional)')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->addActionLabel('Tambah Produk')
                            ->defaultItems(1)
                            ->reorderableWithButtons(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customerGroup.name')
                    ->label('Grup Customer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diupdate')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                // Tambahkan filter jika diperlukan di kemudian hari
            ])
            ->actions([
                // Tombol Print
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Print Price List')
                    ->url(fn(PriceList $record): string => route('print.pricelist', $record))
                    ->openUrlInNewTab(),

                // Tombol Edit
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Data'),

                // Tombol Delete
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus Data'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListPriceLists::route('/'),
            'create' => Pages\CreatePriceList::route('/create'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }
}
