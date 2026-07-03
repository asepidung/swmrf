<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMutation extends ViewRecord
{
    protected static string $resource = MutationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('receive')
                ->label('Terima Mutasi')
                ->hiddenLabel()
                ->tooltip('Terima Mutasi')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === 'SENT')
                ->action(function ($record) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                        foreach ($record->items as $item) {
                            \App\Models\BeefStock::create([
                                'barcode' => $item->barcode,
                                'product_id' => $item->product_id,
                                'warehouse_id' => $record->to_warehouse_id,
                                'grade_id' => $item->grade_id,
                                'weight' => $item->weight,
                                'qty_pcs' => $item->qty_pcs,
                                'ph_level' => $item->ph_level,
                                'pack_date' => $item->pack_date,
                                'exp_date' => $item->exp_date,
                                'origin' => $item->origin,
                                'status' => 'IN_STOCK',
                                'note' => 'Mutasi Masuk ' . $record->mutation_number,
                            ]);

                            \App\Models\BeefStockMovement::create([
                                'product_id' => $item->product_id,
                                'warehouse_id' => $record->to_warehouse_id,
                                'condition' => $item->grade_id,
                                'barcode' => $item->barcode,
                                'transaction_type' => 'MUTATION_IN',
                                'reference_document' => $record->mutation_number,
                                'weight_in' => $item->weight,
                                'weight_out' => 0,
                                'pcs_in' => $item->qty_pcs,
                                'pcs_out' => 0,
                                'note' => 'Penerimaan Mutasi',
                                'created_by' => auth()->id() ?? 1,
                            ]);
                        }
                        $record->update([
                            'status' => 'COMPLETED',
                            'received_by' => auth()->id() ?? 1
                        ]);
                    });
                    \Filament\Notifications\Notification::make()->title('Mutasi berhasil diterima')->success()->send();
                }),

            Actions\Action::make('print')
                ->label('Cetak Laporan')
                ->hiddenLabel()
                ->tooltip('Cetak Laporan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record) => route('filament.admin.resources.mutations.print', ['record' => $record]))
                ->openUrlInNewTab(),

            Actions\Action::make('scan')
                ->label('Scan Barang')
                ->hiddenLabel()
                ->tooltip('Scan Barang')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn ($record) => MutationResource::getUrl('scan', ['record' => $record]))
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\EditAction::make()
                ->hiddenLabel()
                ->tooltip('Edit')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\DeleteAction::make()
                ->hiddenLabel()
                ->tooltip('Delete')
                ->icon('heroicon-o-trash')
                ->visible(fn ($record) => $record->status === 'DRAFT' && $record->items()->count() === 0),
                
            Actions\Action::make('back')
                ->label('Kembali')
                ->hiddenLabel()
                ->tooltip('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MutationResource::getUrl('index')),
        ];
    }
}
