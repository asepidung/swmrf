<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'so_number',
        'customer_id',
        'delivery_date',
        'po_number',
        'shipping_address',
        'status',
        'note',
        'created_by',
    ];

    /**
     * Mengatur fungsi event model, termasuk auto-generate nomor SO.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->so_number)) {
                $year2Digit = date('y');
                $month2Digit = date('m');
                $currentYearFull = date('Y');

                // Menggunakan withTrashed() agar data yang terhapus tetap masuk dalam perhitungan nomor urut
                $lastOrder = self::withTrashed()
                    ->whereYear('created_at', $currentYearFull)
                    ->orderBy('id', 'desc')
                    ->first();

                $nextSequence = 1;

                if ($lastOrder && !empty($lastOrder->so_number)) {
                    $lastSequence = (int) substr($lastOrder->so_number, -4);
                    $nextSequence = $lastSequence + 1;
                }

                $model->so_number = 'SO-' . $year2Digit . $month2Digit . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Relasi ke entitas pelanggan.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relasi ke entitas detail item pesanan.
     */
    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /**
     * Relasi ke entitas pengguna untuk mencatat pembuat data.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
