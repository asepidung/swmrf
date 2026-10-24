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
     * Berat produk yang DITERIMA customer menurut bukti terima (DO receipt): di surat jalan yang dipilih, atau (tanpa surat jalan)
     * total yang diterima customer itu dalam 3 bulan sampai tanggal acuan. Surat jalan yang belum punya bukti terima memakai
     * berat surat jalannya. Hanya INFORMASI untuk pengisi plan, bukan batas (Owner, 24 Oktober 2026).
     */
    public static function deliveredWeight(?int $deliveryOrderId, ?int $customerId, int $productId, ?\Carbon\CarbonInterface $until = null): float
    {
        if ($deliveryOrderId) {
            $orderIds = collect([$deliveryOrderId]);
        } elseif ($customerId) {
            $until = \Carbon\Carbon::parse($until ?? now());
            $from = $until->copy()->subMonths(SalesReturnPlan::MAX_DELIVERY_AGE_MONTHS);

            $orderIds = DeliveryOrder::query()
                ->where('customer_id', $customerId)
                ->whereDate('delivery_date', '>=', $from->toDateString())
                ->whereDate('delivery_date', '<=', $until->toDateString())
                ->pluck('id');
        } else {
            return 0.0;
        }

        if ($orderIds->isEmpty()) {
            return 0.0;
        }

        $receiptWeight = DeliveryOrderReceiptItem::query()
            ->where('product_id', $productId)
            ->whereIn('delivery_order_receipt_id', DeliveryOrderReceipt::query()->whereIn('delivery_order_id', $orderIds)->select('id'))
            ->sum('weight');

        $withoutReceipt = $orderIds->diff(DeliveryOrderReceipt::query()->whereIn('delivery_order_id', $orderIds)->pluck('delivery_order_id'));

        $orderWeight = $withoutReceipt->isEmpty() ? 0 : DeliveryOrderItem::query()
            ->where('product_id', $productId)
            ->whereIn('delivery_order_id', $withoutReceipt)
            ->sum('weight');

        return round((float) $receiptWeight + (float) $orderWeight, 2);
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

        });

        static::updating(function (self $item) {
            $plan = $item->plan()->first();

            if ($plan && $plan->status === SalesReturnPlan::STATUS_RECEIVED) {
                throw new \Exception(__('This plan has already been received and its items can no longer be changed.'));
            }

        });

        static::deleting(function (self $item) {
            $plan = $item->plan()->first();

            if ($plan && $plan->status !== SalesReturnPlan::STATUS_DRAFT) {
                throw new \Exception(__('Items can only be removed from a sales return plan while it is still a draft.'));
            }
        });
    }
}
