<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BeefStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'product_id',
        'warehouse_id',
        'grade_id',
        'weight',
        'qty_pcs',
        'ph_level',
        'pack_date',
        'exp_date',
        'origin',
        'status',
    ];

    /* Relasi tabel grades */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
