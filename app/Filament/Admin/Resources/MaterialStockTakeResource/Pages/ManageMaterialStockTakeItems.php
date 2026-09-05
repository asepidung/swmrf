<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;

class ManageMaterialStockTakeItems extends ManageRelatedRecords
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected static string $relationship = 'items';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public function getTitle(): string
    {
        // Kuncinya utuh, dokumennya lewat penampung -- bukan kunci yang
        // berakhir " - " lalu disambung teks.
        return __('Input Material Counts for :document', [
            'document' => $this->getOwnerRecord()->document_number,
        ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        $actions[] = Actions\Action::make('back')
            ->label(__('Back to List'))
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        if ($this->getOwnerRecord()->isCountable()) {
            $actions[] = Actions\Action::make('complete_opname')
                ->label(__('Finish Stock Opname'))
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->requiresConfirmation()
                ->modalHeading(__('Finish this stock count?'))
                ->modalDescription(__('Is everything counted carefully? Once you press this, nothing can be changed. Every difference cuts or adds stock permanently, and anything left uncounted is treated as missing.'))
                ->modalSubmitActionLabel(__('Yes, I am sure'))
                // Tombol ini MENGUBAH STOK secara permanen, jadi izinnya
                // sendiri -- sama seperti padanannya di opname daging.
                ->visible(fn (): bool => auth()->user()?->isProgrammer()
                        || (auth()->user()?->hasPermission('finish_material_stock_takes') ?? false))
                ->action(function () {
                    // Satu jalur, di modelnya. Sebelumnya halaman ini dan
                    // halaman Edit punya penerapan sendiri-sendiri dengan
                    // ARTI YANG BERBEDA: yang satu menambahkan selisih, yang
                    // satu menimpa dengan angka hitungan.
                    $this->getOwnerRecord()->applyToStock();

                    Notification::make()->title(__('The stock count is finished and the stock has been updated.'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('items', ['record' => $this->getOwnerRecord()]));
                });
        }

        return $actions;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.pages.manage-stock-take-items-footer');
    }

    public function table(Table $table): Table
    {
        $isCompleted = $this->getOwnerRecord()->status === \App\Models\MaterialStockTake::STATUS_COMPLETED;
        $isInProgress = $this->getOwnerRecord()->isCountable();

        return $table
            ->recordTitleAttribute('id')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('material.code')
                    ->label(__('Item Code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Item Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.unit.name')
                    ->label(__('Unit')),
                Tables\Columns\TextColumn::make('system_qty')
                    ->label(__('System Qty'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isCompleted),
                
                // Only show text input if in progress
                Tables\Columns\TextInputColumn::make('physical_qty')
                    ->label(__('Physical Qty'))
                    ->type('text')
                    ->numeric()
                    ->visible($isInProgress)
                    // Hitungan material selalu BILANGAN BULAT.
                    //
                    // Keputusan Owner: "material itu gak ada qty koma-komaan".
                    //
                    // Penguraian lamanya membuang setiap titik sebagai
                    // pemisah ribuan, sehingga mengetik "12.5" diam-diam
                    // menjadi 125 -- sepuluh kali lipat, tanpa satu pun
                    // gejala, di isian yang langsung memotong atau menambah
                    // stok. Dan di layar pindai daging titik justru pemisah
                    // desimal, jadi dua layar opname di aplikasi yang sama
                    // membaca angka dengan cara yang berlawanan.
                    //
                    // Sekarang yang memuat pemisah desimal DITOLAK, bukan
                    // ditebak.
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->updateStateUsing(function ($record, $state) {
                        $bersih = trim((string) $state);

                        if ($bersih === '') {
                            $record->physical_qty = null;
                            $record->difference_qty = null;
                            $record->save();

                            return;
                        }

                        // Pemisah ribuan boleh diketik, desimal tidak.
                        $angka = str_replace('.', '', $bersih);

                        if (! preg_match('/^\d+$/', $angka)) {
                            Notification::make()
                                ->title(__('Enter a whole number'))
                                ->body(__('Material is counted in whole units, so :value cannot be read.', ['value' => $bersih]))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->physical_qty = (int) $angka;
                        $record->difference_qty = $record->physical_qty - $record->system_qty;
                        $record->save();
                    }),
                
                // Show as text if completed
                Tables\Columns\TextColumn::make('physical_qty_text')
                    ->label(__('Physical Qty'))
                    ->getStateUsing(fn ($record) => $record->physical_qty)
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isCompleted),

                // Aturannya satu rumah di `MaterialStockTakeItem`.
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Variance Status'))
                    ->visible($isCompleted)
                    ->getStateUsing(fn (\App\Models\MaterialStockTakeItem $record): string => $record->varianceLabel())
                    ->badge()
                    ->color(fn ($state, \App\Models\MaterialStockTakeItem $record): string => $record->varianceColor()),
                
                Tables\Columns\TextColumn::make('difference_qty')
                    ->label(__('Difference Qty'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isCompleted)
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
