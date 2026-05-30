<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogisticCategoryResource\Pages;
use App\Models\LogisticCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LogisticCategoryResource extends Resource
{
    protected static ?string $model = LogisticCategory::class;

    protected static ?string $navigationGroup = 'LOGISTICS';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Logistic Category';
    protected static ?string $modelLabel = 'Logistic Category';
    protected static ?string $pluralModelLabel = 'Logistic Categories';
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Detail')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            // SOP: Auto Uppercase
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable(),
            ])
            // SOP: Click Row = View Modal
            ->recordUrl(null)
            ->recordAction('view')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->extraAttributes(['style' => 'display: none !important;']),
                Tables\Actions\EditAction::make(),
                // NO DELETE BUTTON
            ])
            ->bulkActions([])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogisticCategories::route('/'),
            'create' => Pages\CreateLogisticCategory::route('/create'),
            'edit' => Pages\EditLogisticCategory::route('/{record}/edit'),
        ];
    }
}
