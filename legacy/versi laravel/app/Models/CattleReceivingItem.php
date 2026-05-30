<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CattleReceivingItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cattle_receiving_id',
        'cattle_category_id',
        'eartag',
        'initial_weight',
        'notes'
    ];

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(CattleReceiving::class, 'cattle_receiving_id');
    }

    // UBAH DARI category() KE cattleCategory()
    public function cattleCategory(): BelongsTo
    {
        return $this->belongsTo(CattleCategory::class, 'cattle_category_id');
    }
}
