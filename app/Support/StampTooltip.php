<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Tooltip waktu di kolom nama pembuat/penyetuju (Owner, 10 November 2026): arahkan kursor ke nama, muncul kapan
 * dokumen itu dibuat, tanpa menambah kolom tanggal di tiap tabel.
 *
 * Dipasang SEKALI lewat `TextColumn::configureUsing()` di AppServiceProvider untuk kolom-kolom di `KOLOM`,
 * jadi semua daftar yang memakai nama kolom itu ikut, termasuk modul yang ditambahkan nanti.
 *
 * Untuk PO, penyetuju = saat PO diterbitkan (nomor PO lahir di sana), jadi `created_at` PO adalah waktu disetujui.
 * Baris hasil impor legacy tidak diberi tooltip: `created_at`-nya waktu impor, bukan waktu kejadian sebenarnya.
 */
class StampTooltip
{
    /** nama kolom => kunci terjemahan kalimatnya */
    public const KOLOM = [
        'creator.name' => 'Created on :datetime',
        'user.name' => 'Created on :datetime',
        'createdBy.name' => 'Created on :datetime',
        'approvedBy.name' => 'Approved on :datetime',
        'approver.name' => 'Approved on :datetime',
    ];

    public static function text(string $kalimat, ?Model $record, mixed $nama = null): ?string
    {
        if (! $record || $record->created_at === null) {
            return null;
        }

        if (is_string($nama) && strcasecmp(trim($nama), 'Legacy') === 0) {
            return null;
        }

        return __($kalimat, ['datetime' => $record->created_at->translatedFormat('d M Y H:i')]);
    }
}
