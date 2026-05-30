<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarcassItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'carcass_id',
        'cattle_weighing_item_id',
        'carcass_1',
        'carcass_2',
        'hides',
        'tail',
        'notes'
    ];

    public function carcass(): BelongsTo
    {
        return $this->belongsTo(Carcass::class);
    }

    public function weighingItem(): BelongsTo
    {
        return $this->belongsTo(CattleWeighingItem::class, 'cattle_weighing_item_id');
    }
}
