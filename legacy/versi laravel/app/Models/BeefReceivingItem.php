<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeefReceivingItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'beef_receiving_id',
        'beef_item_id', // Ini master barang Beef-nya
        'qty_received',
        'price',
        'subtotal'
    ];

    /* Relasi balik ke Induk GR */
    public function receiving()
    {
        return $this->belongsTo(BeefReceiving::class, 'beef_receiving_id');
    }

    /* Relasi ke master barang (Tabel: beef_items) */
    public function item()
    {
        return $this->belongsTo(Product::class, 'beef_item_id');
    }
}
