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
     * Keputusan Owner, 7 September 2026: modul pendamping (Carcass, Boning,
     * dst) HANYA untuk MELIHAT laporan yang sudah jadi -- pengisian/
     * penyuntingannya harus lewat menu QC > QC Report, bukan dari sini.
     * Konsekuensinya dua: tombol ini SELALU membuka halaman VIEW (tidak
     * pernah lagi bercabang ke `edit`), dan tombol ini sama sekali TIDAK
     * TAMPIL selama laporannya belum diisi (`submitted_at` masih kosong) --
     * bukan cuma disembunyikan warnanya jadi kuning seperti sebelumnya.
     * Draft yang baru lahir otomatis (lihat `QcCompanionObserver`) memang
     * selalu `submitted_at = null`, jadi syarat ini otomatis menahan tombol
     * sampai QC benar-benar menyelesaikannya lewat menunya sendiri.
     */
    public static function make(): Action
    {
        return Action::make('qc_report')
            ->label(__('QC Report'))
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->tooltip(__('QC report has been submitted'))
            ->url(fn (Model $record): ?string => ($laporan = static::laporan($record))
                ? QcReportResource::getUrl('view', ['record' => $laporan])
                : null)
            // Tidak ditampilkan kepada yang tidak boleh membacanya, tidak
            // ditampilkan untuk dokumen lama yang tidak punya tugas QC, dan
            // TIDAK ditampilkan selama laporannya masih draft kosong.
            ->visible(fn (Model $record): bool => (auth()->user()?->hasPermission('view_qc_reports') ?? false)
                && (static::laporan($record)?->sudahDiisi() ?? false));
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
