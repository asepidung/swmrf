<?php

namespace App\Support;

/**
 * RPT-01: Repeater relasi Filament menghapus setiap baris lama yang tidak ada di state kiriman dan membuat baris untuk setiap
 * kunci baru. `disableItemDeletion()`/`disableItemCreation()` hanya menyembunyikan tombol. Untuk halaman yang jumlah barisnya
 * tidak boleh berubah, kunci yang dikirim harus persis sama dengan baris yang ada (`record-{id}`).
 */
class RepeaterRows
{
    /**
     * @param  iterable<int|string>  $existingIds  id baris yang ada di basis data
     * @param  array<int|string, mixed>|null  $submitted  state Repeater (kuncinya yang dibandingkan)
     */
    public static function unchanged(iterable $existingIds, ?array $submitted): bool
    {
        $ada = collect($existingIds)->map(fn ($id) => 'record-'.$id)->sort()->values()->all();
        $kiriman = collect(array_keys($submitted ?? []))->map(fn ($k) => (string) $k)->sort()->values()->all();

        return $ada === $kiriman;
    }
}
