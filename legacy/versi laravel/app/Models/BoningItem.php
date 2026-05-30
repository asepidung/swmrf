<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoningItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'boning_id',
        'product_id',
        'warehouse_id',
        'grade_id',
        'condition',
        'weight',
        'qty_pcs',
        'ph_level',
        'pack_date',
        'exp_date',
        'barcode',
        'created_by'
    ];

    /*
    |--------------------------------------------------------------------------
    | Tipe Data Casting
    |--------------------------------------------------------------------------
    | Memastikan tipe data yang ditarik dari database sesuai dengan formatnya.
    */
    protected $casts = [
        'weight' => 'float',
        'qty_pcs' => 'integer',
        'ph_level' => 'float',
        'pack_date' => 'date',
        'exp_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relasi Tabel
    |--------------------------------------------------------------------------
    */
    public function boning(): BelongsTo
    {
        return $this->belongsTo(Boning::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
