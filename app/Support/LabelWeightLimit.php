<?php

namespace App\Support;

use App\Models\Product;

/**
 * Batas berat wajar satu label (#486 langkah 2).
 *
 * Bukan batas barcode -- itu `BarcodeSegments::BERAT_MAKS`. Ini batas
 * "mungkin salah ketik": di atasnya operator diminta MENGONFIRMASI, tidak
 * ditolak, karena offal, kulit, dan bone memang sah ribuan kilo.
 */
class LabelWeightLimit
{
    /** Kg. Berlaku bila `products.max_label_weight` kosong. */
    public const BAWAAN = 100.0;

    public static function forProduct(Product|int|null $product): float
    {
        if (is_int($product)) {
            $product = Product::find($product);
        }

        $batas = $product?->max_label_weight;

        return $batas === null ? self::BAWAAN : (float) $batas;
    }

    public static function exceeds(Product|int|null $product, float $kg): bool
    {
        return round($kg, 2) > self::forProduct($product);
    }
}
