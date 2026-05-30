<?php

namespace App\Filament\Resources\CattleWeighingResource\Pages;

use App\Filament\Resources\CattleWeighingResource;
use App\Models\CattleReceiving;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class DraftCattleWeighing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CattleWeighingResource::class;
    protected static string $view = 'filament.resources.cattle-receiving-resource.pages.draft-cattle-receiving'; // Kita pinjam view draft GRC kemarin biar gak repot bikin blade baru

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // KUNCI: Tarik GRC yang belum ada di tabel Weighing
                CattleReceiving::query()
                    ->whereDoesntHave('weighing')
                    ->withCount('items') // Hitung jumlah sapinya
            )
            ->columns([
                Tables\Columns\TextColumn::make('receive_date')->label('Receive Date')->date('d M Y')->color('warning'),
                Tables\Columns\TextColumn::make('receiving_number')->label('GRC Number')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')->label('PO Number')->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier'),
                Tables\Columns\TextColumn::make('items_count')->label('Heads')->suffix(' Heads')->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label('Process Weighing')
                    ->icon('heroicon-o-scale')
                    ->color('success')
                    ->button()
                    ->url(fn($record) => CattleWeighingResource::getUrl('create', ['grc_id' => $record->id])),
            ]);
    }
}
