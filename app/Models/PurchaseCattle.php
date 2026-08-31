<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseCattle extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'purchase_cattles';

    protected $fillable = [
        'document_number',
        'supplier_id',
        'shipping_date',
        'summary_note',
        'created_by',
    ];

    protected $casts = [
        'shipping_date' => 'date',
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
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (empty($model->document_number)) {
                $model->document_number = static::generateDocumentNumber();
            }
        });

        static::deleting(function ($model) {
            if ($model->receivings()->exists()) {
                throw new \Exception(__('Cannot delete PO Cattle because it has already been received.'));
            }
        });
    }

    /**
     * Nomor dokumen berikutnya: SWM-CPO#26001.
     *
     * TIDAK memakai `substr(-3)` untuk membaca urutan terakhir. Cara itu
     * membuat modul berhenti bekerja di PO ke-1000: nomornya menjadi
     * `...1000`, tiga digit terakhirnya terbaca `000`, urutan berikutnya
     * dihitung 1, dan nomor yang sudah ada dicoba lagi -- menabrak unique
     * index dengan error yang tidak menjelaskan apa-apa. 999 PO setahun
     * kira-kira 2,7 per hari; di RPH itu sangat mungkin tercapai.
     *
     * Sekarang urutannya dibaca dari SELURUH bagian setelah prefix, jadi
     * format lama yang tiga digit tetap terbaca dan nomornya tumbuh sendiri
     * menjadi empat digit saat melewati 999.
     *
     * Urutannya juga tidak boleh diambil dengan `orderBy` string biasa:
     * `...26999` dianggap lebih besar daripada `...261000` karena '9' > '1'.
     * Diurutkan berdasarkan PANJANG lebih dulu -- didukung MySQL maupun
     * SQLite, dan testing berjalan di SQLite.
     *
     * `lockForUpdate()` sengaja TIDAK dibungkus transaksinya sendiri di
     * sini. Transaksi yang bersarang di dalam `creating()` akan commit
     * sebelum Eloquent menjalankan INSERT, sehingga kuncinya lepas justru
     * pada celah yang seharusnya dijaga. Pemanggilnya yang membuka
     * transaksi, sehingga kunci bertahan sampai barisnya benar-benar
     * tersimpan -- lihat CreatePurchaseCattle::handleRecordCreation().
     */
    public static function generateDocumentNumber(): string
    {
        return DocumentNumber::next(
            query: static::withTrashed(),
            column: 'document_number',
            prefix: 'SWM-CPO#'.date('y'),
            padding: 3,
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseCattleItem::class, 'purchase_cattle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(CattleReceiving::class, 'purchase_cattle_id');
    }
}
