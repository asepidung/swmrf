<?php

namespace App\Models;

use App\Support\DocumentNumber;
use App\Support\InvoiceTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesReturn extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'return_number',
        'return_date',
        'delivery_order_id',
        'credit_amount',
        'customer_id',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'credit_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->return_number)) {
                $model->return_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'return_number',
                    prefix: 'SR#'.date('y'),
                    padding: 3,
                );
            }
            if (empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });
    }

    /**
     * Setujui retur ini: seluruh barangnya masuk ke stok.
     *
     * @throws \RuntimeException
     */
    public function approve(): void
    {
        if ($this->status !== 'Draft') {
            throw new \RuntimeException(__('Only a draft return can be approved.'));
        }

        if ($this->items->isEmpty()) {
            throw new \RuntimeException(__('This return has no item yet.'));
        }

        $this->guardAgainstOverReturn();

        DB::transaction(function (): void {
            $this->update(['status' => 'Approved']);

            foreach ($this->items as $item) {
                BeefStock::create([
                    'barcode' => $item->barcode,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'grade_id' => $item->grade_id,
                    'weight' => $item->weight,
                    'qty_pcs' => $item->qty_pcs,
                    'ph_level' => $item->ph_level,
                    'pack_date' => $item->pack_date,
                    'exp_date' => $item->exp_date,
                    'origin' => $item->origin,
                    'status' => 'IN_STOCK',
                    'note' => 'Sales Return '.$this->return_number,
                ]);

                BeefStockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'condition' => $item->grade_id,
                    'barcode' => $item->barcode,
                    'transaction_type' => 'SALES_RETURN',
                    'reference_document' => $this->return_number,
                    'weight_in' => $item->weight,
                    'pcs_in' => $item->qty_pcs,
                    'created_by' => Auth::id(),
                    'note' => 'Sales Return from Customer',
                ]);
            }

            // Barangnya sudah kembali ke gudang. Sekarang uangnya.
            $this->attachToBill();
        });
    }

    /**
     * Buka kunci retur ini: seluruh barangnya ditarik kembali dari stok.
     *
     * SATU RUMAH untuk keduanya. Rutin ini dulu disalin utuh di halaman Edit
     * DAN halaman View -- termasuk celah izinnya, sehingga menambal yang satu
     * meninggalkan yang lain tetap terbuka. Pola yang sama sudah pernah
     * ditemukan pada saldo hutang yang disalin enam kali dan rumus tagihan
     * yang disalin lima kali.
     *
     * Barangnya diperiksa lebih dulu SEMUANYA, baru ditarik. Menarik separuh
     * lalu berhenti di tengah karena satu barang sudah terlanjur dikirim lagi
     * meninggalkan stok yang tidak cocok dengan dokumen mana pun.
     *
     * @throws \RuntimeException
     */
    public function unlock(): void
    {
        if ($this->status !== 'Approved') {
            throw new \RuntimeException(__('Only an approved return can be unlocked.'));
        }

        DB::transaction(function (): void {
            foreach ($this->items as $item) {
                $stock = BeefStock::where('barcode', $item->barcode)->lockForUpdate()->first();

                if (! $stock) {
                    throw new \RuntimeException(__('Item :barcode is no longer in stock (already used or shipped).', [
                        'barcode' => $item->barcode,
                    ]));
                }

                if ($stock->status !== 'IN_STOCK') {
                    throw new \RuntimeException(__('Item :barcode is no longer in the warehouse (status: :status).', [
                        'barcode' => $item->barcode,
                        'status' => $stock->status,
                    ]));
                }
            }

            foreach ($this->items as $item) {
                BeefStockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'condition' => $item->grade_id,
                    'barcode' => $item->barcode,
                    'transaction_type' => 'CANCEL_SALES_RETURN',
                    'reference_document' => $this->return_number,
                    'weight_out' => $item->weight,
                    'pcs_out' => $item->qty_pcs,
                    'created_by' => Auth::id(),
                    'note' => 'Unlock/Cancel Sales Return',
                ]);

                BeefStock::where('barcode', $item->barcode)->delete();
            }

            $this->detachFromBill();

            $this->update(['status' => 'Draft']);
        });
    }

    // =================================================================
    // Sisi uang: retur memotong tagihan pelanggan
    // =================================================================

    /**
     * Tidak boleh mengembalikan lebih banyak daripada yang pernah ditagihkan.
     *
     * Diperiksa per INVOICE per PRODUK, bukan sekadar totalnya: mengembalikan
     * 20 kg sirloin yang tidak pernah dikirim tetap salah walaupun invoicenya
     * memuat 500 kg ribeye.
     *
     * Retur yang sudah disetujui sebelumnya ikut dihitung, jadi dua retur
     * kecil tidak bisa bersama-sama melewati batas yang tidak bisa dilewati
     * satu retur besar.
     *
     * Yang TIDAK bisa ditangkap dari sini: kalau sebuah box ditolak dengan
     * cara MENURUNKAN "Received Weight" di halaman Approve DO alih-alih
     * memakai tombol Rejections, lalu belakangan box itu dibuatkan Sales
     * Return. Bukti terima hanya menyimpan total per produk, bukan per
     * barcode, jadi tidak ada data yang bisa membedakannya. Menutupnya berarti
     * membuat Rejections satu-satunya jalan menolak box -- pekerjaan
     * tersendiri.
     *
     * @throws \RuntimeException
     */
    private function guardAgainstOverReturn(): void
    {
        $rekap = [];

        foreach ($this->items as $item) {
            $invoice = $item->billItWasChargedOn();

            // Karton yang kirimannya belum ditagihkan tidak punya batas untuk
            // dibandingkan. Ia diperiksa lagi nanti, saat invoicenya terbit
            // dan memungutnya.
            if (! $invoice) {
                continue;
            }

            $kunci = $invoice->getKey().':'.$item->product_id;

            $rekap[$kunci] ??= [
                'invoice' => $invoice,
                'product_id' => (int) $item->product_id,
                'berat' => 0.0,
            ];

            $rekap[$kunci]['berat'] += (float) $item->weight;
        }

        foreach ($rekap as $baris) {
            $invoice = $baris['invoice'];
            $ditagih = $invoice->billedWeightFor($baris['product_id']);

            // Produk yang TIDAK ADA di invoice ini tidak punya batas untuk
            // dilanggar -- ia memang berharga nol, dan nolnya terbaca di layar.
            // Itu terjadi pada barang yang ditimbang ulang dengan produk yang
            // berbeda dari yang dikirim. Menolak returnya berarti menahan
            // barangnya di luar stok hanya karena uangnya nol.
            if ($ditagih <= 0) {
                continue;
            }

            $sudahDiretur = $invoice->returnedWeightFor($baris['product_id']);
            $seluruhnya = round($sudahDiretur + $baris['berat'], 2);

            if ($seluruhnya > $ditagih) {
                throw new \RuntimeException(__(
                    'Returning :returned kg of :product is more than the :billed kg billed on invoice :invoice.',
                    [
                        'returned' => number_format($seluruhnya, 2),
                        'product' => Product::find($baris['product_id'])?->name ?? '-',
                        'billed' => number_format($ditagih, 2),
                        'invoice' => $invoice->invoice_number,
                    ],
                ));
            }
        }
    }

    /**
     * Invoice yang dipotong retur ini -- bisa LEBIH DARI SATU.
     *
     * Tautannya ada di kartonnya, bukan di returnya. Satu retur boleh memuat
     * barang dari beberapa kiriman sekaligus, dan tiap kiriman punya
     * invoicenya sendiri. Project Owner, 4 September 2026: pelanggan sebesar
     * Lion Superindo memang mengembalikan barang dari beberapa kiriman dalam
     * satu kali jalan, dan justru untuk itulah retur tanpa surat jalan
     * ("Unidentified Delivery") disediakan.
     *
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function billsReduced(): \Illuminate\Support\Collection
    {
        return Invoice::query()
            ->whereIn('id', $this->items()->whereNotNull('invoice_id')->pluck('invoice_id')->unique())
            ->get();
    }

    /**
     * Rekam harga jual tiap karton, lalu tempelkan nilainya ke invoicenya
     * masing-masing.
     *
     * Harganya DI-SNAPSHOT, bukan dibaca ulang tiap kali dibutuhkan. Harga
     * bergerak -- lewat Price List, lewat diskon pelanggan, lewat Sales Order
     * berikutnya -- dan nota retur yang ikut bergerak berarti angka yang sudah
     * disepakati berubah sendiri di belakang punggung orang.
     *
     * Karton yang kirimannya BELUM ditagihkan tetap dinilai tetapi belum
     * menempel ke mana-mana. Ia menunggu, dan invoice yang lahir kemudian
     * untuk surat jalan itu yang memungutnya.
     */
    public function attachToBill(): void
    {
        $total = 0.0;
        $invoices = [];

        foreach ($this->items as $item) {
            $invoice = $item->billItWasChargedOn();
            [$perKg, $jumlah] = $this->sellingPriceFor($item, $invoice);

            $item->forceFill([
                'invoice_id' => $invoice?->getKey(),
                'unit_price' => $perKg,
                'line_amount' => $jumlah,
            ])->save();

            $total += $jumlah;

            if ($invoice) {
                $invoices[$invoice->getKey()] = $invoice;
            }
        }

        $this->forceFill(['credit_amount' => round($total, 2)])->save();

        foreach ($invoices as $invoice) {
            $invoice->settleAfterCreditNote();
        }
    }

    /**
     * Lepaskan potongannya kembali dari semua invoice yang tersentuh.
     *
     * Alokasi pembayaran yang sudah TERLANJUR dilepas tidak dipasang kembali
     * di sini. Uang itu sekarang menjadi deposit pelanggan, dan dipakai lagi
     * lewat halaman Terima Pembayaran seperti lebih bayar biasa. Memasangnya
     * kembali otomatis berarti menebak pembayaran mana yang dulu menutup
     * invoice mana, padahal jejaknya sudah tidak ada.
     */
    public function detachFromBill(): void
    {
        $invoices = $this->billsReduced();

        $this->items()->update(['invoice_id' => null, 'line_amount' => 0]);
        $this->forceFill(['credit_amount' => 0])->save();

        foreach ($invoices as $invoice) {
            $invoice->settleAfterCreditNote();
        }
    }

    /**
     * Harga jual satu karton retur: per kg, dan jumlah barisnya.
     *
     * Dua sumber, dengan urutan yang tidak boleh dibalik:
     *
     *  1. baris INVOICE untuk produk yang sama. `amount / weight` sudah
     *     memperhitungkan diskon barisnya, jadi yang dikembalikan kepada
     *     pelanggan persis sebesar yang ditagihkan kepadanya;
     *  2. baris SALES ORDER dari kiriman kartonnya sendiri, lewat
     *     `InvoiceTotals::line()` -- rumus yang sama persis dengan yang
     *     dipakai `InvoiceResource::billableLines()` untuk melahirkan invoice.
     *     Dipakai kalau invoicenya belum ada, supaya angkanya tidak bisa
     *     berbeda dari invoice yang akan terbit.
     *
     * Kalau produknya tidak ketemu di keduanya, harganya nol dan terbaca nol
     * di layar. Itu bisa terjadi pada barang yang ditimbang ulang dengan
     * produk yang berbeda dari yang dikirim. Menebak harganya lebih buruk
     * daripada menunjukkan bahwa ia belum berharga.
     *
     * @return array{0: float, 1: float} [harga per kg, jumlah baris]
     */
    private function sellingPriceFor(SalesReturnItem $item, ?Invoice $invoice): array
    {
        $berat = (float) $item->weight;

        if ($invoice) {
            $baris = $invoice->items->firstWhere('product_id', $item->product_id);

            if ($baris && (float) $baris->weight > 0) {
                $perKg = (float) $baris->amount / (float) $baris->weight;

                return [round($perKg, 2), round($berat * $perKg, 0)];
            }
        }

        // Sales Order diambil dari kiriman KARTONNYA, bukan dari surat jalan
        // yang tertulis di returnya. Retur tanpa surat jalan pun tetap
        // berharga, dan retur lintas kiriman memakai harga masing-masing.
        $salesOrderId = $item->originDelivery()?->sales_order_id;

        if ($salesOrderId) {
            $baris = SalesOrderItem::query()
                ->where('sales_order_id', $salesOrderId)
                ->where('product_id', $item->product_id)
                ->first();

            if ($baris) {
                $hasil = InvoiceTotals::line($berat, (float) $baris->price, (float) $baris->discount);
                $jumlah = (float) $hasil['amount'];

                return [$berat > 0 ? round($jumlah / $berat, 2) : 0.0, $jumlah];
            }
        }

        return [0.0, 0.0];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
