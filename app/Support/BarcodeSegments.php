<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Susunan barcode SWM, dan satu-satunya tempat yang boleh tahu posisinya.
 *
 * ```
 * origin(1) + ddmmyy(6) + kode produk(6) + grade(1) + berat(6) + pcs(2) + pH(2) + urutan(4) = 28
 * ```
 *
 * **Berat 6 digit = berat x 100, dua desimal tetap terbawa** (`22,14` kg ->
 * `002214`), jadi batasnya 9.999,99 kg. Sampai #486 berat hanya 4 digit
 * (maks 99,99 kg), dan tujuh tempat menyusunnya sendiri dengan
 * `str_pad(round($weight * 100), 4, ...)`. `str_pad` TIDAK MEMOTONG: berat
 * 5.747,66 kg menjadi `574766`, barcodenya diam-diam 28 karakter, dan
 * seluruh pembaca yang menghitung menurut POSISI bergeser dua karakter --
 * asal label tercatat `-UNIND`, opname menganggap label sendiri sebagai
 * barcode supplier. Keputusan Owner 30 September 2026: SEMUA barcode baru
 * 28 digit, tanpa dua jenis pengecekan (lihat `.agents/barcode-berat.md`).
 *
 * Barcode legacy (19-20 digit) dan 4 barcode uji 26 digit di hosting bukan
 * barcode standar ini; pembaca memperlakukannya sebagai barcode asing.
 * `StockGuardsTest` menjaga agar penyusunan berat tidak kembali ditulis
 * tangan di luar rumah ini.
 */
class BarcodeSegments
{
    /** Panjang seluruh barcode standar. */
    public const PANJANG = 28;

    public const BERAT_DIGIT = 6;

    /** Berat terbesar yang muat di segmen berat, dalam kg. */
    public const BERAT_MAKS = 9999.99;

    /** Pcs terbesar yang muat di segmen pcs (2 digit). */
    public const PCS_MAKS = 99;

    /**
     * Segmen berat: berat x 100, dibulatkan, 6 digit.
     *
     * Menolak berat yang tidak muat. Memotong atau membiarkannya meluap sama
     * buruknya -- keduanya menghasilkan barcode yang menunjuk berat lain.
     */
    public static function weight(float|int|string $kg): string
    {
        $kg = (float) $kg;

        if ($kg < 0 || round($kg, 2) > self::BERAT_MAKS) {
            throw new InvalidArgumentException(
                __('Weight :weight kg does not fit in a barcode (maximum :max kg).', [
                    'weight' => number_format($kg, 2, ',', '.'),
                    'max' => number_format(self::BERAT_MAKS, 2, ',', '.'),
                ])
            );
        }

        return str_pad((string) (int) round($kg * 100), self::BERAT_DIGIT, '0', STR_PAD_LEFT);
    }

    /** Segmen pcs, 2 digit. Menolak yang tidak muat, alasannya sama dengan berat. */
    public static function pcs(int|string $qty): string
    {
        $qty = (int) $qty;

        if ($qty < 0 || $qty > self::PCS_MAKS) {
            throw new InvalidArgumentException(
                __('Quantity :qty pcs does not fit in a barcode (maximum :max pcs).', [
                    'qty' => $qty,
                    'max' => self::PCS_MAKS,
                ])
            );
        }

        return str_pad((string) $qty, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Barcode standar SWM: tepat 28 karakter.
     *
     * Bukan "semuanya angka": segmen kode produk memuat huruf (`MT0010`).
     */
    public static function isStandard(?string $barcode): bool
    {
        return $barcode !== null && strlen($barcode) === self::PANJANG;
    }

    /**
     * Membongkar barcode standar menurut posisinya. `null` untuk yang bukan
     * barcode standar, supaya pemanggil tidak pernah membaca posisi dari
     * barcode yang susunannya lain.
     *
     * @return array{origin: string, date: string, product: string, grade: string, weight: float, pcs: int, ph: float, sequence: string}|null
     */
    public static function parse(?string $barcode): ?array
    {
        if (! self::isStandard($barcode)) {
            return null;
        }

        return [
            'origin' => substr($barcode, 0, 1),
            'date' => substr($barcode, 1, 6),
            'product' => substr($barcode, 7, 6),
            'grade' => substr($barcode, 13, 1),
            'weight' => ((int) substr($barcode, 14, self::BERAT_DIGIT)) / 100,
            'pcs' => (int) substr($barcode, 20, 2),
            'ph' => ((int) substr($barcode, 22, 2)) / 10,
            'sequence' => substr($barcode, 24, 4),
        ];
    }
}
