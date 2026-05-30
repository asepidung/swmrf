<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogisticItemResource\Pages;
use App\Filament\Resources\LogisticItemResource\RelationManagers;
use App\Models\LogisticItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LogisticItemResource extends Resource
{
    protected static ?string $model = LogisticItem::class;
    protected static ?string $navigationGroup = 'LOGISTICS';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Logistic Item Information')
                    ->schema([
                        // 1. KODE OTOMATIS (Logic: LOG + 0000)
                        Forms\Components\TextInput::make('code')
                            ->label('Item Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function () {
                                $lastId = \App\Models\LogisticItem::max('id') ?? 0;
                                return 'LOG' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
                            })
                            ->readOnly(),

                        // 2. NAMA BARANG (Unique & Uppercase)
                        Forms\Components\TextInput::make('name')
                            ->label('Item Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),

                        // 3. UNIT (Tabel Relasi + Tombol Kuning)
                        Forms\Components\Select::make('unit_id')
                            ->label('Unit')
                            ->relationship('unit', 'name')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Unit Name')
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalWidth('md')
                                    ->color('warning')
                                    ->icon('heroicon-m-plus-circle');
                            }),

                        // 4. CATEGORY (Tabel Relasi + Tombol Kuning)
                        Forms\Components\Select::make('logistic_category_id')
                            ->label('Category')
                            ->relationship('logisticCategory', 'name')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Category Name')
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalWidth('md')
                                    ->color('warning')
                                    ->icon('heroicon-m-plus-circle');
                            }),
                        // 5. MIN STOCK
                        Forms\Components\TextInput::make('min_stock')
                            ->label('Min. Stock')
                            ->numeric()
                            ->default(0),

                        // 6. TOGGLE STOCK
                        Forms\Components\Toggle::make('show_in_stock')

                            ->label('Show in Stock List?')
                            ->default(true)
                            ->helperText('Turn OFF for non-inventory items like Office Supplies.')
                            ->columnSpanFull(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('logisticCategory.name')
                    ->label('Category')
                    ->badge()
                    ->color('warning'),

                // --- INI PERBAIKANNYA ---
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->sortable(),
                // ------------------------

                Tables\Columns\IconColumn::make('show_in_stock')
                    ->label('Stockable')
                    ->boolean(),

                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Min. Stock')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->recordUrl(null)
            ->recordAction('view')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->extraAttributes(['style' => 'display: none !important;']),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name', 'asc');
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
            'index' => Pages\ListLogisticItems::route('/'),
            'create' => Pages\CreateLogisticItem::route('/create'),
            'edit' => Pages\EditLogisticItem::route('/{record}/edit'),
        ];
    }
}
