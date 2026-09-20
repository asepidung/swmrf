<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot biaya beli per kelas sapi untuk sebuah `Costing` (`hpp.md` §6)
 * -- biaya beli TIDAK PERNAH satu harga dikali berat total, selalu
 * dijumlah per kelas.
 */
class CostingCattle extends Model
{
    protected $table = 'costing_cattle';

    protected $fillable = [
        'costing_id',
        'cattle_class_id',
        'head_count',
        'received_weight',
        'price_per_kg',
        'amount',
    ];

    protected $casts = [
        'head_count' => 'integer',
        'received_weight' => 'float',
        'price_per_kg' => 'float',
        'amount' => 'float',
    ];

    public function costing(): BelongsTo
    {
        return $this->belongsTo(Costing::class);
    }

    public function cattleClass(): BelongsTo
    {
        return $this->belongsTo(CattleClass::class);
    }
}
