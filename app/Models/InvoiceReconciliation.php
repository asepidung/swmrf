<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReconciliation extends Model
{
    // Point to the MySQL view
    protected $table = 'invoice_reconciliation_view';

    // The view uses string IDs (e.g., 'item_1', 'charge_1')
    public $incrementing = false;
    protected $keyType = 'string';

    // Disable timestamps since views don't usually support saving/updating directly
    public $timestamps = false;

    protected $casts = [
        'weight' => 'float',
        'price' => 'float',
        'discount_percent' => 'float',
        'discount_rp' => 'float',
        'amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Apa yang tertulis di baris ini: produknya, atau nama biayanya.
     *
     * Aturannya dulu ditulis ulang di tiga tempat -- kolom di layar, berkas
     * ekspor, dan baris Excel di halaman daftar. Yang ketiga menuliskannya
     * dengan cara yang berbeda: ia membaca `item_name` sebagai KOLOM.
     *
     * `item_name` bukan kolom. Tampilan `invoice_reconciliation_view` punya
     * `row_type` dan `charge_name`, tidak punya `item_name`. Eloquent
     * menjawab null untuk properti yang tidak ada, jadi kolom "Product /
     * Charge" di berkas Excel-nya SELALU KOSONG -- tanpa galat, dan tanpa
     * ada yang aneh di layar, karena layar memakai jalur yang berbeda.
     *
     * Sekarang ia benar-benar ada, dan ketiganya membacanya dari sini.
     */
    public function getItemNameAttribute(): string
    {
        if ($this->row_type === 'product') {
            return $this->product?->name ?? '-';
        }

        return $this->charge_name ?: '-';
    }
}
