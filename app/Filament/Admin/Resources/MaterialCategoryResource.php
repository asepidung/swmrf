<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialCategoryResource\Pages;
use App\Filament\Admin\Resources\MaterialCategoryResource\RelationManagers;
use App\Models\MaterialCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialCategoryResource extends Resource
{
    protected static ?string $model = MaterialCategory::class;

    protected static ?string $cluster = \App\Filament\Clusters\Materials::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Category Name'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Category Name'))
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
                fn (\Illuminate\Database\Eloquent\Model $record): string => Pages\EditMaterialCategory::getUrl([$record->id])
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
            'index' => Pages\ListMaterialCategories::route('/'),
            'create' => Pages\CreateMaterialCategory::route('/create'),
            'edit' => Pages\EditMaterialCategory::route('/{record}/edit'),
        ];
    }
}
