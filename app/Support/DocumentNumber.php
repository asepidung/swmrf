<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Penomoran dokumen yang tidak berhenti bekerja saat digitnya bertambah.
 *
 * Sepuluh generator di aplikasi ini pernah menulis urutannya sendiri dengan
 * pola yang sama, dan pola itu punya batas yang tidak terlihat:
 *
 *     $sequence = (int) substr($nomorTerakhir, -3) + 1;
 *     $nomor = $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
 *
 * Selama urutannya masih tiga digit, semuanya benar. Dokumen ke-1000 pun
 * masih benar: 999 + 1 menjadi `1000`. Yang gagal adalah dokumen SESUDAHNYA
 * -- `substr(-3)` memotong tepat tiga karakter terakhir, jadi `1000` terbaca
 * `000`, urutan berikutnya dihitung 1, dan nomor yang sudah dipakai sepuluh
 * bulan sebelumnya dicoba lagi. Unique index menolaknya dengan error yang
 * tidak menjelaskan apa-apa, di tengah hari kerja, tanpa peringatan apa pun
 * sebelumnya.
 *
 * Untuk Carcass batas itu bukan teori: satu karkas per sapi yang dipotong,
 * jadi memotong tiga ekor sehari sudah melewati 1.000 dalam setahun.
 *
 * Ada jebakan kedua yang hanya muncul pada generator yang mengurutkan
 * berdasarkan NOMOR (bukan id): `orderBy` membandingkan sebagai teks, dan
 * `...26999` dianggap lebih besar daripada `...261000` karena '9' > '1'.
 * Karena itu di sini diurutkan berdasarkan PANJANG lebih dulu -- `LENGTH()`
 * didukung MySQL maupun SQLite, dan testing berjalan di SQLite.
 *
 * Padding tetap dihormati supaya nomor yang sudah terbit tidak berubah arti;
 * ia hanya batas BAWAH, bukan batas atas.
 */
class DocumentNumber
{
    /**
     * Nomor berikutnya untuk sebuah prefix.
     *
     * `$query` harus sudah disiapkan pemanggil -- termasuk `withTrashed()`
     * bila modelnya soft delete. Nomor dokumen yang sudah terhapus tetap
     * harus dihitung: dokumen boleh hilang, nomornya tidak boleh dipakai
     * ulang.
     *
     * Kuncinya `lockForUpdate()`, tetapi kunci baris hanya berlaku selama
     * transaksi yang membukanya. Pemanggil yang bertanggung jawab membuka
     * transaksi yang MENCAKUP penyimpanan barisnya -- kalau transaksinya
     * ditutup sebelum INSERT, kuncinya lepas justru pada celah yang
     * seharusnya dijaga.
     */
    public static function next(Builder $query, string $column, string $prefix, int $padding): string
    {
        $last = $query
            ->where($column, 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByRaw("LENGTH({$column}) DESC")
            ->orderBy($column, 'desc')
            ->first();

        $sequence = $last
            ? static::sequenceOf($last->{$column}, $prefix) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Urutan yang tersimpan di sebuah nomor, dibaca UTUH.
     *
     * Inilah bedanya dengan `substr($nomor, -3)`: yang diambil adalah seluruh
     * bagian setelah prefix, berapa pun panjangnya. Nomor lama yang tiga
     * digit tetap terbaca benar, dan yang sudah empat digit tidak kehilangan
     * angka teratasnya.
     */
    public static function sequenceOf(string $number, string $prefix): int
    {
        return (int) substr($number, strlen($prefix));
    }
}
