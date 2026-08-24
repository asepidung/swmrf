<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

use Carbon\Carbon;

class Payable extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $guarded = ['id'];

    /**
     * Potongkan uang muka yang sudah dibayar ke utang yang baru terbit.
     *
     * DP dibayar saat ORDER, sementara utang lahir saat barang DITERIMA. Tanpa
     * langkah ini, utang akan tercatat sebesar nilai penuh meski DP sudah
     * dibayar — kesalahan yang tidak menimbulkan error apa pun dan baru
     * ketahuan saat supplier menagih.
     *
     * Uang muka ditelusuri lewat rantai dokumen: Goods Receipt -> PO -> Request.
     */
    protected static function applyAdvancesFrom(?Model $source, self $payable): void
    {
        if ($source === null) {
            return;
        }

        $outstanding = (float) $payable->amount - (float) $payable->paid_amount;

        if ($outstanding <= 0) {
            return;
        }

        foreach (SupplierPayment::unallocatedFor($source) as $advance) {
            if ($outstanding <= 0) {
                break;
            }

            $applied = $advance->allocateTo($payable, $outstanding);

            if ($applied <= 0) {
                continue;
            }

            $payable->paid_amount = (float) $payable->paid_amount + $applied;
            $outstanding -= $applied;
        }
    }

    /** Dokumen Request yang menurunkan sebuah Goods Receipt, bila ada. */
    protected static function requisitionBehind(Model $gr): ?Model
    {
        $po = $gr->purchaseMaterial ?? $gr->purchaseProduct ?? null;

        if ($po === null) {
            return null;
        }

        return $po->materialRequisition ?? $po->productRequisition ?? null;
    }

    public static function generateForGoodsReceipt(GoodsReceiptMaterial $gr): self
    {
        $gr->loadMissing(['items', 'supplier']);
        
        $subtotalSum = $gr->items->sum('subtotal');
        $tax = $gr->supplier && $gr->supplier->is_tax_11 ? ($subtotalSum * 0.11) : 0;
        $amount = $subtotalSum + $tax;
        
        $topDays = $gr->supplier->top_days ?? 0;
        $dueDate = Carbon::parse($gr->receive_date)->addDays($topDays);
        
        $payable = $gr->payable ?: new self();
        $payable->payableable_type = get_class($gr);
        $payable->payableable_id = $gr->id;
        $payable->supplier_id = $gr->supplier_id;
        $payable->document_number = $gr->gr_number;
        $payable->amount = $amount;
        $payable->balance = $amount - $payable->paid_amount;
        $payable->due_date = $dueDate;

        // Uang muka dipotongkan sebelum status dihitung, supaya dokumen yang
        // sudah lunas di muka tidak sempat tercatat sebagai 'unpaid'.
        static::applyAdvancesFrom(static::requisitionBehind($gr), $payable);

        $payable->balance = $payable->amount - $payable->paid_amount;

        if ($payable->paid_amount <= 0) {
            $payable->status = 'unpaid';
        } elseif ($payable->paid_amount >= $payable->amount) {
            $payable->status = 'paid';
        } else {
            $payable->status = 'partial';
        }
        
        $payable->created_by = auth()->id() ?? $gr->created_by;
        $payable->save();
        
        return $payable;
    }

    public static function generateForGoodsReceiptProduct(GoodsReceiptProduct $gr): self
    {
        $gr->loadMissing(['items', 'supplier']);
        
        $subtotalSum = $gr->items->sum('subtotal');
        $tax = $gr->supplier && $gr->supplier->is_tax_11 ? ($subtotalSum * 0.11) : 0;
        $amount = $subtotalSum + $tax;
        
        $topDays = $gr->supplier->top_days ?? 0;
        $dueDate = Carbon::parse($gr->receive_date)->addDays($topDays);
        
        $payable = $gr->payable ?: new self();
        $payable->payableable_type = get_class($gr);
        $payable->payableable_id = $gr->id;
        $payable->supplier_id = $gr->supplier_id;
        $payable->document_number = $gr->gr_number;
        $payable->amount = $amount;
        $payable->balance = $amount - $payable->paid_amount;
        $payable->due_date = $dueDate;

        // Uang muka dipotongkan sebelum status dihitung, supaya dokumen yang
        // sudah lunas di muka tidak sempat tercatat sebagai 'unpaid'.
        static::applyAdvancesFrom(static::requisitionBehind($gr), $payable);

        $payable->balance = $payable->amount - $payable->paid_amount;

        if ($payable->paid_amount <= 0) {
            $payable->status = 'unpaid';
        } elseif ($payable->paid_amount >= $payable->amount) {
            $payable->status = 'paid';
        } else {
            $payable->status = 'partial';
        }
        
        $payable->created_by = auth()->id() ?? $gr->created_by;
        $payable->save();
        
        return $payable;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->document_number ?? 'Payable');
    }

    public function payableable()
    {
        return $this->morphTo();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
