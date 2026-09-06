<?php

namespace App\Filament\Admin\Resources\QcReportResource\Pages;

use App\Filament\Admin\Resources\QcReportResource;
use Filament\Resources\Pages\ListRecords;

class ListQcReports extends ListRecords
{
    protected static string $resource = QcReportResource::class;

    /**
     * Tidak ada tombol "Buat".
     *
     * Laporan QC selalu MENDAMPINGI sebuah dokumen, jadi ia lahir dari
     * dokumen itu -- dari daftar tugas di Dashboard, atau dari tombol di
     * halaman dokumennya. Tombol buat yang berdiri sendiri di sini akan
     * menghasilkan laporan yang tidak mendampingi apa pun.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
