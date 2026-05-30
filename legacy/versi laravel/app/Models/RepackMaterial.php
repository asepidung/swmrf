<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepackMaterial extends Model
{
    use HasFactory;

    /* Mengizinkan mass assignment untuk semua atribut selain id */
    protected $guarded = ['id'];

    /* Menentukan casting tipe data untuk atribut tertentu */
    protected $casts = [
        'weight' => 'decimal:2',
        'ph_level' => 'decimal:1',
        'qty_pcs' => 'integer',
        'pack_date' => 'date',
        'exp_date' => 'date',
    ];

    /* Mendefinisikan relasi belongsTo ke model Repack */
    public function repack()
    {
        return $this->belongsTo(Repack::class);
    }

    /* Mendefinisikan relasi belongsTo ke model Product */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /* Mendefinisikan relasi belongsTo ke model Grade */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    /* Mendefinisikan relasi belongsTo ke model Warehouse */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
