<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Tally extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'tallies';

    /**
     * Satu-satunya tempat status Tally ditulis.
     *
     * TIGA nilai, bukan dua. Komentar di `$fillable` dulu menyebut
     * "'processing', 'locked'" saja, padahal `DeliveryOrder` menyetel status
     * ketiga -- `do` -- saat surat jalannya dibuat. Diperiksa di basis data
     * hosting: `do=3`. Nilai yang paling banyak ada justru yang tidak
     * disebutkan.
     *
     * Kata `processing` juga dipakai sebagai status Sales Order, dan artinya
     * berbeda: di sana pesanannya sedang disiapkan, di sini tallynya sedang
     * dipindai. Konstanta membuat tiap kalimat menyebut miliknya sendiri.
     */
    public const STATUS_PROCESSING = 'processing';

    /** Sudah dikunci; di layar tertulis "Approved". */
    public const STATUS_LOCKED = 'locked';

    /** Sudah menjadi surat jalan. Disetel oleh `DeliveryOrder`. */
    public const STATUS_DELIVERED = 'do';

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PROCESSING => self::STATUS_PROCESSING,
            self::STATUS_LOCKED => self::STATUS_LOCKED,
            self::STATUS_DELIVERED => self::STATUS_DELIVERED,
        ];
    }

    protected $fillable = [
        'sales_order_id',
        'tally_number',
        'status',
        'seal_number',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TallyItem::class, 'tally_id');
    }

    public function deliveryOrder(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeliveryOrder::class, 'tally_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        static::creating(function (Tally $tally) {
            if (empty($tally->created_by)) {
                if (Auth::check()) {
                    $tally->created_by = Auth::id();
                } else {
                    $tally->created_by = User::first()?->id ?? 1;
                }
            }

            if (empty($tally->tally_number)) {
                $tally->tally_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'tally_number',
                    prefix: 'TS#'.date('y'),
                    padding: 4,
                );
            }
        });

        static::deleting(function (Tally $tally) {
            // Restore all items back to stock by deleting them
            $tally->items()->get()->each->delete();

            // Revert sales order status to 'waiting'
            if ($tally->salesOrder) {
                $tally->salesOrder->update(['status' => \App\Models\SalesOrder::STATUS_WAITING]);
            }
        });
    }
}
