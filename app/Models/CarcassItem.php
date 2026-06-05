<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarcassItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'carcass_items';

    protected $fillable = [
        'carcass_id',
        'cattle_weighing_item_id',
        'carcass_1',
        'carcass_2',
        'hides',
        'tail',
        'notes'
    ];

    protected $appends = [
        'eartag',
    ];

    
    public function carcass(): BelongsTo
    {
        return $this->belongsTo(Carcass::class, 'carcass_id');
    }

    public function weighingItem(): BelongsTo
    {
        return $this->belongsTo(CattleWeighingItem::class, 'cattle_weighing_item_id');
    }

    public function getEartagAttribute()
    {
        return optional($this->weighingItem)->eartag;
    }
}
