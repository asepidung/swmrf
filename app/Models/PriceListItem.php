<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PriceListItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'price_list_id',
        'product_id',
        'price',
        'note',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['priceList.id', 'product.name', 'price', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Urutan cetak dan ekspor (Owner, 28 Oktober 2026): kategori produk dulu (menurut id kategori), lalu nama
     * produk menurut abjad. `$byGroup` menaruh nama grup customer di depan (ekspor lintas grup).
     *
     * @param  \Illuminate\Support\Collection<int, PriceListItem>  $items
     * @return \Illuminate\Support\Collection<int, PriceListItem>
     */
    public static function sortedForPrint(\Illuminate\Support\Collection $items, bool $byGroup = false): \Illuminate\Support\Collection
    {
        return $items->sort(function (PriceListItem $a, PriceListItem $b) use ($byGroup): int {
            if ($byGroup) {
                $group = strnatcasecmp($a->priceList?->customerGroup?->name ?? '', $b->priceList?->customerGroup?->name ?? '');

                if ($group !== 0) {
                    return $group;
                }
            }

            $category = ($a->product?->category_id ?? PHP_INT_MAX) <=> ($b->product?->category_id ?? PHP_INT_MAX);

            return $category !== 0 ? $category : strnatcasecmp($a->product?->name ?? '', $b->product?->name ?? '');
        })->values();
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
