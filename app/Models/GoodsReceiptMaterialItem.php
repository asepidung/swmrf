<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceiptMaterialItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function goodsReceiptMaterial()
    {
        return $this->belongsTo(GoodsReceiptMaterial::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
