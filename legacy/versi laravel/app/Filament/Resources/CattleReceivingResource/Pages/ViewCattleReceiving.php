<?php

namespace App\Filament\Resources\CattleReceivingResource\Pages;

use App\Filament\Resources\CattleReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewCattleReceiving extends ViewRecord
{
    protected static string $resource = CattleReceivingResource::class;

    // SUNTIK DATA MANUAL
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        // FIX DI SINI: Ganti 'items.category' jadi 'items.cattleCategory'
        $record->load(['items.cattleCategory', 'purchaseOrder', 'supplier']);

        $data['po_number_display'] = $record->purchaseOrder->po_number ?? '-';
        $data['supplier_name_display'] = $record->supplier->name ?? '-';

        $receivingItems = [];
        foreach ($record->items as $item) {
            $receivingItems[(string) Str::uuid()] = [
                'cattle_category_id' => $item->cattle_category_id,
                'eartag' => $item->eartag,
                'initial_weight' => $item->initial_weight,
                'notes' => $item->notes,
            ];
        }
        $data['receiving_items'] = $receivingItems;

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
                ->tooltip('Print GRC')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->iconButton()
                ->url(fn($record) => route('print.cattle-receiving', $record->id))
                ->openUrlInNewTab(),

            // Tombol Edit
            Actions\EditAction::make()
                ->tooltip('Edit GRC')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->iconButton(),

            // INI YANG TADI ILANG BRO!
            Actions\DeleteAction::make()
                ->tooltip('Hapus GRC')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->iconButton(),
        ];
    }
}
