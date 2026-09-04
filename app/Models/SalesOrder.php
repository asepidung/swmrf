<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Satu-satunya tempat status Sales Order ditulis.
     *
     * Enam nilai, dan seluruhnya ditulis sebagai teks mentah tersebar di
     * belasan berkas -- sama seperti status Invoice sebelum dirapikan. Yang
     * membuatnya lebih berbahaya di sini: kata `processing` dan `cancelled`
     * JUGA dipakai sebagai status Tally, jadi teks yang sama berarti dua hal
     * berbeda tergantung tabel yang sedang dibicarakan. Konstanta membuat
     * kalimatnya menyebut miliknya sendiri.
     *
     * Aplikasi ini memakai DUA ejaan untuk kata batal: `cancelled` di Sales
     * Order, Tally, dan Delivery Plan; `canceled` di PO dan Goods Receipt.
     * Keduanya nyata dan dipakai modul yang berbeda -- yang salah bukan
     * ejaannya, melainkan kode Sales Order yang memeriksa KEDUANYA dengan
     * `in_array()` seolah miliknya sendiri bisa berbentuk dua rupa. Diperiksa
     * di basis data hosting: `completed=3 waiting=4`, tidak ada satu pun baris
     * berejaan satu L. Pemeriksaan gandanya dilepas.
     */
    public const STATUS_WAITING = 'waiting';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_ON_DELIVERY = 'on_delivery';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Status yang membuat dokumennya tidak boleh disunting lagi. */
    public const STATUS_LOCKED_FOR_EDIT = [
        self::STATUS_CANCELLED,
        self::STATUS_READY,
    ];

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_WAITING => self::STATUS_WAITING,
            self::STATUS_PROCESSING => self::STATUS_PROCESSING,
            self::STATUS_READY => self::STATUS_READY,
            self::STATUS_ON_DELIVERY => self::STATUS_ON_DELIVERY,
            self::STATUS_COMPLETED => self::STATUS_COMPLETED,
            self::STATUS_CANCELLED => self::STATUS_CANCELLED,
        ];
    }

    protected $fillable = [
        'so_number',
        'customer_id',
        'delivery_date',
        'po_number',
        'shipping_address',
        'status',
        'note',
        'down_payment',
        'created_by',
        'delivery_plan_id',
        'delivery_note',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'down_payment' => 'float',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'so_number', 
                'customer.name', 
                'delivery_date', 
                'status', 
                'po_number', 
                'shipping_address', 
                'note'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->so_number)) {
                $year2Digit = date('y');

                $model->so_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'so_number',
                    prefix: 'SO#'.$year2Digit,
                    padding: 4,
                );
            }
        });

        static::saving(function ($model) {
            if ($model->customer_id && $model->delivery_date) {
                // Normalize new date to Y-m-d string
                $normalizedDate = \Carbon\Carbon::parse($model->delivery_date)->format('Y-m-d');

                // Check if date or customer actually changed
                $dateChanged = $model->getOriginal('delivery_date')
                    ? \Carbon\Carbon::parse($model->getOriginal('delivery_date'))->format('Y-m-d') !== $normalizedDate
                    : true;
                
                $customerChanged = $model->isDirty('customer_id');

                if ($dateChanged || $customerChanged || is_null($model->delivery_plan_id)) {
                    $plan = DeliveryPlan::withTrashed()->firstOrCreate(
                        [
                            'customer_id' => $model->customer_id,
                            'delivery_date' => $normalizedDate
                        ],
                        [
                            'created_by' => auth()->id()
                        ]
                    );
                    if ($plan->trashed()) {
                        $plan->restore();
                    }
                    $model->delivery_plan_id = $plan->id;
                }

                // Keep the date stored in clean Y-m-d format
                $model->delivery_date = $normalizedDate;
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tally()
    {
        return $this->hasOne(Tally::class, 'sales_order_id');
    }

    public function deliveryPlan()
    {
        return $this->belongsTo(DeliveryPlan::class);
    }
}
