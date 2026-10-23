<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRepack extends CreateRecord
{
    protected static string $resource = RepackResource::class;

    /** Tanpa batas susut dari QC, repack belum boleh dibuat (tombol Create di daftar mati; ini penjaga untuk alamat yang dibuka langsung). */
    public function mount(): void
    {
        if (\App\Models\Repack::shrinkLimitPercent() === null) {
            \Filament\Notifications\Notification::make()
                ->title(__('QC has not set the shrinkage limit yet.'))
                ->body(__('Ask QC to fill in the Shrinkage Limit on the Repacks list first.'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));

            return;
        }

        parent::mount();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
