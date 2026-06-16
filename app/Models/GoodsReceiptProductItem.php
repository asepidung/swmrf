<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceiptProductItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function goodsReceiptProduct()
    {
        return $this->belongsTo(GoodsReceiptProduct::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
