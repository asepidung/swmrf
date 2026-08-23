<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermission('view_warehouses');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('view_warehouses');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }

    public static function getModelLabel(): string
    {
        return __('Warehouse');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Warehouses');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label(fn () => __('Code'))
                    ->autofocus()
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Forms\Components\TextInput::make('name')
                    ->label(fn () => __('Name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                Forms\Components\Toggle::make('is_active')
                    ->label(fn () => __('Active'))
                    ->default(true)
                            ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(fn () => __('Code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label(fn () => __('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn () => __('Active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn () => __('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id')
            ->headerActions([
                Tables\Actions\Action::make('export_excel')
                    ->label(fn () => __('Excel'))
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();

                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Code', 'Name', 'Active']));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([$record->code ?? '', $record->name ?? '', $record->is_active ? 'YES' : 'NO']));
                            }
                            $writer->close();
                        }, 'Warehouses.xlsx');
                    }),
            ])
            ->actions([
                // Baris tabel dibuat clickable, tombol Edit statis tidak diperlukan
            ])
            ->recordUrl(
                fn (Warehouse $record): string => Pages\EditWarehouse::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
