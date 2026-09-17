<?php

namespace App\Models;

use App\Support\DocumentNumber;
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
                \App\Services\StockService::adjustStock(
                    $item->material_id,
                    -(float) $item->qty_received,
                    'RETUR',
                    $gr->gr_number,
                    "Pembatalan/Hapus GR " . $gr->gr_number
                );
            }
        });
    }

    /**
     * Satu rumah dengan `GoodsReceiptProduct::generateGrNumber()`.
     *
     * Bentuk lamanya membaca baris TERAKHIR MENURUT ID tanpa mengunci apa
     * pun -- dua orang yang menyimpan GR pada saat yang sama membaca baris
     * terakhir yang sama dan mendapat nomor yang sama; yang kedua ditolak
     * unique index dengan galat SQL mentah di tengah hari kerja.
     */
    public static function generateGrNumber(): string
    {
        return DocumentNumber::next(
            query: static::withTrashed(),
            column: 'gr_number',
            prefix: 'SWM-GRM#'.date('y'),
            padding: 3,
        );
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
