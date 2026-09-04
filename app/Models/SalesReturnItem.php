<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'invoice_id',
        'origin_delivery_order_id',
        'product_id',
        'warehouse_id',
        'grade_id',
        'barcode',
        'weight',
        'credited_weight',
        'qty_pcs',
        'unit_price',
        'line_amount',
        'ph_level',
        'pack_date',
        'exp_date',
        'origin',
        'is_repacked',
    ];

    protected $casts = [
        'weight' => 'float',
        'credited_weight' => 'float',
        'qty_pcs' => 'integer',
        'unit_price' => 'decimal:2',
        'line_amount' => 'decimal:2',
        'ph_level' => 'float',
        'pack_date' => 'date',
        'exp_date' => 'date',
        'is_repacked' => 'boolean',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /** Invoice yang dipotong karton ini. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Kiriman tempat karton ini dulu keluar, dicari dari barcodenya sendiri.
     *
     * Inilah yang membuat retur lintas pengiriman mungkin. Barcode sebuah
     * karton sudah cukup untuk menemukan tally yang mengeluarkannya, dan dari
     * tally itu surat jalannya. Returnya sendiri tidak perlu tahu apa-apa --
     * dan memang sering tidak tahu, karena pelanggan mengembalikan barang dari
     * beberapa kiriman dalam satu kali jalan.
     *
     * PENGIRIMAN TERAKHIR yang diambil, bukan yang pertama. Barcode unik per
     * tally, bukan global: satu karton yang pernah diretur lalu dikirim lagi
     * memakai barcode yang sama. Yang sedang dikembalikan pelanggan tentu
     * kiriman terakhirnya, bukan kiriman tahun lalu.
     */
    public function deliveryItWasShippedOn(): ?DeliveryOrder
    {
        if (! $this->barcode) {
            return null;
        }

        return DeliveryOrder::query()
            ->whereHas('tally', fn ($q) => $q->whereHas(
                'items',
                fn ($i) => $i->where('barcode', $this->barcode),
            ))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Kiriman asal karton ini, dari barcodenya atau dari returnya.
     *
     * Barcodenya ditanya lebih dulu, karena itu jawaban yang paling tepat:
     * satu karton tahu sendiri ia keluar di surat jalan mana, dan retur lintas
     * pengiriman memang tidak punya satu surat jalan untuk semuanya.
     *
     * Barang TIMBANG ULANG tidak punya jawaban itu. Ia diberi barcode BARU
     * berawalan 4 saat diretur, dan barcode baru tidak pernah ada di tally
     * mana pun. Untuk barang semacam itu yang menjawab surat jalan yang
     * tertulis di returnya -- dan kalau returnya pun tidak menyebut apa-apa,
     * memang tidak ada jawabannya.
     */
    public function originDelivery(): ?DeliveryOrder
    {
        // Yang DITULIS orangnya menang atas apa pun yang bisa ditebak.
        //
        // Karton yang rusak dan sulit dibaca barcodenya di-barcode ULANG saat
        // diretur, sehingga barcodenya baru dan tidak menunjuk ke kiriman mana
        // pun. Kalau returnya juga tidak menyebut surat jalan -- dan retur
        // lintas pengiriman memang tidak -- tidak ada yang bisa ditebak sama
        // sekali, dan barang itu dulu berharga nol tanpa satu pun gejala.
        if ($this->origin_delivery_order_id) {
            return $this->originDeliveryOrder;
        }

        return $this->deliveryItWasShippedOn() ?? $this->salesReturn?->deliveryOrder;
    }

    /** Surat jalan asal yang ditulis orangnya, kalau memang diisi. */
    public function originDeliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'origin_delivery_order_id');
    }

    /**
     * Invoice yang menagihkan karton ini -- kalau memang sudah terbit.
     *
     * Jalurnya surat jalan -> bukti terima -> invoice, karena invoice lahir
     * dari bukti terima, bukan langsung dari surat jalannya. Project Owner:
     * "invoice itu lahir setelah do receipt karena do receipt itu yang jadi
     * draft invoice".
     */
    public function billItWasChargedOn(): ?Invoice
    {
        $deliveryOrder = $this->originDelivery();

        if (! $deliveryOrder) {
            return null;
        }

        return Invoice::query()
            ->whereHas(
                'deliveryOrderReceipt',
                fn ($q) => $q->where('delivery_order_id', $deliveryOrder->getKey()),
            )
            ->first();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
