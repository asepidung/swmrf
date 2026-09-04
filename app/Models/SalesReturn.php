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
        'invoice_id',
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
     * Invoice yang dipotong retur ini, kalau sudah menempel.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Invoice untuk surat jalan yang diretur ini -- kalau memang sudah ada.
     *
     * Jalurnya surat jalan -> bukti terima -> invoice, karena invoice memang
     * lahir dari bukti terima, bukan langsung dari surat jalannya.
     */
    public function billForThisDelivery(): ?Invoice
    {
        if (! $this->delivery_order_id) {
            return null;
        }

        return Invoice::query()
            ->whereHas(
                'deliveryOrderReceipt',
                fn ($q) => $q->where('delivery_order_id', $this->delivery_order_id),
            )
            ->first();
    }

    /**
     * Rekam harga jual tiap barang, lalu tempelkan nilainya ke invoice.
     *
     * Harganya DI-SNAPSHOT, bukan dibaca ulang tiap kali dibutuhkan. Harga
     * bergerak -- lewat Price List, lewat diskon pelanggan, lewat Sales Order
     * berikutnya -- dan nota retur yang ikut bergerak berarti angka yang sudah
     * disepakati berubah sendiri di belakang punggung orang.
     *
     * Kalau invoicenya BELUM ada, returnya tetap dinilai tetapi belum menempel
     * ke mana-mana. Ia menunggu, dan `Invoice` yang lahir kemudian untuk surat
     * jalan ini yang memungutnya.
     */
    public function attachToBill(): void
    {
        $invoice = $this->billForThisDelivery();

        $total = 0.0;

        foreach ($this->items as $item) {
            [$perKg, $jumlah] = $this->sellingPriceFor($item, $invoice);

            $item->forceFill([
                'unit_price' => $perKg,
                'line_amount' => $jumlah,
            ])->save();

            $total += $jumlah;
        }

        $this->forceFill([
            'credit_amount' => round($total, 2),
            'invoice_id' => $invoice?->getKey(),
        ])->save();

        $invoice?->settleAfterCreditNote();
    }

    /**
     * Lepaskan potongannya kembali.
     *
     * Alokasi pembayaran yang sudah TERLANJUR dilepas tidak dipasang kembali
     * di sini. Uang itu sekarang menjadi deposit pelanggan, dan dipakai lagi
     * lewat halaman Terima Pembayaran seperti lebih bayar biasa. Memasangnya
     * kembali otomatis berarti menebak pembayaran mana yang dulu menutup
     * invoice mana, padahal jejaknya sudah tidak ada.
     */
    public function detachFromBill(): void
    {
        $invoice = $this->invoice;

        $this->forceFill([
            'credit_amount' => 0,
            'invoice_id' => null,
        ])->save();

        $invoice?->settleAfterCreditNote();
    }

    /**
     * Harga jual satu barang retur: per kg, dan jumlah barisnya.
     *
     * Dua sumber, dengan urutan yang tidak boleh dibalik:
     *
     *  1. baris INVOICE untuk produk yang sama. `amount / weight` sudah
     *     memperhitungkan diskon barisnya, jadi yang dikembalikan kepada
     *     pelanggan persis sebesar yang ditagihkan kepadanya;
     *  2. baris SALES ORDER, lewat `InvoiceTotals::line()` -- rumus yang sama
     *     persis dengan yang dipakai `InvoiceResource::billableLines()` untuk
     *     melahirkan invoice. Dipakai kalau invoicenya belum ada, supaya
     *     angkanya tidak bisa berbeda dari invoice yang akan terbit.
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

        $salesOrderId = $this->deliveryOrder?->sales_order_id;

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
