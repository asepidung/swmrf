<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class GoodsReceiptMaterial extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($gr) {
            $payable = $gr->payable;
            if ($payable && in_array($payable->status, ['partial', 'paid'])) {
                throw new \Exception(__('This record cannot be deleted because its payable status is partial or paid.'));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->gr_number ?? 'GR Material');
    }

    public function purchaseMaterial()
    {
        return $this->belongsTo(PurchaseMaterial::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptMaterialItem::class);
    }

    public function payable()
    {
        return $this->morphOne(Payable::class, 'payableable');
    }
}
