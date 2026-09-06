<?php

namespace App\Observers;

use App\Models\QcReport;
use App\Support\TaskNotifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Membukakan tugas QC begitu dokumen pasangannya lahir.
 *
 * Dipasang untuk SETIAP kelas di `QcReport::DOKUMEN`, dalam satu perulangan
 * di `AppServiceProvider`. Menambah titik QC karena itu tetap berarti
 * menambah SATU baris di daftar itu -- pengamatnya, tugasnya, dan
 * notifikasinya ikut dengan sendirinya.
 */
class QcCompanionObserver
{
    public function created(Model $dokumen): void
    {
        // Dokumen yang entah bagaimana sudah punya laporan tidak dibukakan
        // tugas kedua. Bisa terjadi saat data disalin atau dipulihkan.
        if ($dokumen->qcReports()->exists()) {
            return;
        }

        $laporan = QcReport::create([
            'reportable_type' => $dokumen::class,
            'reportable_id' => $dokumen->getKey(),
        ]);

        /*
         * Notifikasinya dikirim ke perangkat, bukan cuma ditunggu di layar.
         *
         * Permintaan Owner, 7 September 2026: "ia yang gw maksud notifikasi
         * itu notif ke hp". Daftar tugas di Dashboard tetap ada dan tetap
         * bertahan sampai dikerjakan; push yang membuat QC tahu ADA
         * pekerjaan tanpa harus membuka halamannya lebih dulu.
         *
         * `TaskNotifier` sudah membungkus kegagalannya sendiri: layanan push
         * yang bermasalah tidak boleh menggagalkan penyimpanan dokumen yang
         * memicunya.
         */
        TaskNotifier::notifyPermissionHolders(
            permissions: 'create_qc_reports',
            title: __('QC report needed'),
            body: __(':type :number is waiting for its QC report.', [
                'type' => $laporan->jenisDokumen(),
                'number' => $laporan->nomorDokumen(),
            ]),
            url: \App\Filament\Admin\Resources\QcReportResource::getUrl('edit', ['record' => $laporan]),
            tag: 'qc-report-'.$laporan->id,
            exceptUserId: auth()->id(),
        );
    }
}
