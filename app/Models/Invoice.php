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
     * Satu-satunya tempat status invoice ditulis.
     *
     * Status invoice adalah kolom TEKS berbahasa Indonesia, dan "sudah dibayar
     * atau belum" ditentukan dengan membandingkannya ke teks itu di banyak
     * tempat. Satu salah ketik berarti invoice yang sudah lunas ikut terhitung
     * sebagai piutang -- tanpa satu pun gejala, karena perbandingan string
     * yang meleset tidak menghasilkan error, hanya jawaban yang salah.
     *
     * Dulu hanya 'Lunas' yang punya konstanta, dan itu pun baru dibuat saat
     * modul Piutang dikerjakan. Tiga sisanya masih ditulis ulang sebagai teks
     * di dua puluh tujuh tempat. Sekarang keempatnya punya rumah.
     *
     * NILAINYA TIDAK DIUBAH. Kolomnya sudah berisi teks ini di basis data yang
     * sedang berjalan, dan menggantinya berarti memindahkan data sungguhan --
     * pekerjaan tersendiri yang tidak dititipkan pada perapian ini.
     */
    public const STATUS_PAID = 'Lunas';

    public const STATUS_UNPAID = 'Belum Dibayar';

    /**
     * Tukar faktur: jatuh temponya BELUM dihitung sampai fakturnya ditukar.
     * Lihat hook `saving()` di bawah -- di sana kedua status ini menentukan
     * `due_date`, dan itu satu-satunya tempat yang boleh menghitungnya.
     */
    public const STATUS_EXCHANGE_PENDING = 'Belum TF';

    public const STATUS_EXCHANGED = 'Sudah TF';

    /** Seluruh status yang sah, untuk penyaring dan pilihan di form. */
    public static function statuses(): array
    {
        return [
            static::STATUS_UNPAID => static::STATUS_UNPAID,
            static::STATUS_EXCHANGE_PENDING => static::STATUS_EXCHANGE_PENDING,
            static::STATUS_EXCHANGED => static::STATUS_EXCHANGED,
            static::STATUS_PAID => static::STATUS_PAID,
        ];
    }

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
     * Karton retur yang memotong invoice ini.
     *
     * Tautannya di KARTONNYA, bukan di returnya. Satu retur boleh memuat
     * barang dari beberapa kiriman sekaligus -- dan karena itu memotong
     * beberapa invoice sekaligus. Lihat catatan di
     * `SalesReturnItem::deliveryItWasShippedOn()`.
     */
    public function returnedItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class, 'invoice_id');
    }

    /**
     * Berapa yang dikembalikan pelanggan, dalam rupiah.
     *
     * Hanya karton dari retur yang SUDAH DISETUJUI. Retur yang masih Draft
     * belum menggerakkan apa pun -- barangnya belum masuk stok, jadi uangnya
     * pun belum boleh memotong tagihan.
     */
    public function returnedAmount(): float
    {
        return round((float) $this->returnedItems()
            ->whereHas('salesReturn', fn ($q) => $q->where('status', 'Approved'))
            ->sum('line_amount'), 2);
    }

    /**
     * Berat yang sudah DIKREDITKAN untuk satu produk -- bukan berat fisiknya.
     *
     * Dua angka itu memang bisa berbeda. Kita mengirim satu box 20,00 kg;
     * pelanggan menimbang ulang dan mendapat 19,80 kg, dan itu yang
     * ditagihkan. Saat boxnya dikembalikan, 20,00 kg masuk gudang tetapi yang
     * boleh dikreditkan tetap 19,80 kg -- karena hanya sebesar itu yang pernah
     * ditagihkan kepada mereka.
     *
     * Memakai berat fisik di sini akan membuat sisa jatah retur menyusut lebih
     * cepat daripada seharusnya.
     */
    public function returnedWeightFor(int $productId, ?int $exceptSalesReturnId = null): float
    {
        return round((float) $this->returnedItems()
            ->where('product_id', $productId)
            ->whereHas('salesReturn', fn ($q) => $q
                ->where('status', 'Approved')
                // Satu retur TIDAK boleh menghitung dirinya sendiri sebagai
                // jatah yang sudah terpakai.
                //
                // Nilainya dihitung ulang setiap kali salah satu kartonnya
                // dipungut invoice yang baru terbit -- dan itu memang terjadi
                // pada retur lintas pengiriman, tempat sebagian kiriman sudah
                // ditagih dan sebagian belum. Tanpa pengecualian ini, pada
                // hitungan kedua returnya melihat kreditnya sendiri sebagai
                // jatah yang habis, lalu mengkreditkan dirinya NOL. Potongan
                // yang sudah benar lenyap tanpa satu pun gejala.
                ->when($exceptSalesReturnId, fn ($q) => $q->whereKeyNot($exceptSalesReturnId)))
            ->sum('credited_weight'), 2);
    }

    /** Berat yang ditagihkan invoice ini untuk satu produk. */
    public function billedWeightFor(int $productId): float
    {
        return round((float) $this->items()->where('product_id', $productId)->sum('weight'), 2);
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

    /**
     * Pungut retur yang sudah disetujui tapi belum menempel ke invoice mana pun.
     *
     * Retur bisa terjadi SEBELUM invoicenya dibuat -- barangnya balik hari itu
     * juga, tagihannya menyusul seminggu kemudian. Ketika itu returnya menunggu
     * dengan `invoice_id` kosong, dan invoice yang lahir untuk surat jalan yang
     * sama inilah yang memungutnya.
     *
     * Invoicenya tetap terbit PENUH lalu berkurang oleh nota returnya, bukan
     * lahir dengan angka yang diam-diam sudah dikurangi. Bedanya terlihat di
     * dokumen: pelanggan bisa membaca apa yang ditagihkan dan apa yang
     * dikembalikan sebagai dua baris, bukan satu angka yang harus dipercaya.
     */
    public function collectPendingSalesReturns(): void
    {
        if (! $this->delivery_order_receipt_id) {
            return;
        }

        $deliveryOrderId = $this->deliveryOrderReceipt?->delivery_order_id;

        if (! $deliveryOrderId) {
            return;
        }

        // Yang dicari KARTONNYA, bukan returnya -- dan lewat barcodenya,
        // bukan lewat surat jalan yang tertulis di returnya. Retur lintas
        // pengiriman punya barang dari beberapa surat jalan sekaligus, jadi
        // yang menentukan adalah asal tiap karton.
        $menunggu = SalesReturnItem::query()
            ->whereNull('invoice_id')
            ->whereHas('salesReturn', fn ($q) => $q->where('status', 'Approved'))
            ->get()
            ->filter(fn (SalesReturnItem $item): bool =>
                $item->originDelivery()?->getKey() === $deliveryOrderId);

        if ($menunggu->isEmpty()) {
            return;
        }

        foreach ($menunggu as $item) {
            $item->forceFill(['invoice_id' => $this->getKey()])->save();
        }

        // Harganya dihitung ulang dari invoice yang baru terbit ini. Waktu
        // returnya disetujui invoicenya belum ada, jadi harganya diambil dari
        // Sales Order; sekarang sumber yang lebih tepat sudah tersedia.
        foreach ($menunggu->pluck('salesReturn')->unique('id') as $retur) {
            $retur->refresh()->attachToBill();
        }

        $this->settleAfterCreditNote();
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
            - (float) $this->down_payment
            // Barang yang dikembalikan pelanggan tidak jadi ditagihkan.
            //
            // Dikurangkan DI SINI, bukan dengan mengubah baris invoicenya.
            // Invoice yang sudah terbit ada di tangan pelanggan; menggeser
            // angkanya membuat dokumen yang mereka pegang tidak cocok dengan
            // tagihan yang kita kirim. Yang benar adalah nota retur tersendiri
            // yang terbaca sebagai pengurang -- pola yang sama dengan potongan
            // pembayaran.
            - $this->returnedAmount();
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
            $this->status = static::STATUS_UNPAID;
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

    /**
     * Hitung ulang sesudah nilai returnya berubah.
     *
     * Urutannya penting. Yang sudah dibayar dilepaskan LEBIH DULU, baru sisa
     * tagihannya diturunkan -- kalau dibalik, `recalculate()` memangkas
     * balance di nol dan kelebihannya lenyap dari pandangan tanpa satu pun
     * gejala.
     */
    public function settleAfterCreditNote(): void
    {
        $this->refresh();
        $this->releaseOverpaidAllocations();

        // recalculate() dipanggil sendiri oleh hook saving().
        $this->save();
    }

    /**
     * Kembalikan uang yang jadi kelebihan karena adanya retur.
     *
     * Pelanggan sudah membayar penuh, lalu mengembalikan barang. Tidak ada
     * lagi yang bisa dipotong dari tagihan ini -- uangnya sudah masuk. Yang
     * benar: alokasinya DILEPAS sebesar kelebihannya, sehingga uang itu
     * kembali menjadi deposit pelanggan lewat `Payment::unallocatedAmount()`
     * yang sudah ada.
     *
     * Satu kolam deposit, satu aturan. Membuat kolam kedua khusus retur
     * berarti dua tempat yang harus sepakat tentang "berapa uang pelanggan
     * yang belum terpakai" -- dan dua tempat semacam itu selalu berselisih
     * pada akhirnya.
     *
     * Yang dilepas alokasi TERBARU dulu, kebalikan dari urutan pemasangannya.
     * Pembayaran paling lama tetap menempel di tempatnya, jadi riwayat
     * pelunasan yang sudah lama tidak ikut terusik.
     */
    protected function releaseOverpaidAllocations(): void
    {
        $lebih = round((float) $this->paid_amount - $this->billedAmount(), 2);

        if ($lebih <= 0) {
            return;
        }

        foreach ($this->paymentAllocations()->orderByDesc('id')->get() as $alokasi) {
            if ($lebih <= 0) {
                break;
            }

            $diambil = min($lebih, (float) $alokasi->amount_allocated);
            $sisa = round((float) $alokasi->amount_allocated - $diambil, 2);

            if ($sisa > 0) {
                $alokasi->update(['amount_allocated' => $sisa]);
            } else {
                $alokasi->delete();
            }

            $this->paid_amount = max(round((float) $this->paid_amount - $diambil, 2), 0);
            $lebih = round($lebih - $diambil, 2);
        }
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
            // Alokasi milik pembayaran yang SUDAH DIBATALKAN tidak menahan apa
            // pun. Kalau ikut dihitung, satu kali salah catat akan mengunci
            // invoicenya selamanya walaupun pembayarannya sudah dibalik.
            $masihAdaPembayaran = $model->paymentAllocations()
                ->whereHas('payment', fn ($q) => $q->whereNull('cancelled_at'))
                ->exists();

            if ($masihAdaPembayaran) {
                throw new \Exception(__('This invoice cannot be deleted because a customer payment is already recorded against it.'));
            }

            // Hapus lunak TIDAK memicu cascade di basis data -- itu UPDATE,
            // bukan DELETE. Tanpa baris ini piutangnya tetap hidup dan tetap
            // ditagihkan kepada pelanggan untuk invoice yang sudah tidak ada.
            if (! $model->isForceDeleting()) {
                $model->receivable?->delete();
                $model->releaseDeliveryDocuments();

                // Nota returnya dilepaskan, bukan ikut terhapus. Barangnya
                // sudah benar-benar kembali ke gudang; yang hilang hanya
                // invoice yang dipotongnya. Karton yang dilepas akan dipungut
                // sendiri oleh invoice pengganti untuk surat jalan yang sama.
                $model->returnedItems()->update(['invoice_id' => null]);
            }
        });

        // Dan pemulihannya harus mengembalikan keduanya, bukan cuma invoicenya.
        static::restored(function (Invoice $model) {
            $model->receivable()->withTrashed()->first()?->restore();
            $model->markDeliveryDocumentsInvoiced();
            $model->collectPendingSalesReturns();
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

            if ($model->status === static::STATUS_EXCHANGE_PENDING) {
                $model->due_date = null;
            } elseif ($model->status === static::STATUS_EXCHANGED) {
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
