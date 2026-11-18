<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;

/**
 * Penjaga tahap untuk halaman keputusan permintaan (review purchasing / persetujuan finance).
 *
 * Status dibaca ULANG dari database tepat saat tombol ditekan, bukan dari salinan yang dimuat waktu halaman dibuka:
 * tab lama tidak boleh menyetujui, menolak, atau mengembalikan permintaan yang sudah berpindah tahap.
 */
trait GuardsRequisitionStage
{
    /**
     * @param  list<string>  $allowed  status yang masih boleh diputuskan di halaman ini
     */
    protected function stageStillOpen(array $allowed, string $viewUrl): bool
    {
        $current = $this->record::query()->whereKey($this->record->getKey())->value('status');

        if (in_array($current, $allowed, true)) {
            return true;
        }

        Notification::make()
            ->title(__('This request has already moved on, so this decision was not saved.'))
            ->body(__('Current status') . ': ' . $current . '.')
            ->warning()
            ->send();

        $this->redirect($viewUrl);

        return false;
    }
}
