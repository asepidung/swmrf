<?php

namespace App\Helpers;

class BarcodeHelper
{
    /**
     * Get the origin name from a barcode string.
     *
     * @param string $barcode
     * @return string
     */
    public static function getOrigin($barcode)
    {
        // Barcode standar SWM: 28 digit (`BarcodeSegments`, sejak #486).
        if (! \App\Support\BarcodeSegments::isStandard($barcode)) {
            return '-UNIND';
        }

        $prefix = substr($barcode, 0, 1);
        
        $origins = [
            '1' => 'BONING',
            '2' => 'R-STCK',
            '3' => 'R-IMPT',
            '4' => 'R-RTRN',
            '5' => 'R-TRDG',
            '6' => 'RLB-TL',
            '7' => 'TRD-LC',
            '8' => 'TRD-IM',
            '0' => '-FOUND',
        ];

        return $origins[$prefix] ?? '-UNIND';
    }
    /**
     * Awalan barcode LEGACY (aplikasi lama) -> digit origin barcode standar.
     *
     * Satu rumah. Dipakai opname (barang tanpa label standar) dan relabel
     * Tally, supaya keduanya menurunkan origin dengan cara yang sama.
     */
    public const LEGACY_ORIGIN = [
        1 => 1, // Boning -> Boning
        2 => 7, // Trading Lokal -> TRD-LC
        3 => 2, // Repack Stock -> R-STCK
        4 => 6, // Relabel Tally -> RLB-TL
        5 => 3, // Repack Import -> R-IMPT
        6 => 4, // Repack Return -> R-RTRN
        7 => 5, // Repack Trading -> R-TRDG
    ];

    /**
     * Digit origin untuk barcode BARU yang menggantikan barcode ini.
     *
     * Barcode standar: digit pertamanya dibawa apa adanya (origin ASLI tidak
     * diganti -- keputusan Owner 5 Oktober 2026, issue #497). Barcode legacy:
     * awalannya dipetakan lewat `LEGACY_ORIGIN`. Selain itu `0` (tanpa asal
     * yang diketahui).
     */
    public static function originDigitFor(?string $barcode): string
    {
        if (\App\Support\BarcodeSegments::isStandard($barcode)) {
            return substr($barcode, 0, 1);
        }

        return self::LEGACY_ORIGIN[substr((string) $barcode, 0, 1)] ?? 0;
    }
}
