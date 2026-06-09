<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'transaction_type',
        'reference_document',
        'qty_in',
        'qty_out',
        'balance',
        'note',
        'created_by',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
