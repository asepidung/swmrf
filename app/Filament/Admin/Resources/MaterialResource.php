<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialResource\Pages;
use App\Filament\Admin\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $cluster = \App\Filament\Clusters\Materials::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Material Items';

    protected static ?string $modelLabel = 'Material Item';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Logistic Item Information'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('Item Code'))
                            ->placeholder(__('Auto-generated'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('name')
                            ->label(__('Item Name'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                        Forms\Components\Select::make('material_unit_id')
                            ->label(__('Unit'))
                            ->relationship('unit', 'name')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        Forms\Components\Select::make('material_category_id')
                            ->label(__('Category'))
                            ->relationship('category', 'name')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                            ]),
                        Forms\Components\TextInput::make('min_stock')
                            ->label(__('Min. Stock'))
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('show_in_stock')
                            ->label(__('Show in Stock List?'))
                            ->helperText(__('Turn OFF for non-inventory items like Office Supplies.'))
                            ->default(true)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Is Active'))
                            ->default(true)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Item Code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Item Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label(__('Unit'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label(__('Min. Stock'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_in_stock')
                    ->label(__('Show in Stock'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->recordUrl(
                fn (\Illuminate\Database\Eloquent\Model $record): string => Pages\EditMaterial::getUrl([$record->id])
            )
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
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
