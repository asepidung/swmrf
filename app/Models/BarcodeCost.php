<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Nilai (harga pokok per kg) satu barcode -- HPP tahap 2, issue #522.
 *
 * Satu baris per barcode, di mana pun barcode itu sedang berada. Dibaca lewat
 * `App\Support\BarcodeCosts`, bukan langsung dari modul lain.
 */
class BarcodeCost extends Model
{
    use LogsActivity;

    public const SOURCE_BONING = 'boning';

    public const SOURCE_GOODS_RECEIPT = 'goods_receipt';

    public const SOURCE_REPACK = 'repack';

    public const SOURCE_RELABEL = 'relabel';

    public const SOURCE_SALES_RETURN = 'sales_return';

    public const SOURCE_STOCK_TAKE = 'stock_take';

    public const SOURCE_OPENING = 'opening';

    public const SOURCES = [
        self::SOURCE_BONING, self::SOURCE_GOODS_RECEIPT, self::SOURCE_REPACK, self::SOURCE_RELABEL,
        self::SOURCE_SALES_RETURN, self::SOURCE_STOCK_TAKE, self::SOURCE_OPENING,
    ];

    protected $fillable = [
        'barcode', 'product_id', 'cost_per_kg', 'source_type', 'source_id', 'source_barcode', 'valued_at', 'note',
    ];

    protected $casts = [
        'cost_per_kg' => 'float',
        'valued_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('barcode_costs')
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isValued(): bool
    {
        return $this->cost_per_kg !== null;
    }
}
