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
        return __('Input Material Counts - ') . $this->getOwnerRecord()->document_number;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        $actions[] = Actions\Action::make('back')
            ->label(__('Back to List'))
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        if (in_array($this->getOwnerRecord()->status, ['DRAFT', 'IN_PROGRESS'])) {
            $actions[] = Actions\Action::make('complete_opname')
                ->label(__('Finish Stock Opname'))
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->requiresConfirmation()
                ->modalHeading('PERINGATAN KERAS: Finalisasi Opname!')
                ->modalDescription('Apakah kamu yakin sudah menghitung dengan teliti dan benar? Setelah tombol ini ditekan, data TIDAK BISA diubah lagi. Semua selisih (kurang/lebih) akan langsung memotong atau menambah stok di sistem secara permanen, dan barang yang tidak terhitung akan dianggap hilang/selisih. Pikirkan baik-baik sebelum melanjutkan!')
                ->modalSubmitActionLabel('Ya, Saya Yakin & Selesai')
                ->action(function () {
                    $record = $this->getOwnerRecord();
                    
                    \Illuminate\Support\Facades\DB::transaction(function() use ($record) {
                        foreach ($record->items as $item) {
                            if ($item->difference_qty != 0) {
                                \App\Services\StockService::adjustStock(
                                    $item->material_id,
                                    $item->difference_qty,
                                    'STOCK_OPNAME',
                                    $record->document_number,
                                    'Adjustment from Stock Opname ' . $record->document_number
                                );
                            }
                        }
                        $record->update(['status' => 'COMPLETED']);
                    });
                    
                    Notification::make()->title('Stock Opname berhasil diselesaikan dan stok telah diperbarui.')->success()->send();
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
        $opnameStatus = $this->getOwnerRecord()->status;
        $isCompleted = $opnameStatus === 'COMPLETED';
        $isInProgress = in_array($opnameStatus, ['DRAFT', 'IN_PROGRESS']);

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
                    ->updateStateUsing(function ($record, $state) {
                        if ($state === '' || $state === null) {
                            $record->physical_qty = null;
                            $record->difference_qty = null;
                        } else {
                            // Parse string like "1.234,56" into float 1234.56
                            $cleanState = str_replace('.', '', $state);
                            $cleanState = str_replace(',', '.', $cleanState);
                            $record->physical_qty = (float) $cleanState;
                            $record->difference_qty = $record->physical_qty - $record->system_qty;
                        }
                        $record->save();
                    }),
                
                // Show as text if completed
                Tables\Columns\TextColumn::make('physical_qty_text')
                    ->label(__('Physical Qty'))
                    ->getStateUsing(fn ($record) => $record->physical_qty)
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isCompleted),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Variance Status'))
                    ->visible($isCompleted)
                    ->getStateUsing(function ($record) {
                        if ($record->physical_qty === null) return '-';
                        if ($record->difference_qty > 0) return __('Lebih');
                        if ($record->difference_qty < 0) return __('Kurang');
                        return __('Sesuai');
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        __('Lebih') => 'info',
                        __('Kurang') => 'danger',
                        __('Sesuai') => 'success',
                        default => 'gray',
                    }),
                
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
