<?php

namespace App\Filament\Admin\Resources\BeefStockResource\RelationManagers;

use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BeefStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'beefStocks';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('barcode')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'IN_STOCK')->orderBy('pack_date', 'asc'))
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->alignCenter()
                    ->formatStateUsing(function ($state) {
                        if (!is_string($state)) return $state;
                        
                        // Mask 6 digit terakhir (pH & Counter) selama opname
                        // berjalan. Pertanyaannya satu rumah di
                        // `StockTake::isCounting()`.
                        if (\App\Models\StockTake::isCounting() && strlen($state) >= 10) {
                            return substr($state, 0, -6) . '******';
                        }
                        
                        return $state;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('Warehouse'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight (Kg)'))
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ',')),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('ph_level')
                    ->label(__('pH'))
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 1) : '-'),

                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('P.O.D'))
                    ->date('d-M-y')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('age')
                    ->label(__('Age'))
                    ->getStateUsing(fn (BeefStock $record) => $record->pack_date ? sprintf('%03d ', abs((int) now()->diffInDays($record->pack_date))) . __('days') : '')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->alignCenter()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record || !$record->barcode) return $state;
                        $prefix = substr($record->barcode, 0, 1);
                        $map = [
                            '1' => 'BNG',
                            '2' => 'RSTK',
                            '3' => 'RIMP',
                            '4' => 'RRTN',
                            '5' => 'RTRD',
                            '6' => 'RLBT',
                            '7' => 'TRDL',
                            '8' => 'TRDI',
                        ];
                        return $map[$prefix] ?? $state;
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                // Hapus satu baris stok.
                //
                // Gunanya satu: barang yang tercatat di sistem tetapi fisiknya
                // tidak ada. Keputusan Owner, 5 September 2026 -- fiturnya
                // memang dibutuhkan, dengan hak akses tersendiri, dan wajib
                // benar-benar tercatat di `beef_stock_movements`.
                //
                // Tiga hal yang dibetulkan di sini:
                //
                //  - IZINNYA SEKARANG ADA. `delete_beef_stocks` disebut di
                //    sini dan di `BeefStockPolicy`, tetapi tidak pernah dibuat
                //    -- tidak di seeder, tidak di migrasi mana pun. Jadi
                //    `hasPermission()` selalu `false` dan yang lolos hanya
                //    akun programmer; tidak ada cara memberikan hak ini kepada
                //    orang gudang, dan tidak ada gejala yang memberitahu.
                //
                //  - PELAKUNYA DICATAT. Dari dua puluh empat pemanggilan
                //    `BeefStockMovement::create` di seluruh aplikasi, dua
                //    puluh tiga menulis `created_by`. Yang satu tidak: justru
                //    yang ini -- satu-satunya aksi manual yang menghancurkan
                //    baris stok. `BeefStock` tidak memakai hapus lunak, jadi
                //    barisnya benar-benar hilang dan catatan pergerakan inilah
                //    satu-satunya yang tersisa.
                //
                //  - ALASANNYA WAJIB DIISI. Stok yang hilang tanpa dokumen
                //    selalu punya cerita; kalau ceritanya tidak ikut ditulis
                //    saat itu juga, ia tidak akan pernah bisa ditulis lagi.
                Tables\Actions\Action::make('delete')
                    ->label('')
                    ->tooltip(__('Delete stock'))
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Delete this stock item?'))
                    ->modalDescription(__('The item disappears from stock and cannot be brought back. Only do this for goods that are recorded here but are not physically there.'))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('Why is it being deleted?'))
                            ->required()
                            ->maxLength(500)
                            ->rows(2),
                    ])
                    ->visible(fn (): bool => auth()->user()?->isProgrammer()
                        || (auth()->user()?->hasPermission('delete_beef_stocks') ?? false))
                    ->action(function (BeefStock $record, array $data) {
                        BeefStockMovement::create([
                            'product_id' => $record->product_id,
                            'warehouse_id' => $record->warehouse_id,
                            'condition' => $record->grade_id,
                            'barcode' => $record->barcode,
                            'transaction_type' => 'VOID_STOCK',
                            'reference_document' => 'MANUAL_DELETE',
                            'weight_out' => $record->weight,
                            'pcs_out' => $record->qty_pcs,
                            'note' => $data['reason'],
                            'created_by' => auth()->id(),
                        ]);
                        $record->delete();
                    }),
            ])
            ->bulkActions([]);
    }
}
