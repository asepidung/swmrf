<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PurchaseMaterial extends Model
{

    /**
     * Penjagaan penghapusan.
     *
     * Ditulis di model, bukan di tombolnya, supaya berlaku untuk semua jalur
     * penghapusan -- tombol di layar, aksi massal, maupun tinker. Idiomnya
     * mengikuti penjagaan yang sudah ada di Goods Receipt.
     */
    protected static function booted(): void
    {
        static::deleting(function ($po) {
            // Uang muka sudah terlanjur keluar ke pemasok. Menghapus PO-nya
            // membuat pembayaran itu menunjuk ke dokumen yang tidak ada lagi,
            // dan uangnya hilang dari jejak tanpa satu pun error.
            if ($po->supplierPayments()->exists()) {
                throw new \Exception(__('This purchase order cannot be deleted because a supplier payment is already recorded against it.'));
            }
        });
    }
    use HasFactory, SoftDeletes, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->po_number ?? 'PO Material');
    }

    public function materialRequisition()
    {
        return $this->belongsTo(MaterialRequisition::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseMaterialItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceiptMaterial::class);
    }

    public function supplierPayments()
    {
        return $this->morphMany(SupplierPayment::class, 'source');
    }
}
