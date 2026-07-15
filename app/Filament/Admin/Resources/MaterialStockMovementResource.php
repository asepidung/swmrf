<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialStockMovementResource\Pages;
use App\Models\MaterialStockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;

class MaterialStockMovementResource extends Resource
{
    protected static ?string $model = MaterialStockMovement::class;

    protected static ?string $cluster = \App\Filament\Clusters\MaterialsStock::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    public static function getNavigationLabel(): string
    {
        return __('Stock Movements');
    }

    public static function getModelLabel(): string
    {
        return __('Stock Movement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Stock Movements');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Stock Movement Details'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label(__('Timestamp'))
                            ->disabled(),
                        Forms\Components\TextInput::make('reference_document')
                            ->label(__('Reference Document'))
                            ->disabled(),
                        Forms\Components\TextInput::make('material.name')
                            ->label(__('Material'))
                            ->disabled(),
                        Forms\Components\TextInput::make('transaction_type')
                            ->label(__('Transaction Type'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => __($state)),
                        Forms\Components\TextInput::make('qty_in')
                            ->label(__('Qty In'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                        Forms\Components\TextInput::make('qty_out')
                            ->label(__('Qty Out'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                        Forms\Components\TextInput::make('balance')
                            ->label(__('Balance'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                        Forms\Components\TextInput::make('creator.name')
                            ->label(__('Operator'))
                            ->disabled(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(3)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Timestamp'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
                Tables\Columns\TextColumn::make('reference_document')
                    ->label(__('Reference Document'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Material'))
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
                Tables\Columns\TextColumn::make('transaction_type')
                    ->label(__('Transaction Type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'GR' => 'success',
                        'ISSUE' => 'danger',
                        'ADJUSTMENT' => 'warning',
                        'RETUR' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __($state))
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
                Tables\Columns\TextColumn::make('qty_in')
                    ->label(__('Qty In'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable()
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
                Tables\Columns\TextColumn::make('qty_out')
                    ->label(__('Qty Out'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable()
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('Balance'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable()
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Operator'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->tooltip(fn (MaterialStockMovement $record): ?string => $record->note),
            ])
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Timestamp', 'Reference Document', 'Material', 'Transaction Type', 'Qty In', 'Qty Out', 'Balance', 'Note', 'Operator'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->created_at ?? '',
                                        $record->reference_document ?? '',
                                        $record->material?->name ?? '',
                                        $record->transaction_type ?? '',
                                        $record->qty_in ?? '',
                                        $record->qty_out ?? '',
                                        $record->balance ?? '',
                                        $record->note ?? '',
                                        $record->creator?->name ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.material-stock-movements-pdf', [
                                'records' => $records,
                                'title' => __('Stock Movements')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_stock_movements.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options([
                        'GR' => 'GR',
                        'ISSUE' => 'ISSUE',
                        'ADJUSTMENT' => 'ADJUSTMENT',
                        'RETUR' => 'RETUR',
                    ])
                    ->label(__('Transaction Type')),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(fn (array $data): array => []),
            ])
            ->actions([
                // Read-only, clickable row handles navigation
            ])
            ->bulkActions([
                // Read-only log
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->defaultSort('id', 'desc');
    }

    public static function getRecordUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return null;
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
            'index' => Pages\ListMaterialStockMovements::route('/'),
            'view' => Pages\ViewMaterialStockMovement::route('/{record}'),
        ];
    }
}
