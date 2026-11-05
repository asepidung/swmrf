<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Material Counts Detail');
    }

    protected static string $relationship = 'items';


    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        // Aturan yang sama dengan halaman input (ManageMaterialStockTakeItems), keputusan Owner 17 September 2026:
        // stok sistem dan selisih TIDAK terlihat selama menghitung (hitungan buta). Terlihat sesudah COMPLETED, atau
        // selama REVIEW hanya untuk pemegang izin finish_material_stock_takes. Tabel ini dulu menampilkannya
        // selalu, sehingga penghitung bisa melihat selisih sebelum opname selesai (ditemukan 5 November 2026).
        $dokumen = $this->getOwnerRecord();
        $selesai = $dokumen->status === \App\Models\MaterialStockTake::STATUS_COMPLETED;
        $bisaMeninjau = $dokumen->status === \App\Models\MaterialStockTake::STATUS_REVIEW
            && (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('finish_material_stock_takes') ?? false));
        $lihatSelisih = $selesai || $bisaMeninjau;

        return $table
            ->recordTitleAttribute('id')
            ->paginated(false)
            // Urut abjad menurut nama barang (permintaan Owner, 5 November 2026), bukan urutan masuk.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy(
                \App\Models\Material::select('name')->whereColumn('materials.id', 'material_stock_take_items.material_id')
            ))
            ->columns([
                Tables\Columns\TextColumn::make('material.code')
                    ->label(__('Item Code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Item Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('system_qty')
                    ->label(__('System Qty'))
                    ->visible($selesai)
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('physical_qty')
                    ->label(__('Physical Qty'))
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.'),
                // Aturannya satu rumah di `MaterialStockTakeItem`.
                //
                // Berkas ini memakai kunci `__('Sesuai')` yang pada #285
                // sudah diganti di berkas sebelah DAN dihapus dari kedua
                // berkas bahasa -- sehingga kuncinya tidak punya terjemahan
                // sama sekali dan pengguna berbahasa Inggris membaca
                // "Sesuai". Perapian yang setengah jalan meninggalkan
                // kerusakan yang lebih senyap daripada sebelum dirapikan.
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Variance Status'))
                    ->visible($lihatSelisih)
                    ->getStateUsing(fn (\App\Models\MaterialStockTakeItem $record): string => $record->varianceLabel())
                    ->badge()
                    ->color(fn ($state, \App\Models\MaterialStockTakeItem $record): string => $record->varianceColor()),
                Tables\Columns\TextColumn::make('difference_qty')
                    ->label(__('Difference Qty'))
                    ->visible($lihatSelisih)
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn ($state) => $state > 0 ? 'info' : ($state < 0 ? 'danger' : 'success')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
