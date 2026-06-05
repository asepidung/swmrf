<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PurchaseProduct extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'product_requisition_id',
        'po_number',
        'po_date',
        'supplier_id',
        'approved_by',
        'total_amount',
        'note',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->po_number ?? 'Beef Purchase Order');
    }

    public function productRequisition()
    {
        return $this->belongsTo(ProductRequisition::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseProductItem::class);
    }
}
