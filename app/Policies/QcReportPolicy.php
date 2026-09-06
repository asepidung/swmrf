<?php

namespace App\Policies;

use App\Models\QcReport;
use App\Models\User;

/**
 * Siapa yang boleh menulis dan membaca laporan QC.
 *
 * Laporan QC TIDAK menghalangi pekerjaan siapa pun -- keputusan Owner,
 * 6 September 2026: "qc gak nahan apapun". Satu-satunya pengecualian ada di
 * Repack, dan penjagaannya tinggal di sana, bukan di sini.
 *
 * Yang dijaga policy ini cuma satu hal: laporan mutu ditulis oleh orang QC,
 * bukan oleh yang mengerjakan prosesnya. Pemeriksaan yang ditandatangani
 * sendiri oleh yang diperiksa bukan pemeriksaan.
 */
class QcReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_qc_reports');
    }

    public function view(User $user, QcReport $report): bool
    {
        return $user->hasPermission('view_qc_reports');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_qc_reports');
    }

    public function update(User $user, QcReport $report): bool
    {
        return $user->hasPermission('edit_qc_reports');
    }

    public function delete(User $user, QcReport $report): bool
    {
        return $user->hasPermission('delete_qc_reports');
    }

    public function restore(User $user, QcReport $report): bool
    {
        return $user->hasPermission('delete_qc_reports');
    }

    public function forceDelete(User $user, QcReport $report): bool
    {
        return $user->hasPermission('delete_qc_reports');
    }
}
