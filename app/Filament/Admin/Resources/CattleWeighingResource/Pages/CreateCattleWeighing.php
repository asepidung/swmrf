<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use App\Models\CattleReceiving;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateCattleWeighing extends CreateRecord
{
    protected static string $resource = CattleWeighingResource::class;

    /** Penerimaan yang halaman ini dibuka untuknya; tidak bisa diubah dari browser (CW-01). */
    #[\Livewire\Attributes\Locked]
    public ?int $receivingId = null;

    public function mount(): void
    {
        parent::mount();

        $this->receivingId = request()->query('receiving_id') ? (int) request()->query('receiving_id') : null;
    }

    /**
     * Baris timbang hanya boleh menunjuk ekor milik penerimaan ini, tiap ekor sekali (CW-01). Penerimaan dan id ekor
     * dari browser dikoreksi di server: baris yang menunjuk ekor lain atau ekor kembar dibuang.
     */
    protected function beforeValidate(): void
    {
        if (! $this->receivingId) {
            return;
        }

        $this->data['cattle_receiving_id'] = $this->receivingId;

        $sah = CattleReceiving::find($this->receivingId)?->items()->pluck('id')->all() ?? [];
        $sudah = [];

        foreach (($this->data['items'] ?? []) as $kunci => $baris) {
            $ekor = $baris['cattle_receiving_item_id'] ?? null;

            if (! in_array($ekor, $sah) || in_array($ekor, $sudah)) {
                unset($this->data['items'][$kunci]);

                continue;
            }

            $sudah[] = $ekor;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $record->calculateAndSaveFinancialLoss();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
