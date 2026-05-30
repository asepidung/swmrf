<?php

namespace App\Filament\Resources\CattleReceivingResource\Pages;

use App\Filament\Resources\CattleReceivingResource;
use App\Models\CattlePurchaseOrder;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class DraftCattleReceiving extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CattleReceivingResource::class;
    protected static string $view = 'filament.resources.cattle-receiving-resource.pages.draft-cattle-receiving';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\CattlePurchaseOrder::query()
                    ->withSum('items', 'qty_head') // Gunakan qty_head sesuai DDL
                    ->whereDoesntHave('receivings')
            )
            ->columns([
                Tables\Columns\TextColumn::make('po_date')
                    ->label('PO Date')
                    ->date('d M Y')
                    ->sortable()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('items_sum_qty_head') // Hasil dari withSum
                    ->label('Qty Head')
                    ->numeric()
                    ->suffix(' Heads')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('primary'),
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->button()
                    ->url(fn($record) => CattleReceivingResource::getUrl('create', ['po_id' => $record->id])),
            ]);
    }
}
