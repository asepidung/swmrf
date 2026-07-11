<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CattleClassResource\Pages;
use App\Filament\Admin\Resources\CattleClassResource\RelationManagers;
use App\Models\CattleClass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CattleClassResource extends Resource
{
    protected static ?string $model = CattleClass::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }

    public static function getModelLabel(): string
    {
        return __('Cattle Class');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cattle Classes');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                    ->label(fn() => __('Name'))
                    ->autofocus()
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
                    ->label(fn() => __('Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(fn() => __('Updated at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_excel')
                    ->label(fn() => __('Excel'))
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Name', 'Created At']));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->name ?? '',
                                    $record->created_at ? $record->created_at->format('Y-m-d H:i:s') : '',
                                ]));
                            }
                            $writer->close();
                        }, 'Cattle_Classes.xlsx');
                    }),
            ])
            ->actions([
                // 
            ])
            ->recordUrl(
                fn (CattleClass $record): string => Pages\EditCattleClass::getUrl([$record->id])
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
            'index' => Pages\ListCattleClasses::route('/'),
            'create' => Pages\CreateCattleClass::route('/create'),
            'edit' => Pages\EditCattleClass::route('/{record}/edit'),
        ];
    }
}
