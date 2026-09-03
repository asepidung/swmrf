<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Invoice extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'delivery_order_receipt_id',
        'customer_id',
        'sales_order_id',
        'invoice_date',
        'due_date',
        'term_of_payment',
        'status',
        'invoice_exchange_date',
        'exchange_by',
        'exchange_note',
        'po_number',
        'delivery_order_number',
        'note',
        'total_weight',
        'subtotal',
        'total_discount',
        'tax',
        'charge',
        'additional_charges',
        'down_payment',
        'balance',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'invoice_exchange_date' => 'date',
        'term_of_payment' => 'integer',
        'total_weight' => 'float',
        'subtotal' => 'float',
        'total_discount' => 'float',
        'tax' => 'float',
        'charge' => 'float',
        'additional_charges' => 'array',
        'down_payment' => 'float',
        'balance' => 'float',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function deliveryOrderReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderReceipt::class, 'delivery_order_receipt_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function additionalCharges(): HasMany
    {
        return $this->hasMany(InvoiceAdditionalCharge::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id');
    }

    public function receivable(): HasOne
    {
        return $this->hasOne(Receivable::class, 'invoice_id');
    }

    /**
     * Kembalikan surat jalan dan bukti terimanya ke keadaan belum ditagih.
     *
     * Tanpa ini keduanya tetap bertanda 'Invoiced' padahal invoicenya sudah
     * tidak ada, dan bukti terima itu tidak akan pernah muncul lagi di daftar
     * Draft Invoice untuk ditagihkan ulang.
     */
    public function releaseDeliveryDocuments(): void
    {
        $receipt = $this->deliveryOrderReceipt;

        if (! $receipt) {
            return;
        }

        // 'Approved' adalah keadaan keduanya tepat sebelum ditagihkan:
        // bawaan kolom status bukti terima, dan yang dipasang halaman Approve
        // pada surat jalannya.
        $receipt->update(['status' => 'Approved']);
        $receipt->deliveryOrder?->update(['status' => 'Approved']);
    }

    /** Kebalikannya, dipakai saat invoicenya dipulihkan. */
    public function markDeliveryDocumentsInvoiced(): void
    {
        $receipt = $this->deliveryOrderReceipt;

        if (! $receipt) {
            return;
        }

        $receipt->update(['status' => 'Invoiced']);
        $receipt->deliveryOrder?->update(['status' => 'Invoiced']);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $year2Digit = date('y');
                $currentMonth = date('n');

                $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
                $romanMonth = $romans[$currentMonth - 1] ?? 'I';

                // Tahunnya sudah terkandung di prefix, jadi penyaring
                // `whereYear('created_at')` tidak lagi diperlukan -- dan
                // memang bukan penyaring yang tepat: dokumen bertanggal
                // mundur akan salah kelompok.
                $model->invoice_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'invoice_number',
                    prefix: 'SWM-INV#'.$year2Digit,
                    padding: 4,
                );
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id() ?? 1;
            }
        });

        // Pembayaran pelanggan sudah tercatat menunjuk ke invoice ini. Kalau
        // invoicenya hilang, pembayaran itu menunjuk ke dokumen yang tidak ada
        // lagi -- dan uang yang sudah masuk lenyap dari jejaknya tanpa satu pun
        // error. Mengikuti penjagaan yang sama pada PO daging dan PO bahan.
        static::deleting(function (Invoice $model) {
            if ($model->paymentAllocations()->exists()) {
                throw new \Exception(__('This invoice cannot be deleted because a customer payment is already recorded against it.'));
            }

            // Hapus lunak TIDAK memicu cascade di basis data -- itu UPDATE,
            // bukan DELETE. Tanpa baris ini piutangnya tetap hidup dan tetap
            // ditagihkan kepada pelanggan untuk invoice yang sudah tidak ada.
            if (! $model->isForceDeleting()) {
                $model->receivable?->delete();
                $model->releaseDeliveryDocuments();
            }
        });

        // Dan pemulihannya harus mengembalikan keduanya, bukan cuma invoicenya.
        static::restored(function (Invoice $model) {
            $model->receivable()->withTrashed()->first()?->restore();
            $model->markDeliveryDocumentsInvoiced();
        });

        static::saving(function ($model) {
            if ($model->status === 'Belum TF') {
                $model->due_date = null;
            } elseif ($model->status === 'Sudah TF') {
                if ($model->invoice_exchange_date) {
                    $model->due_date = \Carbon\Carbon::parse($model->invoice_exchange_date)->addDays((int)$model->term_of_payment)->toDateString();
                }
            } else {
                if ($model->invoice_date) {
                    $model->due_date = \Carbon\Carbon::parse($model->invoice_date)->addDays((int)$model->term_of_payment)->toDateString();
                }
            }
        });
    }
}
