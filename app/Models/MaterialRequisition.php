<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MaterialRequisition extends Model
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
            ->useLogName($this->document_number ?? 'Material Requisition');
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
                prefix: 'MR#' . date('y'),
                padding: 3,
            );
        });
    }

    public function updateTotalAmount()
    {
        $total = $this->items()->sum('subtotal');
        $tax = 0;
        if ($this->supplier && $this->supplier->is_tax_11) {
            $tax = $total * 0.11;
        }
        $this->update([
            'total_amount' => $total + $tax,
            'tax_amount' => $tax
        ]);
    }

    public function items()
    {
        return $this->hasMany(MaterialRequisitionItem::class);
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

    public function purchaseMaterial()
    {
        return $this->hasOne(PurchaseMaterial::class);
    }

    public function generatePurchaseOrder()
    {
        $this->loadMissing(['items', 'supplier']);

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
                query: PurchaseMaterial::withTrashed(),
                column: 'po_number',
                prefix: 'SWM-MPO#' . date('y'),
                padding: 3,
            );

            $po = PurchaseMaterial::create([
                'po_number' => $poNumber,
                'material_requisition_id' => $this->id,
                'supplier_id' => $this->supplier_id,
                'approved_by' => auth()->id() ?? 1, // fallback to 1 if console
                'po_date' => $this->due_date ?? now(),
                'total_amount' => $this->total_amount,
                'note' => $this->note,
            ]);

            foreach ($this->items as $item) {
                PurchaseMaterialItem::create([
                    'purchase_material_id' => $po->id,
                    'material_id' => $item->material_id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ]);
            }
        });
    }
}
