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
 * diabaikan; cukup MEMUAT salah satunya). Nama adalah data yang bisa Owner ubah; id tidak pernah ditulis
 * di kode.
 *
 * Bila tidak ada yang cocok, drylog tetap dicatat jumlahnya tetapi tidak
 * bernilai (0) -- halaman menandainya "belum ada harga" dan Lock tidak
 * diblokir.
 */
class DrylogMaterial
{
    /**
     * Potongan nama yang dikenali, TANPA spasi/tanda hubung dan huruf besar.
     * Sebuah material dianggap drylog bila namanya MEMUAT salah satunya, jadi
     * "DRY LOG - ABSORBENT PAD 3000" tetap dikenali.
     *
     * Ejaannya memang bermacam-macam: data lama menamainya "DRY LOG" (RM0005),
     * produknya bernama dagang Dri-Loc, orang menyebutnya drylock, dan nama
     * umumnya absorbent pad / soaker pad / pad absorber.
     */
    public const NAMES = [
        'DRYLOG', 'DRYLOCK', 'DRILOC', 'DRILOCK',
        'ABSORBENTPAD', 'ABSORBERPAD', 'SOAKERPAD', 'PADABSORBER', 'PADABSORBENT',
    ];

    public static function find(): ?Material
    {
        return Material::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->first(function (Material $material): bool {
                $name = self::normalise($material->name);

                foreach (self::NAMES as $piece) {
                    if (str_contains($name, $piece)) {
                        return true;
                    }
                }

                return false;
            });
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
