<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu temuan di dalam sebuah laporan QC.
 *
 * Keputusan Owner, 6 September 2026: tiga kolom, tetapi tidak semuanya wajib.
 * Proses yang tidak bermasalah TIDAK menambah baris di sini sama sekali --
 * yang wajib cuma catatan umum di laporannya.
 *
 * Kalau barisnya ada, `description` wajib: temuan tanpa keterangan bukan
 * temuan. `affected_count` dan `action_taken` boleh menyusul, karena kadang
 * keduanya memang belum diketahui saat menulis.
 */
class QcFinding extends Model
{
    protected $fillable = [
        'qc_report_id',
        'description',
        'affected_count',
        'action_taken',
    ];

    protected $casts = [
        'affected_count' => 'integer',
    ];

    public function qcReport(): BelongsTo
    {
        return $this->belongsTo(QcReport::class);
    }
}
