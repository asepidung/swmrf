<?php

namespace App\Services;

use App\Models\CostingItem;
use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;

/**
 * Harga acuan sebuah produk untuk menilai hasil produksi: grup acuan costing
 * produk -> price list grup itu -> dikurangi trading terms grup (`hpp.md` §9,
 * §10). Satu rumah untuk `CostingCalculator` (nilai jual boning) dan
 * `RepackValuator` (nilai jual hasil repack), supaya keduanya tidak bisa
 * berbeda diam-diam.
 *
 * - Produk punya grup acuan: harga dari price list grup itu, potongan = trading
 *   terms grup itu.
 * - Produk tanpa grup acuan: harga umum (`CustomerGroup::generalPriceReference()`),
 *   potongan 0% -- ditandai `NO_REFERENCE` (keadaan normal, tetapi kelihatan).
 * - Harga tidak ditemukan sama sekali: `gross_price` dan `net_price` null,
 *   ditandai `NO_PRICE`.
 *
 * Net dibulatkan 2 desimal di sini -- ia nilai uang, bukan nilai antara.
 */
class ReferencePrice
{
    /**
     * @return array{reference_group_id: ?int, gross_price: ?float, trading_terms_percent: float, net_price: ?float, flag: ?string}
     */
    public static function forProduct(?Product $product): array
    {
        $referenceGroupId = $product?->costing_customer_group_id;
        $flag = null;
        $termsPercent = 0.0;
        $grossPrice = null;

        if ($referenceGroupId) {
            $group = CustomerGroup::find($referenceGroupId);
            $termsPercent = (float) ($group->trading_terms_percent ?? 0);
            $grossPrice = self::priceFor((int) $referenceGroupId, (int) $product->id);
        } else {
            // Kosong = harga umum, keadaan paling banyak (hpp.md §10). Potongan
            // HANYA berlaku untuk LION/HYPERMART (§9): harga umum selalu 0%
            // terlepas dari apa pun yang tersimpan di grup acuannya.
            $flag = CostingItem::FLAG_NO_REFERENCE;

            $general = CustomerGroup::generalPriceReference();
            $grossPrice = $general && $product ? self::priceFor($general->id, (int) $product->id) : null;
        }

        if ($grossPrice === null) {
            return [
                'reference_group_id' => $referenceGroupId,
                'gross_price' => null,
                'trading_terms_percent' => $termsPercent,
                'net_price' => null,
                'flag' => CostingItem::FLAG_NO_PRICE,
            ];
        }

        return [
            'reference_group_id' => $referenceGroupId,
            'gross_price' => round($grossPrice, 2),
            'trading_terms_percent' => $termsPercent,
            'net_price' => round($grossPrice * (1 - $termsPercent / 100), 2),
            'flag' => $flag,
        ];
    }

    private static function priceFor(int $groupId, int $productId): ?float
    {
        $priceList = PriceList::where('customer_group_id', $groupId)->first();

        if (! $priceList) {
            return null;
        }

        $item = PriceListItem::where('price_list_id', $priceList->id)
            ->where('product_id', $productId)
            ->first();

        return $item ? (float) $item->price : null;
    }
}
