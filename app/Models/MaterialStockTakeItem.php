<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockTakeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_stock_take_id',
        'material_id',
        'system_qty',
        'physical_qty',
        'difference_qty',
        'note',
    ];

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(MaterialStockTake::class, 'material_stock_take_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
