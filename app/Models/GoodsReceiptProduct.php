<?php

namespace App\Models;

use App\Support\DocumentNumber;

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
        
        // Lewat `DocumentNumber`, bukan penomoran sendiri.
        //
        // Bentuk lamanya membaca baris TERAKHIR MENURUT ID lalu memungut
        // nomornya, dan menerima nomornya hanya kalau panjangnya lebih dari
        // dua karakter -- panjang dipakai lagi sebagai penanda sah.
        //
        // Yang lebih penting: ia tidak MENGUNCI apa pun. Dua orang yang
        // menyimpan GR pada saat yang sama membaca baris terakhir yang sama
        // dan mendapat nomor yang sama; yang kedua ditolak unique index
        // dengan galat SQL mentah di tengah hari kerja.
        return DocumentNumber::next(
            query: static::withTrashed(),
            column: 'gr_number',
            prefix: $prefix,
            padding: 3,
        );
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
