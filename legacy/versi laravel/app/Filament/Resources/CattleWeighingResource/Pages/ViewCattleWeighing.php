<?php

namespace App\Filament\Resources\CattleWeighingResource\Pages;

use App\Filament\Resources\CattleWeighingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewCattleWeighing extends ViewRecord
{
    protected static string $resource = CattleWeighingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->load(['items.receivingItem', 'receiving.purchaseOrder', 'receiving.supplier']);

        $data['grc_number_display'] = $record->receiving->receiving_number ?? '-';
        $data['po_number_display'] = $record->receiving->purchaseOrder->po_number ?? '-';
        $data['supplier_name_display'] = $record->receiving->supplier->name ?? '-';

        $weighingItems = [];
        $totalInitial = 0;
        $totalActual = 0;
        $heads = 0;

        foreach ($record->items as $item) {
            $initW = (float) ($item->receivingItem->initial_weight ?? 0);
            $actW = (float) ($item->weight ?? 0);

            $totalInitial += $initW;
            $totalActual += $actW;
            $heads++;

            $weighingItems[(string) Str::uuid()] = [
                'cattle_receiving_item_id' => $item->cattle_receiving_item_id,
                'eartag_display' => $item->receivingItem->eartag ?? '-',
                'initial_weight_display' => $initW,
                'weight' => $actW,
                'notes' => $item->notes,
            ];
        }
        $data['weighing_items'] = $weighingItems;

        // INJEK HASIL PERHITUNGAN KE FORM SUMMARY
        $data['summary_heads'] = $heads;
        $data['summary_initial'] = $totalInitial;
        $data['summary_actual'] = $totalActual;
        $data['summary_diff'] = $totalActual - $totalInitial; // Minus = Susut, Plus = Gain

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Tombol Kembali
            Actions\Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),

            // Tombol Print
            Actions\Action::make('print')
                ->tooltip('Print Weighing')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->iconButton()
                ->url(fn($record) => route('print.weighing', $record->id))
                ->openUrlInNewTab(),

            // Tombol Edit
            Actions\EditAction::make()
                ->tooltip('Edit Weighing')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->iconButton(),

            // Tombol Delete
            Actions\DeleteAction::make()
                ->tooltip('Hapus Weighing')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->iconButton(),
        ];
    }

    public function getFormActions(): array
    {
        return [
            // TOMBOL BACK
            Actions\Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),

            // TOMBOL EDIT
            Actions\EditAction::make()
                ->label('Edit Data')
                ->color('warning')
                ->icon('heroicon-o-pencil-square'),

            // TOMBOL PRINT
            Actions\Action::make('print')
                ->label('Print Weighing')
                ->color('success')
                ->icon('heroicon-o-printer')
                ->url(fn($record) => route('print.weighing', $record->id))
                ->openUrlInNewTab(),
        ];
    }
}
