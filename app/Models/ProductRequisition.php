<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ProductRequisition extends Model
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
        'reviewed_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->document_number ?? 'Beef Request');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            DB::transaction(function () use ($model) {
                $currentYear = date('Y');

                $count = DB::table('product_requisitions')
                    ->whereYear('created_at', $currentYear)
                    ->lockForUpdate()
                    ->count();

                $nextNumber = $count + 1;
                $requestNumber = sprintf("%03s", $nextNumber);

                $model->document_number = "BR#" . substr($currentYear, 2) . $requestNumber;
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
        return $this->hasMany(ProductRequisitionItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function purchaseProduct()
    {
        return $this->hasOne(PurchaseProduct::class);
    }

    /**
     * Terbitkan PO dari request ini.
     *
     * Menolak bila PO-nya sudah pernah terbit. Ini lapis kedua di belakang
     * penjagaan status pada halaman Finance Approval: tanpa keduanya, membuka
     * ulang URL finance-approval untuk dokumen ber-status PO Created
     * menerbitkan PO KEDUA tanpa error apa pun, lengkap dengan dokumen uang
     * muka kedua. Pemanggil tidak perlu menangkap pengecualian ini - halaman
     * yang benar tidak akan pernah sampai ke sini.
     *
     * PO yang sudah di-soft-delete sengaja tidak dihitung, supaya dokumen yang
     * dibatalkan masih bisa diterbitkan ulang.
     *
     * @throws \RuntimeException bila PO untuk request ini sudah ada
     */
    public function generatePurchaseOrder()
    {
        $this->loadMissing(['items', 'supplier']);

        if ($this->purchaseProduct()->exists()) {
            throw new \RuntimeException(
                'PO untuk ' . $this->document_number . ' sudah pernah diterbitkan.'
            );
        }

        DB::transaction(function () {
            $currentYear2Digit = date('y');
            $currentYear4Digit = date('Y');

            $countThisYear = PurchaseProduct::withTrashed()
                ->whereYear('created_at', $currentYear4Digit)
                ->lockForUpdate()
                ->count();
            
            $urut = $countThisYear + 1;
            $poNumber = 'SWM-BPO#' . $currentYear2Digit . str_pad($urut, 3, '0', STR_PAD_LEFT);

            $po = PurchaseProduct::create([
                'po_number' => $poNumber,
                'product_requisition_id' => $this->id,
                'supplier_id' => $this->supplier_id,
                'approved_by' => auth()->id() ?? 1,
                'po_date' => $this->due_date ?? now(),
                'total_amount' => $this->total_amount,
                'note' => $this->note,
            ]);

            foreach ($this->items as $item) {
                PurchaseProductItem::create([
                    'purchase_product_id' => $po->id,
                    'product_id' => $item->product_id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ]);
            }
        });
    }
}
