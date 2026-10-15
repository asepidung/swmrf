<?php

namespace App\Services;

use App\Models\Costing;
use App\Models\CostingItem;
use App\Models\CostingPriceDefault;
use App\Models\Product;

/**
 * Sumber harga costing (issue #541): harga sebuah produk disalin dari COSTING TERKUNCI TERAKHIR
 * yang memuat produk itu (gross + net per produk); bila belum pernah ada, dari
 * `costing_price_defaults` (diisi sekali dari sheet costing terbaru); bila tidak ada juga,
 * produk itu NO_PRICE dan harganya diketik di halaman costing (Draft).
 *
 * Potongan adalah milik PRODUK, bukan grup pelanggan: 1 - net/gross.
 */
class CostingPrices
{
    /**
     * @return array{gross: float, net: float}|null
     */
    public static function lastFor(int $productId): ?array
    {
        $item = CostingItem::query()
            ->join('costings', 'costings.id', '=', 'costing_items.costing_id')
            ->where('costing_items.product_id', $productId)
            ->where('costings.status', Costing::STATUS_LOCKED)
            ->whereNull('costings.deleted_at')
            ->where('costing_items.net_price', '>', 0)
            ->orderByDesc('costings.locked_at')
            ->orderByDesc('costings.id')
            ->first(['costing_items.gross_price', 'costing_items.net_price']);

        if ($item) {
            return ['gross' => (float) $item->gross_price, 'net' => (float) $item->net_price];
        }

        $default = CostingPriceDefault::where('product_id', $productId)->where('net_price', '>', 0)->first();

        return $default ? ['gross' => (float) $default->gross_price, 'net' => (float) $default->net_price] : null;
    }

    /** Net dibulatkan ke atas ke rupiah penuh (permintaan akunting, 15 Oktober 2026): 203.437,5 -> 203.438. */
    public static function roundNet(float $net): float
    {
        return ceil(round($net, 6));
    }

    /**
     * Net untuk gross yang diketik: gross dikurangi potongan terakhir produk itu, atau = gross
     * bila produk belum pernah punya harga (potongan 0).
     */
    public static function netForGross(int $productId, float $gross): float
    {
        $last = self::lastFor($productId);

        if (! $last || $last['gross'] <= 0) {
            return self::roundNet($gross);
        }

        return self::roundNet($gross * ($last['net'] / $last['gross']));
    }

    /** "203.437,5" (titik ribuan, koma desimal, dari mask di form) -> 203437.5. Angka polos tetap angka. */
    public static function parseMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return max(0.0, (float) $value);
        }

        $text = trim((string) $value);

        if (str_contains($text, ',')) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        } elseif (substr_count($text, '.') > 1 || preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
            $text = str_replace('.', '', $text);
        }

        return is_numeric($text) ? max(0.0, (float) $text) : 0.0;
    }

    /** 203437.5 -> "203.437,5"; 217000 -> "217.000" (maksimal dua desimal, nol di belakang dibuang). */
    public static function formatMoney(mixed $value): string
    {
        $text = number_format((float) $value, 2, ',', '.');

        return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
    }

    /** Potongan % = 1 - net/gross (0 bila gross kosong). */
    public static function discountPercent(float $gross, float $net): float
    {
        return $gross > 0 ? round((1 - $net / $gross) * 100, 2) : 0.0;
    }

    /** Hanya produk yang TIDAK bertanda `excluded_from_overhead` yang dibebani overhead (kg dasar F88 Excel). */
    public static function isMeat(?Product $product): bool
    {
        return ! (bool) $product?->excluded_from_overhead;
    }

    /** Perbarui harga patokan dari costing yang baru dikunci. */
    public static function rememberFrom(Costing $costing): void
    {
        foreach ($costing->items()->where('net_price', '>', 0)->get() as $item) {
            CostingPriceDefault::updateOrCreate(
                ['product_id' => $item->product_id],
                ['gross_price' => $item->gross_price, 'net_price' => $item->net_price, 'source' => 'costing'],
            );
        }
    }
}
