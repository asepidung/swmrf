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
            Actions\Action::make('scan')
                ->label('Scan Barang')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn ($record) => MutationResource::getUrl('scan', ['record' => $record]))
                ->visible(fn ($record) => $record->status === 'DRAFT'),

            Actions\Action::make('terima_mutasi')
                ->label('Terima Mutasi (Bulk)')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Terima Mutasi')
                ->modalDescription('Apakah Anda yakin ingin menerima mutasi ini? Semua stok barang yang di-scan akan dipindahkan secara bulk ke Gudang Tujuan dan mutasi ini akan dikunci (COMPLETED).')
                ->modalSubmitActionLabel('Ya, Terima')
                ->visible(fn ($record) => $record->status === 'DRAFT')
                ->action(function ($record) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                        $items = $record->items;
                        foreach ($items as $item) {
                            $stock = \App\Models\BeefStock::where('barcode', $item->barcode)->first();
                            if ($stock) {
                                // 1. Catat perpindahan gudang (Out dari Asal)
                                \App\Models\BeefStockMovement::create([
                                    'beef_stock_id' => $stock->id,
                                    'movement_type' => 'MUTATION_OUT',
                                    'warehouse_id' => $stock->warehouse_id,
                                    'reference_number' => $record->mutation_number,
                                    'description' => 'Dipindahkan via mutasi ke gudang tujuan.',
                                    'created_by' => auth()->id(),
                                ]);

                                // 2. Update stock ke gudang tujuan & buka kuncian
                                $stock->warehouse_id = $record->to_warehouse_id;
                                $stock->status = 'IN_STOCK';
                                $stock->save();

                                // 3. Catat perpindahan gudang (In ke Tujuan)
                                \App\Models\BeefStockMovement::create([
                                    'beef_stock_id' => $stock->id,
                                    'movement_type' => 'MUTATION_IN',
                                    'warehouse_id' => $record->to_warehouse_id,
                                    'reference_number' => $record->mutation_number,
                                    'description' => 'Diterima dari gudang asal via mutasi.',
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }

                        $record->update([
                            'status' => 'COMPLETED',
                            'received_by' => auth()->id(),
                        ]);
                    });

                    \Filament\Notifications\Notification::make()
                        ->title('Mutasi berhasil diterima!')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('print')
                ->label('Cetak Laporan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record) => route('filament.admin.resources.mutations.print', ['record' => $record]))
                ->openUrlInNewTab(),

            Actions\EditAction::make(),
        ];
    }
}
