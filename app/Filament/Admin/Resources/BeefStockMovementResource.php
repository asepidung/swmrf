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
        return auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('view_beef_stock_movements') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
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
                            // Warnanya ditentukan ARAH pergerakannya, di
                            // `BeefStockMovement::TYPES`. Peta yang ditulis tangan di
                            // sini dulu melewatkan sembilan jenis dan memberi warna
                            // HIJAU pada `VOID_STOCK` -- penghapusan stok manual,
                            // barang KELUAR, ditampilkan seperti barang masuk.
                            ->color(fn ($state): string => BeefStockMovement::typeColor($state))
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
            ->defaultSort('id', 'desc')
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
                    // Warnanya ditentukan ARAH pergerakannya, di
                    // `BeefStockMovement::TYPES`. Peta yang ditulis tangan di
                    // sini dulu melewatkan sembilan jenis dan memberi warna
                    // HIJAU pada `VOID_STOCK` -- penghapusan stok manual,
                    // barang KELUAR, ditampilkan seperti barang masuk.
                    ->color(fn ($state): string => BeefStockMovement::typeColor($state))
                    ->formatStateUsing(fn ($state) => __($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('weight_in')
                    ->label(__('Weight In'))
                    ->alignRight()
                    ->getStateUsing(fn (BeefStockMovement $record) => $record->weight_in > 0 ? number_format((float) $record->weight_in, 2, '.', ',') . '/' . $record->pcs_in : '-'),

                Tables\Columns\TextColumn::make('weight_out')
                    ->label(__('Weight Out'))
                    ->alignRight()
                    ->getStateUsing(fn (BeefStockMovement $record) => $record->weight_out > 0 ? number_format((float) $record->weight_out, 2, '.', ',') . '/' . $record->pcs_out : '-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Operator'))
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
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
                                    __('Timestamp'), __('Reference Document'), __('Barcode'), __('Product'),
                                    __('Warehouse'), __('Grade'), __('Transaction Type'), __('Weight In'),
                                    __('Weight Out'), __('Pcs In'), __('Pcs Out'), __('Note'), __('Operator'),
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->created_at ?? '',
                                        $record->reference_document ?? '',
                                        $record->barcode ?? '',
                                        $record->product?->name ?? '',
                                        $record->warehouse?->name ?? '',
                                        $record->grade?->name ?? '',
                                        $record->transaction_type ?? '',
                                        $record->weight_in ?? '',
                                        $record->weight_out ?? '',
                                        $record->pcs_in ?? '',
                                        $record->pcs_out ?? '',
                                        $record->note ?? '',
                                        $record->creator?->name ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                    Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
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
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                // Pilihannya SELURUH jenis yang benar-benar ditulis aplikasi.
                //
                // Daftar yang ditulis tangan di sini dulu memuat sepuluh dari
                // sembilan belas, dan salah satunya -- `TALLY_REVERT` -- tidak
                // pernah ditulis kode mana pun, jadi memilihnya selalu
                // menghasilkan daftar kosong.
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(BeefStockMovement::typeOptions())
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
            ->actions([])
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
