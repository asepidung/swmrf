<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TallyResource\Pages;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TallyResource extends Resource
{
    protected static ?string $model = Tally::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getNavigationGroup(): ?string
    {
        return __('WAREHOUSE');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tally');
    }

    public static function getModelLabel(): string
    {
        return __('Tally');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tallies');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Tally $record): string => static::getUrl('view', ['record' => $record->id]))
            ->columns([
                Tables\Columns\TextColumn::make('tally_number')
                    ->label(__('Tally Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('salesOrder.so_number')
                    ->label(__('SO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('salesOrder.customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesOrder.delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        // 'do' dulu TIDAK ADA di peta ini, padahal ia status
                        // yang paling banyak dimiliki dokumen sungguhan
                        // (diperiksa di hosting: do=3). Tally yang sudah
                        // menjadi surat jalan tampil tanpa warna, terbaca
                        // seperti keadaan yang tidak dikenali -- persis bug
                        // yang sama dengan 'on_delivery' di Sales Order.
                        'info' => Tally::STATUS_PROCESSING,
                        'success' => Tally::STATUS_LOCKED,
                        'gray' => Tally::STATUS_DELIVERED,
                    ])
                    // 'do' dulu tampil sebagai "Do" -- hasil `ucfirst()` pada
                    // singkatan, yang tidak berarti apa-apa bagi pembacanya.
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Tally::STATUS_LOCKED => __('Approved'),
                        Tally::STATUS_DELIVERED => __('Delivery Order'),
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_tallies')),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('salesOrder.customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['created_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['created_until'] ?? now()->toDateString();

                        return $query
                            ->when(
                                $from,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['created_from'])->format('d M Y');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['created_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordClasses(fn (Tally $record) => match (true) {
                $record->trashed() => 'bg-danger-50 dark:bg-danger-900/20',
                default => null,
            })
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Tally Number', 'SO Number', 'Customer', 'Delivery Date', 'Status', 'Created At', 'Created By']));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->tally_number ?? '',
                                        $record->salesOrder?->so_number ?? '',
                                        $record->salesOrder?->customer?->name ?? '',
                                        $record->salesOrder?->delivery_date ? \Carbon\Carbon::parse($record->salesOrder->delivery_date)->format('Y-m-d') : '',
                                        $record->status ?? '',
                                        $record->created_at ? $record->created_at->format('Y-m-d H:i') : '',
                                        $record->creator?->name ?? '',
                                    ]));
                                }
                                $writer->close();
                            }, 'Tallies.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->actions([
                \App\Filament\Admin\Resources\QcReportResource\Actions\LihatLaporanQc::make(),
Tables\Actions\Action::make('scan')
                    ->label('')
                    ->iconButton()
                    ->icon('heroicon-m-qr-code')
                    ->color('primary')
                    ->tooltip(__('Scan'))
                    ->url(fn (Tally $record): string => static::getUrl('scan', ['record' => $record->id]))
                    ->visible(fn (Tally $record) => $record->status === Tally::STATUS_PROCESSING && $record->salesOrder?->status !== SalesOrder::STATUS_CANCELLED),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (\Illuminate\Support\Collection $records) => $records->each->delete()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTallies::route('/'),
            'draft' => Pages\DraftTally::route('/draft'),
            'scan' => Pages\ScanTally::route('/{record}/scan'),
            'view' => Pages\ViewTally::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_tallies',
        );
    }
}
