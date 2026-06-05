<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CattleReceivingItem extends Model
{
    use HasFactory;

    protected $table = 'cattle_receiving_items';

    protected $fillable = [
        'cattle_receiving_id',
        'cattle_class_id',
        'eartag',
        'initial_weight',
        'notes',
    ];

    
    public function receiving(): BelongsTo
    {
        return $this->belongsTo(CattleReceiving::class, 'cattle_receiving_id');
    }

    public function cattleClass(): BelongsTo
    {
        return $this->belongsTo(CattleClass::class, 'cattle_class_id');
    }
}
