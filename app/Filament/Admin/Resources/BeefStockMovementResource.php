<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BeefStockMovementResource\Pages;
use App\Models\BeefStockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;

class BeefStockMovementResource extends Resource
{
    protected static ?string $model = BeefStockMovement::class;

    protected static ?string $cluster = \App\Filament\Clusters\BeefStocks::class;

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

    public static function canViewAny(): bool
    {
        return auth()->user()->isProgrammer() || auth()->user()->hasPermission('view_beef_stock_movements');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make(__('Stock Movement Details'))
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label(__('Timestamp'))
                            ->dateTime('d/m/Y H:i'),
                        \Filament\Infolists\Components\TextEntry::make('reference_document')
                            ->label(__('Reference Document')),
                        \Filament\Infolists\Components\TextEntry::make('barcode')
                            ->label(__('Barcode')),
                        \Filament\Infolists\Components\TextEntry::make('product.name')
                            ->label(__('Product')),
                        \Filament\Infolists\Components\TextEntry::make('warehouse.name')
                            ->label(__('Warehouse')),
                        \Filament\Infolists\Components\TextEntry::make('grade.name')
                            ->label(__('Grade')),
                        \Filament\Infolists\Components\TextEntry::make('transaction_type')
                            ->label(__('Transaction Type'))
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'IN_BONING', 'IN_REPACK', 'VOID_OUT_REPACK', 'VOID_STOCK' => 'success',
                                'OUT_TO_REPACK', 'VOID_BONING', 'VOID_IN_REPACK' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => __($state)),
                        \Filament\Infolists\Components\TextEntry::make('weight_in')
                            ->label(__('Weight In'))
                            ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '-'),
                        \Filament\Infolists\Components\TextEntry::make('weight_out')
                            ->label(__('Weight Out'))
                            ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '-'),
                        \Filament\Infolists\Components\TextEntry::make('pcs_in')
                            ->label(__('Pcs In'))
                            ->formatStateUsing(fn ($state) => $state > 0 ? $state : '-'),
                        \Filament\Infolists\Components\TextEntry::make('pcs_out')
                            ->label(__('Pcs Out'))
                            ->formatStateUsing(fn ($state) => $state > 0 ? $state : '-'),
                        \Filament\Infolists\Components\TextEntry::make('creator.name')
                            ->label(__('Operator')),
                        \Filament\Infolists\Components\TextEntry::make('note')
                            ->label(__('Note'))
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
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_document')
                    ->label(__('Reference Document'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->searchable()
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('Warehouse'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_type')
                    ->label(__('Transaction Type'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'IN_BONING', 'IN_REPACK', 'VOID_OUT_REPACK', 'VOID_STOCK' => 'success',
                        'OUT_TO_REPACK', 'VOID_BONING', 'VOID_IN_REPACK' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => __($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('weight_in')
                    ->label(__('Weight In'))
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '-'),

                Tables\Columns\TextColumn::make('weight_out')
                    ->label(__('Weight Out'))
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '-'),

                Tables\Columns\TextColumn::make('pcs_in')
                    ->label(__('Pcs In'))
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '-'),

                Tables\Columns\TextColumn::make('pcs_out')
                    ->label(__('Pcs Out'))
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Operator'))
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ExportAction::make('excel')
                        ->label('Excel')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->exporter(\App\Filament\Exports\BeefStockMovementExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.beef-stock-movements-pdf', [
                                'records' => $records,
                                'title' => __('Stock Movements')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_beef_stock_movements.pdf');
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
                        'IN_BONING' => 'IN_BONING',
                        'VOID_BONING' => 'VOID_BONING',
                        'OUT_TO_REPACK' => 'OUT_TO_REPACK',
                        'VOID_OUT_REPACK' => 'VOID_OUT_REPACK',
                        'IN_REPACK' => 'IN_REPACK',
                        'VOID_IN_REPACK' => 'VOID_IN_REPACK',
                        'VOID_STOCK' => 'VOID_STOCK',
                    ])
                    ->label(__('Transaction Type')),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->label(__('Warehouse')),
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
                Tables\Actions\ViewAction::make(),
            ])
            ->recordUrl(fn (BeefStockMovement $record): string => Pages\ViewBeefStockMovement::getUrl(['record' => $record]))
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeefStockMovements::route('/'),
            'view' => Pages\ViewBeefStockMovement::route('/{record}'),
        ];
    }
}
