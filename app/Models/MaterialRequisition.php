<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MaterialRequisition extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'document_number',
        'user_id',
        'supplier_id',
        'due_date',
        'note',
        'reject_note',
        'terms_of_payment',
        'tax_type',
        'tax_amount',
        'total_amount',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            DB::transaction(function () use ($model) {
                $currentYear = date('Y');

                $count = DB::table('material_requisitions')
                    ->whereYear('created_at', $currentYear)
                    ->lockForUpdate()
                    ->count();

                $nextNumber = $count + 1;
                $requestNumber = sprintf("%03s", $nextNumber);

                $model->document_number = "MR#" . substr($currentYear, 2) . $requestNumber;
            });
        });
    }

    public function updateTotalAmount()
    {
        $total = $this->items()->sum('subtotal');
        $tax = 0;
        if ($this->supplier && $this->supplier->has_tax) {
            $tax = $total * 0.11;
        }
        $this->update([
            'total_amount' => $total + $tax,
            'tax_amount' => $tax
        ]);
    }

    public function items()
    {
        return $this->hasMany(MaterialRequisitionItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
