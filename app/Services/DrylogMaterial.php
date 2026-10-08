<?php

namespace App\Services;

use App\Models\Material;

/**
 * Material mana yang dimaksud "Drylog / Pad Absorber" (issue #509).
 *
 * Drylog hanya SALAH SATU material di master. Halaman produksi tidak
 * menyediakan pilihan material (Owner menolak dropdown dan penanda di form
 * material), jadi materialnya dikenali dari NAMA di master: yang bernama salah
 * satu dari daftar di bawah (spasi, tanda hubung, dan huruf besar-kecil
 * diabaikan). Nama adalah data yang bisa Owner ubah; id tidak pernah ditulis
 * di kode.
 *
 * Bila tidak ada yang cocok, drylog tetap dicatat jumlahnya tetapi tidak
 * bernilai (0) -- halaman menandainya "belum ada harga" dan Lock tidak
 * diblokir.
 */
class DrylogMaterial
{
    /**
     * Nama yang dikenali, TANPA spasi/tanda hubung dan huruf besar. Ejaannya
     * memang bermacam-macam: data lama menamainya "DRY LOG" (RM0005), produknya
     * bernama dagang Dri-Loc, dan orang menyebutnya drylock atau pad absorber.
     */
    public const NAMES = ['DRYLOG', 'DRYLOCK', 'DRILOC', 'DRILOCK', 'PADABSORBER', 'PADABSORBENT'];

    public static function find(): ?Material
    {
        return Material::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereRaw('UPPER(name) LIKE ?', ['%DRY%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%DRI%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%ABSORB%']);
            })
            ->orderBy('id')
            ->get()
            ->first(fn (Material $material): bool => in_array(self::normalise($material->name), self::NAMES, true));
    }

    /** Harga per satuan pakai (pcs), atau null bila materialnya/harganya tidak ada. */
    public static function unitPrice(): ?float
    {
        $material = self::find();

        return $material ? MaterialUnitPrice::perUsageUnit($material) : null;
    }

    private static function normalise(string $name): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
    }
}
