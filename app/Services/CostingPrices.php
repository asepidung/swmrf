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

    /**
     * Net untuk gross yang diketik: gross dikurangi potongan terakhir produk itu, atau = gross
     * bila produk belum pernah punya harga (potongan 0).
     */
    public static function netForGross(int $productId, float $gross): float
    {
        $last = self::lastFor($productId);

        if (! $last || $last['gross'] <= 0) {
            return round($gross, 2);
        }

        return round($gross * ($last['net'] / $last['gross']), 2);
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
