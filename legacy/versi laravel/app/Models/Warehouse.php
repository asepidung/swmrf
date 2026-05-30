<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active'
    ];

    // Relasi balik ke stok gudang
    public function beefStocks(): HasMany
    {
        return $this->hasMany(BeefStock::class);
    }
}
