<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeefReceiving extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receiving_number',
        'beef_purchase_order_id',
        'supplier_id',
        'receive_date',
        'sj_number',
        'note',
        'created_by'
    ];

    /* Relasi ke PO Beef */
    public function purchaseOrder()
    {
        return $this->belongsTo(BeefPurchaseOrder::class, 'beef_purchase_order_id');
    }

    /* Relasi ke Supplier */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /* Relasi ke User yang input */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* Relasi ke Detail Item */
    public function items()
    {
        return $this->hasMany(BeefReceivingItem::class, 'beef_receiving_id');
    }
}
