<?php

namespace App\Filament\Resources\CarcassResource\Pages;

use App\Filament\Resources\CarcassResource;
use App\Models\CattleWeighing;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class DraftCarcass extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CarcassResource::class;

    // Kita pinjam view bawaan table filament aja biar gampang
    protected static string $view = 'filament.resources.cattle-receiving-resource.pages.draft-cattle-receiving';
    protected static ?string $title = 'Draft Antrean Karkas';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // KUNCI: Tarik Weighing yang itemnya MASIH ADA yang belum masuk ke CarcassItem
                CattleWeighing::query()
                    ->whereHas('items', function (Builder $query) {
                        $query->doesntHave('carcassItem');
                    })
                    // Hitung jumlah sisa sapinya aja (yang belum ada carcassItem-nya)
                    ->withCount(['items as pending_heads' => function (Builder $query) {
                        $query->doesntHave('carcassItem');
                    }])
            )
            ->columns([
                Tables\Columns\TextColumn::make('weigh_no')
                    ->label('Weighing No')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('weigh_date')
                    ->label('Weigh Date')
                    ->date('d M Y')
                    ->color('warning'),
                Tables\Columns\TextColumn::make('receiving.purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('receiving.supplier.name')
                    ->label('Supplier'),
                Tables\Columns\TextColumn::make('pending_heads')
                    ->label('Sisa Sapi')
                    ->suffix(' Ekor')
                    ->badge()
                    ->color('danger'), // Warna merah biar tau ini sisa yang harus diberesin
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label('Proses Karkas')
                    ->icon('heroicon-o-scissors')
                    ->color('success')
                    ->button()
                    ->url(fn($record) => CarcassResource::getUrl('create', ['weigh_id' => $record->id])),
            ]);
    }
}
