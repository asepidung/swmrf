<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penanda "barang ini sudah terpakai" untuk label boning/repack dan daftar hasilnya (Owner, 12 November 2026):
 * R = sudah jadi bahan Repack, T = sudah masuk Tally. Satu sumber supaya layar dan kertas label selalu sama.
 */
class BarcodeUsage
{
    /** Dua tombol penanda nonaktif (R dan T) untuk kolom aksi tabel; muncul hanya bila barcode barisnya terpakai. */
    public static function statusActions(): array
    {
        $buat = fn (string $nama, string $huruf, string $warna, string $tooltip) => \Filament\Tables\Actions\Action::make($nama)
            ->label($huruf)
            ->color($warna)
            ->tooltip(__($tooltip))
            ->visible(fn ($record) => in_array($huruf, static::marks($record->barcode), true))
            ->disabled();

        return [
            $buat('repack_status', 'R', 'warning', 'This item is already a repack input'),
            $buat('tally_status', 'T', 'info', 'This item is already on a tally'),
        ];
    }

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
