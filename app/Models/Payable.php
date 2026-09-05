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
    /**
     * Satu-satunya tempat saldo dan status hutang dihitung.
     *
     * Sebelumnya rumus ini disalin di ENAM tempat -- lima di berkas ini dan
     * satu di halaman pembayaran. Selama semuanya menghitung hal yang sama,
     * salinan itu tidak terasa; begitu ada faktor baru seperti kompensasi, ia
     * hanya berlaku di sebagian tempat, dan hutang yang sama menunjukkan angka
     * berbeda tergantung jalur mana yang menyentuhnya terakhir.
     *
     * `amount` tidak pernah diubah. Ia nilai asli yang disepakati, dan
     * kompensasi berdiri di sampingnya supaya keduanya tetap terbaca.
     */
    public function recalculate(): void
    {
        $amount = (float) $this->amount;
        $compensation = (float) $this->compensation;
        $paid = (float) $this->paid_amount;

        $harusDibayar = max($amount - $compensation, 0);

        $this->balance = $harusDibayar - $paid;

        if ($paid <= 0) {
            // Kompensasi yang menutup seluruh tagihan membuat hutangnya lunas
            // tanpa satu rupiah pun berpindah -- dan itu memang benar.
            $this->status = $harusDibayar <= 0 ? 'paid' : 'unpaid';

            return;
        }

        $this->status = $paid >= $harusDibayar ? 'paid' : 'partial';
    }

    /**
     * Catat kompensasi dari pemasok atas hutang ini.
     *
     * Bentuknya potongan TOTAL, bukan potongan per kilo. Keputusan Project
     * Owner: yang sebenarnya dinegosiasikan memang angka bulat, dan
     * menurunkannya menjadi harga per kilo adalah ketelitian yang dikarang.
     *
     * KOMPENSASI TIDAK PERNAH MENYENTUH KERUGIAN. Rancangan sebelumnya
     * membedakan kompensasi karena berat dan karena kualitas, dan yang karena
     * berat ikut mengurangi kerugian susut. Penjelasan Owner membatalkan
     * dasarnya: komplainnya selalu soal mutu -- lemaknya terlalu banyak,
     * hasil dagingnya sedikit. Pemasok tidak pernah mengganti karena beratnya
     * kurang.
     *
     * Pembedaan itu karena itu membedakan sesuatu yang tidak dibedakan di
     * lapangan, dan justru pembedaan itulah bagian yang berbahaya: salah
     * memilih alasan menghapus kerugian susut yang nyata, tanpa satu pun
     * gejala. Jangan dipasang lagi.
     *
     * Susut timbang tetap utuh karena beratnya memang tidak pernah sampai.
     * Gambaran utuhnya terbaca dari kedua angka yang berdiri sendiri.
     *
     * @throws \InvalidArgumentException
     */
    public function applyCompensation(float $amount, ?string $note = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('Compensation must be more than zero.'));
        }

        // Tidak boleh melebihi yang belum dibayar. Kompensasi yang lebih besar
        // daripada sisa hutang berarti pemasok berhutang kepada kita, dan itu
        // hal lain yang butuh dokumennya sendiri.
        //
        // Perhatikan bahwa ini SATU-SATUNYA batas. Kompensasi yang lebih besar
        // daripada kerugian susut tidak perlu dibatasi sama sekali, karena ia
        // memang tidak pernah menyentuhnya.
        $sisa = (float) $this->amount - (float) $this->compensation - (float) $this->paid_amount;

        if ($amount > $sisa) {
            throw new \InvalidArgumentException(
                __('Compensation cannot be more than the outstanding amount.')
            );
        }

        $this->compensation = (float) $this->compensation + $amount;
        $this->compensation_note = $note;

        $this->recalculate();
        $this->save();
    }

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
        $this->recalculate();
        $this->save();
    }

    public static function generateForGoodsReceipt(GoodsReceiptMaterial $gr): self
    {
        $gr->loadMissing(['items', 'supplier']);
        
        $subtotalSum = $gr->items->sum('subtotal');
        $tax = $gr->supplier?->ppnAtas($subtotalSum) ?? 0;
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
        $payable->due_date = $dueDate;

        // Uang muka dipotongkan sebelum status dihitung, supaya dokumen yang
        // sudah lunas di muka tidak sempat tercatat sebagai 'unpaid'.
        static::applyAdvancesBehind($gr, $payable);

        $payable->recalculate();
        
        $payable->created_by = auth()->id() ?? $gr->created_by;
        $payable->save();
        
        return $payable;
    }

    public static function generateForGoodsReceiptProduct(GoodsReceiptProduct $gr): self
    {
        $gr->loadMissing(['items', 'supplier']);
        
        $subtotalSum = $gr->items->sum('subtotal');
        $tax = $gr->supplier?->ppnAtas($subtotalSum) ?? 0;
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
        $payable->due_date = $dueDate;

        // Uang muka dipotongkan sebelum status dihitung, supaya dokumen yang
        // sudah lunas di muka tidak sempat tercatat sebagai 'unpaid'.
        static::applyAdvancesBehind($gr, $payable);

        $payable->recalculate();
        
        $payable->created_by = auth()->id() ?? $gr->created_by;
        $payable->save();
        
        return $payable;
    }

    /**
     * Utang dari penerimaan sapi hidup.
     *
     * Keputusan Project Owner: utang terbit begitu sapi diterima, dan
     * acuannya berat yang diisi operator saat penerimaan -- bukan berat hasil
     * penimbangan ulang. Selisih di penimbangan sudah punya tempatnya sendiri
     * sebagai Financial Loss; ia tidak mengurangi apa yang harus dibayar ke
     * supplier.
     *
     * Harga per kg diambil dari PO Cattle sesuai kelas sapinya, karena PO
     * adalah kontraknya. Harga tidak boleh dibaca dari tempat lain, apalagi
     * diketik ulang saat penerimaan.
     *
     * MENGEMBALIKAN NULL bila ada kelas sapi yang tidak punya harga di PO.
     * Form penerimaan tidak membatasi pilihan kelas ke isi PO, jadi kasus itu
     * nyata bisa terjadi. Menghitungnya sebagai nol akan menerbitkan utang
     * yang lebih kecil dari seharusnya -- tanpa error apa pun, dan baru
     * ketahuan saat supplier menagih. Lebih baik utangnya belum terbit dan
     * terlihat, daripada terbit dengan angka yang salah dan terlihat wajar.
     */
    public static function generateForCattleReceiving(CattleReceiving $receiving): ?self
    {
        $receiving->loadMissing(['items', 'supplier', 'purchaseCattle.items']);

        $pricePerClass = $receiving->purchaseCattle
            ? $receiving->purchaseCattle->items->pluck('price', 'cattle_class_id')
            : collect();

        $unpriced = $receiving->items
            ->reject(fn ($item): bool => $pricePerClass->has($item->cattle_class_id));

        if ($receiving->items->isEmpty() || $unpriced->isNotEmpty()) {
            return null;
        }

        $subtotal = $receiving->items->sum(
            fn ($item): float => (float) $item->initial_weight * (float) $pricePerClass[$item->cattle_class_id]
        );

        // Pajak mengikuti flag supplier, sama seperti GR Beef dan GR Material.
        $tax = $receiving->supplier?->ppnAtas($subtotal) ?? 0;
        $amount = $subtotal + $tax;

        $topDays = $receiving->supplier->top_days ?? 0;

        // withTrashed(): utang yang pernah dibatalkan hanya di-soft-delete.
        // Tanpa ini, menyimpan ulang dokumen membuat baris BARU dengan
        // document_number yang sama dan langsung kena unique constraint.
        $payable = static::withTrashed()
            ->where('payableable_type', get_class($receiving))
            ->where('payableable_id', $receiving->id)
            ->first() ?: new self();

        if ($payable->trashed()) {
            $payable->restore();
        }

        $payable->payableable_type = get_class($receiving);
        $payable->payableable_id = $receiving->id;
        $payable->supplier_id = $receiving->supplier_id;
        $payable->document_number = $receiving->receiving_number;
        $payable->amount = $amount;
        $payable->due_date = Carbon::parse($receiving->receive_date)->addDays($topDays);
        $payable->recalculate();
        
        $payable->created_by = auth()->id() ?? $receiving->created_by;
        $payable->save();

        return $payable;
    }

    /** Kelas sapi pada penerimaan ini yang belum punya harga di PO-nya. */
    public static function unpricedCattleClasses(CattleReceiving $receiving): array
    {
        $receiving->loadMissing(['items.cattleClass', 'purchaseCattle.items']);

        $priced = $receiving->purchaseCattle
            ? $receiving->purchaseCattle->items->pluck('cattle_class_id')
            : collect();

        return $receiving->items
            ->reject(fn ($item): bool => $priced->contains($item->cattle_class_id))
            ->map(fn ($item): string => $item->cattleClass?->name ?? '-')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Jenis pembelian di balik sebuah hutang.
     *
     * Petanya ditaruh DI SINI, bukan di masing-masing tampilan. Sebelumnya
     * `payableable_type` cuma dipetakan di form halaman detail, dan hanya
     * untuk GR Material -- GR Beef dan penerimaan sapi tampil sebagai nama
     * kelas mentah, `App\Models\GoodsReceiptProduct`, di layar pengguna.
     *
     * Dengan satu peta, kolom tabel, filter, dan halaman detail tidak bisa
     * lagi saling berbeda, dan sumber hutang baru cukup didaftarkan sekali.
     *
     * @return array<class-string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            CattleReceiving::class => __('Cattle Purchase'),
            GoodsReceiptProduct::class => __('Meat Purchase'),
            GoodsReceiptMaterial::class => __('Goods Purchase'),
        ];
    }

    /** Warna kategori, supaya daftar hutang bisa dipindai sekilas. */
    public static function sourceColors(): array
    {
        return [
            CattleReceiving::class => 'warning',
            GoodsReceiptProduct::class => 'danger',
            GoodsReceiptMaterial::class => 'info',
        ];
    }

    /**
     * Label sebuah jenis dokumen.
     *
     * Jenis yang belum terdaftar dikembalikan APA ADANYA, bukan disamarkan
     * menjadi tanda hubung: kalau suatu saat ada sumber hutang baru yang lupa
     * didaftarkan, nama kelasnya di layar adalah petunjuk yang menunjukkan
     * persis apa yang harus ditambahkan.
     */
    public static function sourceLabel(?string $type): string
    {
        return static::sourceLabels()[$type] ?? (string) $type;
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
