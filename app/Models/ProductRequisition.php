<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ProductRequisition extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'document_number',
        'user_id',
        'supplier_id',
        'due_date',
        'note',
        'reject_note',
        'terms_of_payment',
        'tax_type',
        'tax_amount',
        'total_amount',
        'status',
        'reviewed_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->document_number ?? 'Beef Request');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            // Nomor lewat `DocumentNumber`, bukan MENGHITUNG BARIS.
            //
            // Bentuk lamanya `count() + 1` atas seluruh permintaan tahun
            // berjalan. Menghitung baris bukan hal yang sama dengan mengambil
            // nomor TERTINGGI: sekali ada satu baris yang benar-benar hilang,
            // hitungannya turun dan nomor yang sudah terpakai diterbitkan
            // lagi -- ditolak unique index dengan galat SQL mentah.
            //
            // `lockForUpdate()` pada sebuah `count()` juga tidak mengunci apa
            // pun ketika hasilnya nol, jadi permintaan PERTAMA setiap tahun
            // justru yang paling rawan kembar.
            $model->document_number = \App\Support\DocumentNumber::next(
                query: static::withTrashed(),
                column: 'document_number',
                prefix: 'BR#' . date('y'),
                padding: 3,
            );
        });
    }

    public function updateTotalAmount()
    {
        $total = $this->items()->sum('subtotal');
        $tax = $this->supplier?->ppnAtas($total) ?? 0;
        $this->update([
            'total_amount' => $total + $tax,
            'tax_amount' => $tax
        ]);
    }

    public function items()
    {
        return $this->hasMany(ProductRequisitionItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function purchaseProduct()
    {
        return $this->hasOne(PurchaseProduct::class);
    }

    /**
     * Terbitkan PO dari request ini.
     *
     * Menolak bila PO-nya sudah pernah terbit. Ini lapis kedua di belakang
     * penjagaan status pada halaman Finance Approval: tanpa keduanya, membuka
     * ulang URL finance-approval untuk dokumen ber-status PO Created
     * menerbitkan PO KEDUA tanpa error apa pun, lengkap dengan dokumen uang
     * muka kedua. Pemanggil tidak perlu menangkap pengecualian ini - halaman
     * yang benar tidak akan pernah sampai ke sini.
     *
     * PO yang sudah di-soft-delete sengaja tidak dihitung, supaya dokumen yang
     * dibatalkan masih bisa diterbitkan ulang.
     *
     * @throws \RuntimeException bila PO untuk request ini sudah ada
     */
    public function generatePurchaseOrder()
    {
        $this->loadMissing(['items', 'supplier']);

        if ($this->purchaseProduct()->exists()) {
            throw new \RuntimeException(
                __('A purchase order for :document has already been issued.', ['document' => $this->document_number])
            );
        }

        DB::transaction(function () {
            // Nomor PO lewat `DocumentNumber`, bukan MENGHITUNG BARIS.
            //
            // Bentuk lamanya `count() + 1` atas seluruh PO tahun berjalan.
            // Menghitung baris bukan hal yang sama dengan mengambil nomor
            // TERTINGGI: sekali ada satu baris yang benar-benar hilang,
            // hitungannya turun dan nomor yang sudah terpakai diterbitkan
            // lagi.
            //
            // Dan `lockForUpdate()` pada sebuah `count()` tidak mengunci apa
            // pun ketika hasilnya nol -- tidak ada baris yang bisa dikunci.
            // PO PERTAMA setiap tahun karena itu justru yang paling rawan
            // kembar.
            $poNumber = \App\Support\DocumentNumber::next(
                query: PurchaseProduct::withTrashed(),
                column: 'po_number',
                prefix: 'SWM-BPO#' . date('y'),
                padding: 3,
            );

            $po = PurchaseProduct::create([
                'po_number' => $poNumber,
                'product_requisition_id' => $this->id,
                'supplier_id' => $this->supplier_id,
                'approved_by' => auth()->id(),
                'po_date' => $this->due_date ?? now(),
                'total_amount' => $this->total_amount,
                'note' => $this->note,
            ]);

            foreach ($this->items as $item) {
                PurchaseProductItem::create([
                    'purchase_product_id' => $po->id,
                    'product_id' => $item->product_id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ]);
            }
        });
    }
}
