<?php

namespace App\Filament\Admin\Resources\QcReportResource\Pages;

use App\Filament\Admin\Resources\QcReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Mengisi laporan QC.
 *
 * Barisnya sudah ada sebelum halaman ini dibuka -- ia lahir sebagai tugas
 * begitu dokumen pasangannya dibuat. Yang dikerjakan di sini MENGISINYA.
 */
class EditQcReport extends EditRecord
{
    protected static string $resource = QcReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Menyimpan berarti laporannya selesai dikerjakan.
     *
     * `submitted_at` dan `created_by` diisi di sini, bukan saat barisnya
     * lahir. Saat lahir belum ada seorang pun yang menulis apa pun, dan
     * mencatat nama orang yang kebetulan membuat dokumen pasangannya berarti
     * laporan mutu tercatat atas nama orang yang diperiksa.
     *
     * Keduanya hanya diisi SEKALI. Menyunting laporan yang sudah jadi tidak
     * memindahkan tanggung jawabnya ke penyunting berikutnya.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->submitted_at === null) {
            $data['submitted_at'] = now();
            $data['created_by'] = auth()->id();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        return __('QC report for :type :number', [
            'type' => $this->record->jenisDokumen(),
            'number' => $this->record->nomorDokumen(),
        ]);
    }
}
