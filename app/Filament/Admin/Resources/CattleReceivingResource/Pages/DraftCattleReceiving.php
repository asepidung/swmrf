<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use App\Models\PurchaseCattle;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class DraftCattleReceiving extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CattleReceivingResource::class;

    protected static string $view = 'filament.admin.resources.cattle-receiving-resource.pages.draft-cattle-receiving';

    public function getTitle(): string
    {
        return __('Draft Cattle Receiving');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PurchaseCattle::query()
                    ->withSum('items', 'qty')
                    ->whereDoesntHave('receivings')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('PO Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('items_sum_qty')
                    ->label(__('Qty Head'))
                    ->numeric()
                    ->suffix(' Heads')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('primary'),
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label(__('Process'))
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->button()
                    ->url(fn (PurchaseCattle $record): string => CattleReceivingResource::getUrl('create', ['po_id' => $record->id])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
