<?php

namespace App\Filament\Admin\Resources\QcReportResource\Pages;

use App\Filament\Admin\Resources\QcReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQcReport extends ViewRecord
{
    protected static string $resource = QcReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /*
             * Keputusan Owner, 7 September 2026: klik baris di index
             * membuka halaman ini, dan Print-nya ada DI SINI -- bukan lagi
             * di tabel index. Hanya untuk laporan yang SUDAH diisi: sama
             * seperti sebelumnya, mencetak tugas yang belum dikerjakan
             * menghasilkan kertas berisi tanda strip.
             */
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('qc-reports.print', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->sudahDiisi()),

            Actions\EditAction::make(),
        ];
    }
}
