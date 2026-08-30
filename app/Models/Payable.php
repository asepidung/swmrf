<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

use Carbon\Carbon;

class Payable extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $guarded = ['id'];

    /**
     * Potongkan uang muka yang sudah dibayar ke utang yang baru terbit.
     *
     * DP dibayar saat ORDER, sementara utang lahir saat barang DITERIMA. Tanpa
     * langkah ini, utang akan tercatat sebesar nilai penuh meski DP sudah
     * dibayar — kesalahan yang tidak menimbulkan error apa pun dan baru
     * ketahuan saat supplier menagih.
     *
     * Uang muka ditelusuri lewat rantai dokumen: Goods Receipt -> PO -> Request.
     */
    protected static function applyAdvancesFrom(?Model $source, self $payable): void
    {
        if ($source === null) {
            return;
        }

        $outstanding = (float) $payable->amount - (float) $payable->paid_amount;

        if ($outstanding <= 0) {
            return;
        }

        foreach (SupplierPayment::unallocatedFor($source) as $advance) {
            if ($outstanding <= 0) {
                break;
            }

            $applied = $advance->allocateTo($payable, $outstanding);

            if ($applied <= 0) {
                continue;
            }

            $payable->paid_amount = (float) $payable->paid_amount + $applied;
            $outstanding -= $applied;
        }
    }

    /**
     * Seluruh dokumen di belakang sebuah Goods Receipt yang mungkin memegang
     * uang muka: PO-nya, lalu Request yang menurunkan PO itu.
     *
     * Dulu hanya Request yang ditelusuri, karena DP memang dicatat saat
     * approve finance. Setelah DP dipindahkan ke halaman PO, uang mukanya
     * tersimpan dengan source_type = PurchaseProduct dan TIDAK PERNAH KETEMU
     * -- utang lahir sebesar nilai penuh seolah belum ada yang dibayar, tanpa
     * error apa pun, dan baru ketahuan saat supplier menagih.
     *
     * Keduanya ditelusuri supaya DP lama yang menempel di Request tetap
     * terpotong, sekaligus DP baru yang menempel di PO. Kalau kelak DP bisa
     * dibayar dari dokumen lain lagi, tambahkan di sini -- jangan menambah
     * pemanggilan applyAdvancesFrom() terpisah, supaya tidak ada yang lupa.
     *
     * @return array<int, Model>
     */
    protected static function advanceSourcesBehind(Model $gr): array
    {
        $po = $gr->purchaseMaterial ?? $gr->purchaseProduct ?? null;

        if ($po === null) {
            return [];
        }

        $requisition = $po->materialRequisition ?? $po->productRequisition ?? null;

        return array_values(array_filter([$po, $requisition]));
    }

    /** Potongkan uang muka dari seluruh dokumen di belakang Goods Receipt. */
    protected static function applyAdvancesBehind(Model $gr, self $payable): void
    {
        foreach (static::advanceSourcesBehind($gr) as $source) {
            static::applyAdvancesFrom($source, $payable);
        }
    }

    /**
     * Kembalikan uang muka yang sudah dipotongkan ke utang ini.
     *
     * WAJIB dipanggil sebelum sebuah utang dibatalkan (GR dibuka kuncinya).
     * Tanpa ini uang mukanya tercatat "sudah terpakai" untuk utang yang sudah
     * tidak ada, lalu HILANG PERMANEN -- utang berikutnya lahir sebesar nilai
     * penuh seolah DP-nya tidak pernah dibayar, tanpa error apa pun.
     *
     * Pelepasannya menyusuri dokumen di belakang GR dan mengurangi alokasi
     * dari yang paling belakang dibayar. Bila satu utang menyerap beberapa
     * uang muka sekaligus, pembagian per-dokumen bisa berbeda dari saat
     * dialokasikan -- tetapi TOTAL yang kembali ke kolam selalu tepat, dan
     * itulah satu-satunya yang menentukan perhitungan utang berikutnya.
     * Kalau kelak perlu laporan alokasi per pembayaran, barulah dibutuhkan
     * tabel alokasi tersendiri.
     */
    public function releaseAdvances(): void
    {
        $outstanding = (float) $this->paid_amount;

        if ($outstanding <= 0) {
            return;
        }

        $gr = $this->payableable;

        if ($gr === null) {
            return;
        }

        foreach (static::advanceSourcesBehind($gr) as $source) {
            foreach (SupplierPayment::allocatedFor($source) as $advance) {
                if ($outstanding <= 0) {
                    break 2;
                }

                $outstanding -= $advance->releaseAmount($outstanding);
            }
        }

        // Sisa yang tidak berhasil dilepas tetap tercatat sebagai terbayar.
        $this->paid_amount = max($outstanding, 0);
        $this->balance = (float) $this->amount - (float) $this->paid_amount;
        $this->status = $this->paid_amount <= 0 ? 'unpaid' : 'partial';
        $this->save();
    }

    public static function generateForGoodsReceipt(GoodsReceiptMaterial $gr): self
    {
        $gr->loadMissing(['items', 'supplier']);
        
        $subtotalSum = $gr->items->sum('subtotal');
        $tax = $gr->supplier && $gr->supplier->is_tax_11 ? ($subtotalSum * 0.11) : 0;
        $amount = $subtotalSum + $tax;
        
        $topDays = $gr->supplier->top_days ?? 0;
        $dueDate = Carbon::parse($gr->receive_date)->addDays($topDays);
        
        // withTrashed(): utang yang pernah dibatalkan hanya di-soft-delete.
        // Tanpa ini, mengunci ulang GR membuat baris BARU dengan
        // document_number yang sama dan langsung kena unique constraint --
        // GR yang sudah dibuka jadi tidak bisa dikunci lagi sama sekali.
        $payable = static::withTrashed()
            ->where('payableable_type', get_class($gr))
            ->where('payableable_id', $gr->id)
            ->first() ?: new self();

        if ($payable->trashed()) {
            $payable->restore();
        }

        $payable->payableable_type = get_class($gr);
        $payable->payableable_id = $gr->id;
        $payable->supplier_id = $gr->supplier_id;
        $payable->document_number = $gr->gr_number;
        $payable->amount = $amount;
        $payable->balance = $amount - $payable->paid_amount;
        $payable->due_date = $dueDate;

        // Uang muka dipotongkan sebelum status dihitung, supaya dokumen yang
        // sudah lunas di muka tidak sempat tercatat sebagai 'unpaid'.
        static::applyAdvancesBehind($gr, $payable);

        $payable->balance = $payable->amount - $payable->paid_amount;

        if ($payable->paid_amount <= 0) {
            $payable->status = 'unpaid';
        } elseif ($payable->paid_amount >= $payable->amount) {
            $payable->status = 'paid';
        } else {
            $payable->status = 'partial';
        }
        
        $payable->created_by = auth()->id() ?? $gr->created_by;
        $payable->save();
        
        return $payable;
    }

    public static function generateForGoodsReceiptProduct(GoodsReceiptProduct $gr): self
    {
        $gr->loadMissing(['items', 'supplier']);
        
        $subtotalSum = $gr->items->sum('subtotal');
        $tax = $gr->supplier && $gr->supplier->is_tax_11 ? ($subtotalSum * 0.11) : 0;
        $amount = $subtotalSum + $tax;
        
        $topDays = $gr->supplier->top_days ?? 0;
        $dueDate = Carbon::parse($gr->receive_date)->addDays($topDays);
        
        // withTrashed(): utang yang pernah dibatalkan hanya di-soft-delete.
        // Tanpa ini, mengunci ulang GR membuat baris BARU dengan
        // document_number yang sama dan langsung kena unique constraint --
        // GR yang sudah dibuka jadi tidak bisa dikunci lagi sama sekali.
        $payable = static::withTrashed()
            ->where('payableable_type', get_class($gr))
            ->where('payableable_id', $gr->id)
            ->first() ?: new self();

        if ($payable->trashed()) {
            $payable->restore();
        }

        $payable->payableable_type = get_class($gr);
        $payable->payableable_id = $gr->id;
        $payable->supplier_id = $gr->supplier_id;
        $payable->document_number = $gr->gr_number;
        $payable->amount = $amount;
        $payable->balance = $amount - $payable->paid_amount;
        $payable->due_date = $dueDate;

        // Uang muka dipotongkan sebelum status dihitung, supaya dokumen yang
        // sudah lunas di muka tidak sempat tercatat sebagai 'unpaid'.
        static::applyAdvancesBehind($gr, $payable);

        $payable->balance = $payable->amount - $payable->paid_amount;

        if ($payable->paid_amount <= 0) {
            $payable->status = 'unpaid';
        } elseif ($payable->paid_amount >= $payable->amount) {
            $payable->status = 'paid';
        } else {
            $payable->status = 'partial';
        }
        
        $payable->created_by = auth()->id() ?? $gr->created_by;
        $payable->save();
        
        return $payable;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->document_number ?? 'Payable');
    }

    public function payableable()
    {
        return $this->morphTo();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
