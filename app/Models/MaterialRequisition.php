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
            ->dontSubmitEmptyLogs()
            ->useLogName($this->document_number ?? 'Material Requisition');
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

    public function purchaseMaterial()
    {
        return $this->hasOne(PurchaseMaterial::class);
    }

    public function generatePurchaseOrder()
    {
        $this->loadMissing(['items', 'supplier']);

        DB::transaction(function () {
            $currentYear2Digit = date('y');
            $currentYear4Digit = date('Y');

            $countThisYear = PurchaseMaterial::whereYear('created_at', $currentYear4Digit)
                ->lockForUpdate()
                ->count();
            
            $urut = $countThisYear + 1;
            $poNumber = 'SWM-MPO#' . $currentYear2Digit . str_pad($urut, 3, '0', STR_PAD_LEFT);

            $po = PurchaseMaterial::create([
                'po_number' => $poNumber,
                'material_requisition_id' => $this->id,
                'supplier_id' => $this->supplier_id,
                'approved_by' => auth()->id() ?? 1, // fallback to 1 if console
                'po_date' => $this->due_date ?? now(),
                'total_amount' => $this->total_amount,
                'note' => $this->note,
            ]);

            foreach ($this->items as $item) {
                PurchaseMaterialItem::create([
                    'purchase_material_id' => $po->id,
                    'material_id' => $item->material_id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ]);
            }
        });
    }
}
