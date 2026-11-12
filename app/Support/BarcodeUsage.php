<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penanda "barang ini sudah terpakai" untuk label boning/repack dan daftar hasilnya (Owner, 12 November 2026):
 * R = sudah jadi bahan Repack, T = sudah masuk Tally. Satu sumber supaya layar dan kertas label selalu sama.
 */
class BarcodeUsage
{
    /** @return list<string> huruf penanda, urut R lalu T; kosong kalau belum terpakai */
    public static function marks(?string $barcode): array
    {
        if ($barcode === null || $barcode === '') {
            return [];
        }

        $marks = [];

        if (DB::table('repack_materials')->where('barcode', $barcode)->exists()) {
            $marks[] = 'R';
        }

        if (DB::table('tally_items')->where('barcode', $barcode)->exists()) {
            $marks[] = 'T';
        }

        return $marks;
    }
}
