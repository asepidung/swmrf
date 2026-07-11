<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialUnitResource\Pages;
use App\Filament\Admin\Resources\MaterialUnitResource\RelationManagers;
use App\Models\MaterialUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Pages\SubNavigationPosition;

class MaterialUnitResource extends Resource
{
    protected static ?string $model = MaterialUnit::class;

    protected static ?string $cluster = \App\Filament\Clusters\Materials::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                    ->label(fn() => __('Unit Name'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(fn() => __('Unit Name'))
                    ->searchable(),
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
                fn (\Illuminate\Database\Eloquent\Model $record): string => Pages\EditMaterialUnit::getUrl([$record->id])
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
            'index' => Pages\ListMaterialUnits::route('/'),
            'create' => Pages\CreateMaterialUnit::route('/create'),
            'edit' => Pages\EditMaterialUnit::route('/{record}/edit'),
        ];
    }
}
