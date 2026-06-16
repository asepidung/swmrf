<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;

class GoodsReceiptProduct extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->gr_number)) {
                $model->gr_number = self::generateGrNumber();
            }
        });

        static::deleting(function ($gr) {
            $payable = $gr->payable;
            if ($payable && in_array($payable->status, ['partial', 'paid'])) {
                throw new \Exception(__('This record cannot be deleted because its payable status is partial or paid.'));
            }

            // Adjust stock for all items
            foreach ($gr->items as $item) {
                // Delete stock record
                BeefStock::where('barcode', $item->barcode)->delete();

                // Log void/movement
                BeefStockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => 1,
                    'condition' => $item->grade_id,
                    'barcode' => $item->barcode,
                    'transaction_type' => 'VOID_GR_BEEF',
                    'reference_document' => $gr->gr_number,
                    'weight_in' => -$item->weight,
                    'pcs_in' => -$item->qty_pcs,
                    'created_by' => auth()->id(),
                ]);
            }
        });
    }

    public static function generateGrNumber(): string
    {
        $year = date('y');
        $prefix = 'SWM-GRB#' . $year;
        
        $lastRecord = self::withTrashed()
            ->where('gr_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;
        if ($lastRecord) {
            $parts = explode('#', $lastRecord->gr_number);
            if (isset($parts[1]) && strlen($parts[1]) > 2) {
                $lastNum = (int) substr($parts[1], 2);
                $number = $lastNum + 1;
            }
        }

        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->gr_number ?? 'GR Beef');
    }

    public function purchaseProduct()
    {
        return $this->belongsTo(PurchaseProduct::class);
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
        return $this->hasMany(GoodsReceiptProductItem::class);
    }

    public function payable()
    {
        return $this->morphOne(Payable::class, 'payableable');
    }
}
