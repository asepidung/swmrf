<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Satu baris klaim (produk + berat yang menurut pelanggan diretur) di
 * dalam sebuah `SalesReturnPlan`.
 *
 * `claimed_weight` boleh dinego SETELAH plan `Submitted` (lihat
 * `SalesReturnPlan::booted()`) -- `LogsActivity` di sini yang menjaga
 * angka lamanya tidak hilang begitu diubah.
 */
class SalesReturnPlanItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'plan_id',
        'product_id',
        'claimed_weight',
        'claimed_qty_pcs',
        'note',
    ];

    protected $casts = [
        'claimed_weight' => 'float',
        'claimed_qty_pcs' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SalesReturnPlan::class, 'plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Klaim tidak boleh melebihi yang benar-benar terkirim di DO yang
     * dipilih plan-nya. Plan tanpa DO (unidentified) tidak punya batas ini.
     *
     * @throws \Exception
     */
    private static function assertWithinDeliveredWeight(self $item): void
    {
        $plan = $item->plan()->first();

        if (! $plan || ! $plan->delivery_order_id) {
            return;
        }

        $deliveryOrder = $plan->deliveryOrder()->first();
        $delivered = $deliveryOrder?->deliveredWeightFor($item->product_id) ?? 0.0;

        if ($delivered <= 0) {
            throw new \Exception(__('This product was not part of the chosen delivery order.'));
        }

        if ($item->claimed_weight > $delivered) {
            throw new \Exception(__('The claimed weight cannot exceed what was actually delivered on this delivery order.'));
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            // Query lewat plan() bukan properti $item->plan -- relasi
            // belongsTo yang sudah diakses ikut TERCACHE di instance ini,
            // dan tidak pernah menyegarkan diri walau baris plan-nya
            // berubah lewat objek lain (mis. plan->submit() lalu
            // save()). Diverifikasi lewat probe manual saat menulis ini:
            // $item->plan tetap membaca status LAMA padahal basis data
            // sudah berubah.
            $plan = $item->plan()->first();

            if ($plan && $plan->status !== SalesReturnPlan::STATUS_DRAFT) {
                throw new \Exception(__('Items can only be added to a sales return plan while it is still a draft.'));
            }

            self::assertWithinDeliveredWeight($item);
        });

        static::updating(function (self $item) {
            $plan = $item->plan()->first();

            if ($plan && $plan->status === SalesReturnPlan::STATUS_RECEIVED) {
                throw new \Exception(__('This plan has already been received and its items can no longer be changed.'));
            }

            self::assertWithinDeliveredWeight($item);
        });

        static::deleting(function (self $item) {
            $plan = $item->plan()->first();

            if ($plan && $plan->status !== SalesReturnPlan::STATUS_DRAFT) {
                throw new \Exception(__('Items can only be removed from a sales return plan while it is still a draft.'));
            }
        });
    }
}
