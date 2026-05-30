<?php

namespace App\Filament\Resources\LogisticReceivingResource\Pages;

use App\Filament\Resources\LogisticReceivingResource;
use App\Models\LogisticPurchaseOrder;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;

class DraftLogisticReceiving extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = LogisticReceivingResource::class;

    /* View blade yang bakal kita bikin di langkah 3 */
    protected static string $view = 'filament.resources.logistic-receiving-resource.pages.draft-logistic-receiving';

    protected static ?string $title = 'Draft GR (Pending PO)';

    public function table(Table $table): Table
    {
        return $table
            /* Tarik data PO Logistic yang HANYA status OPEN atau PARTIAL */
            ->query(
                LogisticPurchaseOrder::query()
                    ->whereIn('status', ['OPEN', 'PARTIAL'])
                    ->latest('id')
            )
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('po_date')
                    ->label('PO Date')
                    ->date('d-M-Y')
                    ->sortable(),

                // TAMBAHAN: Biar user tahu ini PO baru atau lagi dicicil kirimnya
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'OPEN' => 'gray',
                        'PARTIAL' => 'warning',
                        default => 'primary',
                    }),

                TextColumn::make('note')
                    ->label('Note')
                    ->limit(50),
            ])
            ->actions([
                /* Tombol Proses GR yang mengarah ke form Create GR */
                Action::make('proses_gr')
                    ->label('Proses GR')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->url(fn($record) => LogisticReceivingResource::getUrl('create', ['po_id' => $record->id])),
            ]);
    }
}
