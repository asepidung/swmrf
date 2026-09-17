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
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
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
            ->defaultSort('name')
            ->actions([
                //
            ])
            ->recordUrl(
                fn (\Illuminate\Database\Eloquent\Model $record): string => Pages\EditMaterialUnit::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Kembar dengan MaterialCategoryResource -- lihat penjelasan di sana.
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $dilewati = 0;

                            foreach ($records as $record) {
                                if ($record->materials()->exists()) {
                                    $dilewati++;

                                    continue;
                                }

                                \App\Support\MasterDataDeletion::attempt(
                                    fn () => $record->delete(),
                                    __('Material Unit').' '.$record->name,
                                );
                            }

                            if ($dilewati > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title(__('Some material units were not deleted'))
                                    ->body(__(':count of the selected material units are still used by existing materials and were skipped.', ['count' => $dilewati]))
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
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

