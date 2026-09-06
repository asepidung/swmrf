<?php

namespace App\Filament\Admin\Resources\QcReportResource\Actions;

use App\Filament\Admin\Resources\QcReportResource;
use App\Models\QcReport;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * Tombol "Laporan QC" untuk dipasang di modul pendampingnya.
 *
 * Permintaan Owner, 7 September 2026: "harusnya ditiap modul pendamping ada
 * 1 button buat liat laporan qc".
 *
 * **Ditulis SEKALI di sini, bukan disalin ke tiap Resource.** Enam modul
 * dengan tombol yang sama berarti enam salinan yang akan berbeda: satu
 * memakai ikon lain, satu lupa menyembunyikan diri saat laporannya belum
 * ada, satu memeriksa izin yang berbeda. Pola itu sudah berulang di proyek
 * ini, dan tombol adalah tempat paling gampang ia terulang.
 */
class LihatLaporanQc
{
    /**
     * Tombolnya, siap ditaruh di `->actions([...])` sebuah tabel.
     *
     * Warnanya menggambarkan KEADAAN: abu kalau laporannya sudah diisi,
     * kuning kalau masih menunggu. Yang membuka daftar dokumen karena itu
     * bisa melihat mana yang belum diperiksa tanpa membuka satu per satu.
     */
    public static function make(): Action
    {
        return Action::make('qc_report')
            ->label(__('QC Report'))
            ->icon('heroicon-o-clipboard-document-check')
            ->color(fn (Model $record): string => static::laporan($record)?->sudahDiisi()
                ? 'gray'
                : 'warning')
            ->tooltip(fn (Model $record): string => static::laporan($record)?->sudahDiisi()
                ? __('QC report has been submitted')
                : __('QC report is still waiting'))
            ->url(function (Model $record): ?string {
                $laporan = static::laporan($record);

                if (! $laporan) {
                    return null;
                }

                // Yang sudah diisi dibuka untuk DIBACA; yang masih menunggu
                // dibuka untuk DIKERJAKAN. Satu tombol, dua maksud, dan
                // maksudnya ditentukan keadaan laporannya -- bukan oleh yang
                // mengklik.
                return $laporan->sudahDiisi()
                    ? QcReportResource::getUrl('view', ['record' => $laporan])
                    : QcReportResource::getUrl('edit', ['record' => $laporan]);
            })
            // Tidak ditampilkan kepada yang tidak boleh membacanya, dan tidak
            // ditampilkan untuk dokumen lama yang memang tidak punya tugas QC.
            ->visible(fn (Model $record): bool => (auth()->user()?->hasPermission('view_qc_reports') ?? false)
                && static::laporan($record) !== null);
    }

    /** Laporan QC terbaru milik sebuah dokumen. */
    private static function laporan(Model $record): ?QcReport
    {
        return QcReport::query()
            ->where('reportable_type', $record::class)
            ->where('reportable_id', $record->getKey())
            ->latest('id')
            ->first();
    }
}
