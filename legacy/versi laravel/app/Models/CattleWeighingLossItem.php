<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CattleWeighingLossItem extends Model
{
    protected $fillable = [
        'cattle_weighing_loss_id',
        'cattle_weighing_item_id',
        'cattle_category_id',
        'eartag',
        'receive_weight',
        'actual_weight',
        'loss_weight',
        'price_per_kg',
        'loss_cost',
        'note'
    ];

    public function loss(): BelongsTo
    {
        return $this->belongsTo(CattleWeighingLoss::class, 'cattle_weighing_loss_id');
    }

    public function weighingItem(): BelongsTo
    {
        return $this->belongsTo(CattleWeighingItem::class, 'cattle_weighing_item_id');
    }

    public function cattleCategory(): BelongsTo
    {
        return $this->belongsTo(CattleCategory::class, 'cattle_category_id');
    }
}
