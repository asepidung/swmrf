<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Rencana klaim retur yang dibuat SALES, sebelum gudang menerima fisiknya.
 *
 * `Draft` -> `Submitted` (siap ditarik gudang) -> `Received` (sudah
 * menjadi Sales Return) -> `Cancelled`. Hanya `Draft` yang bisa
 * diedit/dihapus bebas; sekali `Submitted` header terkunci KECUALI qty
 * klaim item-nya (lihat `SalesReturnPlanItem`), yang boleh dinego lewat
 * plan ini atau lewat halaman penerimaan gudang.
 */
class SalesReturnPlan extends Model
{
    use SoftDeletes, LogsActivity;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_RECEIVED = 'Received';

    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'plan_number',
        'plan_date',
        'customer_id',
        'delivery_order_id',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'plan_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnPlanItem::class, 'plan_id');
    }

    /**
     * @throws \RuntimeException
     */
    public function submit(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException(__('Only a draft plan can be submitted.'));
        }

        if (! $this->items()->exists()) {
            throw new \RuntimeException(__('A plan needs at least one item before it can be submitted.'));
        }

        $this->status = self::STATUS_SUBMITTED;
        $this->save();
    }

    /**
     * Dipanggil saat Sales Return yang menariknya di-approve.
     *
     * @throws \RuntimeException
     */
    public function markReceived(): void
    {
        if ($this->status !== self::STATUS_SUBMITTED) {
            throw new \RuntimeException(__('Only a submitted plan can become received.'));
        }

        $this->status = self::STATUS_RECEIVED;
        $this->save();
    }

    /**
     * Dipanggil saat Sales Return yang menariknya di-unlock atau dihapus.
     *
     * @throws \RuntimeException
     */
    public function markSubmitted(): void
    {
        if ($this->status !== self::STATUS_RECEIVED) {
            throw new \RuntimeException(__('Only a received plan can revert to submitted.'));
        }

        $this->status = self::STATUS_SUBMITTED;
        $this->save();
    }

    /**
     * @throws \RuntimeException
     */
    public function cancel(): void
    {
        if (! in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true)) {
            throw new \RuntimeException(__('A received plan can no longer be cancelled.'));
        }

        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan) {
            if (empty($plan->plan_number)) {
                $plan->plan_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'plan_number',
                    prefix: 'SRP#'.date('y'),
                    padding: 3,
                );
            }
            if (empty($plan->created_by)) {
                $plan->created_by = Auth::id();
            }
            if (empty($plan->status)) {
                $plan->status = self::STATUS_DRAFT;
            }
        });

        static::saving(function (self $plan) {
            if ($plan->delivery_order_id) {
                // DeliveryOrder::find(), bukan properti $plan->deliveryOrder --
                // lihat penjelasan di SalesReturnPlanItem::booted() soal
                // relasi belongsTo yang tercache dan tidak menyegarkan diri.
                $deliveryOrder = DeliveryOrder::find($plan->delivery_order_id);

                if ($deliveryOrder && (int) $deliveryOrder->customer_id !== (int) $plan->customer_id) {
                    throw new \Exception(__('The chosen delivery order does not belong to this customer.'));
                }
            }
        });

        static::updating(function (self $plan) {
            if ($plan->status === self::STATUS_DRAFT) {
                return;
            }

            $changed = array_diff(array_keys($plan->getDirty()), ['status', 'updated_at']);

            if ($changed !== []) {
                throw new \Exception(__('This sales return plan can no longer be edited because it is no longer a draft.'));
            }
        });

        static::deleting(function (self $plan) {
            if ($plan->status !== self::STATUS_DRAFT) {
                throw new \Exception(__('This sales return plan can only be deleted while it is still a draft.'));
            }
        });
    }
}
