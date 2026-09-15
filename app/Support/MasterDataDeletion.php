<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;

/**
 * Menerjemahkan penolakan basis data menjadi kalimat yang bisa dibaca orang.
 *
 * Data induk seperti Gudang dan Grade ditunjuk oleh banyak sekali dokumen, dan
 * kunci asingnya RESTRICT -- jadi datanya memang aman: basis data menolak
 * menghapus yang masih dipakai. Yang tidak aman adalah CARA penolakannya
 * sampai ke layar: sebagai galat SQL mentah lengkap dengan nama constraint,
 * yang tidak memberi tahu apa pun kepada orang yang menekan tombolnya.
 *
 * Yang diterjemahkan di sini penolakan dari BASIS DATA, bukan daftar tabel
 * yang ditulis ulang di kode. Daftar semacam itu pasti tertinggal begitu ada
 * tabel baru yang menunjuk ke sana, dan tertinggalnya tidak terlihat sampai
 * seseorang berhasil menghapus sesuatu yang seharusnya tertahan.
 *
 * Basis datalah yang tahu persis siapa menunjuk siapa. Tugas berkas ini hanya
 * menyampaikan jawabannya.
 */
class MasterDataDeletion
{
    /** Kode MySQL untuk "baris ini masih ditunjuk baris lain". */
    private const FOREIGN_KEY_VIOLATION = '23000';

    /**
     * Jalankan penghapusan, dan ubah penolakan kunci asing menjadi
     * pemberitahuan yang bisa dibaca.
     *
     * Mengembalikan `true` kalau penghapusannya berhasil.
     */
    public static function attempt(callable $delete, string $label): bool
    {
        try {
            $delete();

            return true;
        } catch (QueryException $e) {
            if (! self::isStillInUse($e)) {
                throw $e;
            }

            Notification::make()
                ->title(__(':label cannot be deleted', ['label' => $label]))
                ->body(__('It is still used by existing documents. Deactivate it instead — it will stop appearing in new documents while the old ones stay readable.'))
                ->danger()
                ->persistent()
                ->send();

            return false;
        }
    }

    /** Kode SQLite untuk pelanggaran constraint apa pun (termasuk kunci asing). */
    private const SQLITE_CONSTRAINT_VIOLATION = 19;

    private static function isStillInUse(QueryException $e): bool
    {
        // SQLSTATE 23000 dipakai beberapa pelanggaran sekaligus, jadi kodenya
        // saja belum cukup. Nomor galat MySQL 1451 -- "Cannot delete or update
        // a parent row" -- yang menyempitkannya ke kasus ini di produksi.
        //
        // Test memakai SQLite, yang memberi kode BERBEDA (19, bukan 1451)
        // untuk pelanggaran yang SAMA -- jebakan yang sudah dicatat di
        // `.agents/rules/aturan-kerja.md`. Tanpa mengenali keduanya, kelas
        // ini tidak pernah bisa dibuktikan menggigit lewat suite: setiap
        // "masih dipakai" di test akan lolos sebagai QueryException mentah,
        // bukan notifikasi ramah -- padahal di produksi (MySQL) perilakunya
        // sudah benar sejak awal. Mengenali kode SQLite tidak mengubah
        // perilaku produksi sama sekali (MySQL tidak pernah mengembalikan 19).
        return $e->getCode() === self::FOREIGN_KEY_VIOLATION
            && in_array((int) ($e->errorInfo[1] ?? 0), [1451, self::SQLITE_CONSTRAINT_VIOLATION], true);
    }
}
