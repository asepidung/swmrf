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

    /**
     * Satu-satunya tempat kata 'Lunas' ditulis.
     *
     * Status invoice adalah kolom teks berisi lima nilai campur bahasa, dan
     * "sudah dibayar atau belum" ditentukan dengan membandingkannya ke teks
     * ini di banyak tempat. Satu salah ketik berarti invoice yang sudah lunas
     * ikut terhitung sebagai piutang, tanpa satu pun gejala.
     */
    public const STATUS_PAID = 'Lunas';

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
        'paid_amount',
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
        'paid_amount' => 'float',
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

    /**
     * Berapa yang ditagihkan kepada pelanggan.
     *
     * Dihitung, bukan disimpan. Ketiga kolomnya sudah ada dan sudah punya
     * arti masing-masing sejak `charge` dipakai; menyimpan jumlahnya lagi
     * hanya menambah satu angka yang bisa berbeda dari penyusunnya.
     */
    public function billedAmount(): float
    {
        return (float) $this->subtotal
            + (float) $this->charge
            - (float) $this->down_payment;
    }

    /**
     * Sisa tagihan dan statusnya, dari satu tempat saja.
     *
     * Dulu `balance` dipakai bergantian oleh dua pihak yang tidak saling
     * tahu: form Invoice menghitungnya dari barang dan uang muka, sementara
     * penerimaan piutang menimpanya dengan sisa tagihan. Siapa pun yang
     * menyentuhnya terakhir menang, dan yang kalah adalah uang yang sudah
     * dibayar pelanggan.
     *
     * Sekarang `balance` TIDAK PERNAH ditulis dari luar. Ia selalu turunan
     * dari yang ditagihkan dikurangi yang sudah dibayar -- mengikuti
     * Payable::recalculate().
     */
    public function recalculate(): void
    {
        $billed = $this->billedAmount();
        $paid = (float) $this->paid_amount;

        $this->balance = max($billed - $paid, 0);

        // Status TF ('Belum TF' / 'Sudah TF') menyimpan riwayat tukar faktur,
        // bukan keadaan pembayaran, jadi ia hanya boleh ditimpa saat tagihannya
        // benar-benar habis.
        if ($this->balance <= 0) {
            $this->status = static::STATUS_PAID;
        } elseif ($this->status === static::STATUS_PAID) {
            $this->status = 'Belum Dibayar';
        }
    }

    /**
     * Catat satu pembayaran pelanggan.
     *
     * Yang bertambah adalah `paid_amount`; `balance` menyusul dengan
     * sendirinya. Tidak ada satu pun jalur yang boleh mengurangi `balance`
     * secara langsung lagi.
     */
    public function applyPayment(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('Payment must be more than zero.'));
        }

        $this->paid_amount = (float) $this->paid_amount + $amount;

        $this->recalculate();
        $this->save();
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

        // Sisa tagihan diturunkan ulang setiap kali barisnya disimpan, apa pun
        // yang dikirim form. Inilah yang membuat menyunting invoice tidak bisa
        // lagi menghapus pembayaran yang sudah diterima.
        static::saving(function (Invoice $model) {
            $model->recalculate();
        });

        static::saving(function ($model) {
            // Invoice yang sudah lunas TIDAK dihitung ulang jatuh temponya.
            // Tanggal itu sudah menjadi riwayat, dan menghitungnya ulang dari
            // tanggal invoice akan menggeser jatuh tempo hasil tukar faktur
            // justru pada saat pelanggannya membayar.
            if ($model->status === static::STATUS_PAID) {
                return;
            }

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
