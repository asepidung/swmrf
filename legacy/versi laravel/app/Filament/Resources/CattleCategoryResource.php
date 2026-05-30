<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CattleCategoryResource\Pages;
use App\Models\CattleCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CattleCategoryResource extends Resource
{
    protected static ?string $model = CattleCategory::class;

    /* Mengatur ikon di sidebar (pake heroicon sapi/box) */
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    /* Mengatur grup navigasi agar rapi */
    protected static ?string $navigationGroup = 'CATTLE';
    protected static ?int $navigationSort = 24;
    protected static ?string $navigationLabel = 'Category';
    protected static ?string $modelLabel = 'Cattle Category';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cattle Category Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('E.G. HEIFER, STEER, COW')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true)
                            ->required(),
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

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Status'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d-M-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCattleCategories::route('/'),
            'create' => Pages\CreateCattleCategory::route('/create'),
            'edit' => Pages\EditCattleCategory::route('/{record}/edit'),
        ];
    }
}
