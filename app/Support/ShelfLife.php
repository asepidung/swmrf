<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Umur simpan sebuah karton: satu rumah untuk satu aturan.
 *
 * **Keputusan Owner, 5 September 2026: "chill 3 bulan, frozen, a, b dan r
 * setahun".** Hanya CHILL yang berumur pendek.
 *
 * Sebelum ini aturannya ditulis EMPAT KALI -- di `LabelingBoning`,
 * `LabelingGoodsReceiptProduct`, `InputHasilRepack`, dan sekali lagi langsung
 * di dalam aksi `ScanStockTake` -- dan keempat salinan itu TIDAK SAMA:
 *
 *   Boning       in_array($gradeId, [1, 3])   grade A -> 3 bulan
 *   GR Product   in_array($gradeId, [1, 3])   grade A -> 3 bulan
 *   Repack       $gradeId === 1               grade A -> 1 tahun
 *   Stock Take   $gradeId === 1               grade A -> 1 tahun
 *
 * Sales Return memanggil salinan milik Repack, menyeberangi batas modul.
 *
 * Jadi satu karton grade A mendapat tanggal kedaluwarsa yang berbeda semata
 * karena pintu mana yang dilewatinya. Tidak ada yang gagal dan tidak ada
 * error; yang berbeda hanya tanggal yang tercetak di stiker, dan itu baru
 * ketahuan setelah barangnya ada di tangan pelanggan.
 *
 * Menurut keputusan di atas, dua salinan yang MEMASUKKAN grade 3 yang keliru.
 *
 * Aturan yang disalin adalah aturan yang cepat atau lambat akan berbeda.
 */
class ShelfLife
{
    /**
     * Grade yang umur simpannya pendek: hanya CHILL.
     *
     * FROZEN, A, B, dan R semuanya setahun.
     */
    private const UMUR_PENDEK = [1];

    private const BULAN_PENDEK = 3;

    /**
     * Grade yang umurnya pendek, untuk penyaring di tempat lain.
     *
     * Laporan "Aging > 60 Days" memakainya juga: umur simpan hanya jadi soal
     * untuk barang berpendingin, dan barang beku tidak perlu ditagih karena
     * lama di gudang. Sebelumnya laporan itu menyaring dengan
     * `where('name', 'like', '%CHILL%')` -- mencocokkan NAMA grade. Sekali
     * saja nama gradenya diubah dari layar Master Data, laporannya kosong
     * tanpa satu pun error, dan tidak ada yang tahu bedanya antara "tidak ada
     * barang tua" dan "penyaringnya tidak cocok lagi".
     *
     * @return array<int, int>
     */
    public static function shortLivedGradeIds(): array
    {
        return self::UMUR_PENDEK;
    }

    /** Tanggal kedaluwarsa untuk satu tanggal kemas dan satu grade. */
    public static function expiryFor(mixed $packDate, mixed $gradeId): ?Carbon
    {
        if (blank($packDate) || blank($gradeId)) {
            return null;
        }

        $tanggal = Carbon::parse($packDate);

        return in_array((int) $gradeId, self::UMUR_PENDEK, true)
            ? $tanggal->copy()->addMonths(self::BULAN_PENDEK)
            : $tanggal->copy()->addYear();
    }

    /** Bentuk siap simpan (Y-m-d), atau `null` kalau bahannya belum lengkap. */
    public static function expiryDateFor(mixed $packDate, mixed $gradeId): ?string
    {
        return self::expiryFor($packDate, $gradeId)?->format('Y-m-d');
    }

    /** Mengisi `exp_date` sebuah form. Dipakai `afterStateUpdated`. */
    public static function fill(mixed $packDate, mixed $gradeId, callable $set): void
    {
        $tanggal = self::expiryDateFor($packDate, $gradeId);

        if ($tanggal === null) {
            return;
        }

        $set('exp_date', $tanggal);
    }
}
