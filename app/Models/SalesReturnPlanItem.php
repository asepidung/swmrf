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

    /**
     * Berat produk yang terkirim: di surat jalan yang dipilih, atau (tanpa surat jalan) total yang diterima customer itu
     * dalam 3 bulan sampai tanggal acuan (Owner, 24 Oktober 2026). Dipakai tampilan form dan batas kerasnya.
     */
    public static function deliveredWeight(?int $deliveryOrderId, ?int $customerId, int $productId, ?\Carbon\CarbonInterface $until = null): float
    {
        if ($deliveryOrderId) {
            return (float) (DeliveryOrder::find($deliveryOrderId)?->deliveredWeightFor($productId) ?? 0);
        }

        if (! $customerId) {
            return 0.0;
        }

        $until = \Carbon\Carbon::parse($until ?? now());
        $from = $until->copy()->subMonths(SalesReturnPlan::MAX_DELIVERY_AGE_MONTHS);

        return round((float) DeliveryOrderItem::query()
            ->where('product_id', $productId)
            ->whereIn('delivery_order_id', DeliveryOrder::query()
                ->where('customer_id', $customerId)
                ->whereDate('delivery_date', '>=', $from->toDateString())
                ->whereDate('delivery_date', '<=', $until->toDateString())
                ->select('id'))
            ->sum('weight'), 2);
    }

    /**
     * Batas keras plan TANPA surat jalan: klaim tidak boleh melebihi yang diterima customer dalam 3 bulan terakhir.
     * Dipanggil dari halaman buat/ubah plan (plan dengan surat jalan dijaga `assertWithinDeliveredWeight()`).
     *
     * @throws \Exception
     */
    public static function assertWithinCustomerWindow(SalesReturnPlan $plan, int $productId, float $weight): void
    {
        if ($plan->delivery_order_id || ! $plan->customer_id) {
            return;
        }

        $delivered = self::deliveredWeight(null, (int) $plan->customer_id, $productId, $plan->plan_date);

        if ($delivered <= 0) {
            throw new \Exception(__('This product was not delivered to this customer in the last 3 months.'));
        }

        if ($weight > $delivered) {
            throw new \Exception(__('The claimed weight cannot exceed what was delivered to this customer in the last 3 months.'));
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
