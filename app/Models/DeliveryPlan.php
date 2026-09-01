<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DeliveryPlan extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'customer_id',
        'delivery_date',
        'driver',
        'armada',
        'load_time',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'customer.name',
                'delivery_date',
                'driver',
                'armada',
                'load_time',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Status Sales Order yang berarti pekerjaannya sudah selesai.
     *
     * 'canceled' dan 'cancelled' dua-duanya ada di basis data warisan, dan
     * keduanya harus ikut terhitung selesai.
     */
    public const FINISHED_SALES_ORDER_STATUSES = ['completed', 'cancelled', 'canceled'];

    /**
     * Jadwal yang masih perlu dilihat petugas distribusi.
     *
     * Batasnya AKHIR HARI KIRIM, bukan peristiwa dokumen. Ketiga peristiwa
     * yang tersedia sama-sama keliru sebagai penanda, dan alasannya datang
     * dari Project Owner:
     *
     *  - Surat jalan dibuat: kadang dibuat sehari sebelum berangkat, jadi
     *    jadwalnya hilang padahal barangnya belum ke mana-mana. Perlu
     *    diperhatikan bahwa status `on_delivery` pada Sales Order juga
     *    dipasang pada saat ini, bukan saat truk berangkat -- jadi status
     *    itu pun bukan penanda yang dicari.
     *  - Resi penerimaan dibuat: itu berarti sopir sudah pulang, jauh
     *    sesudah jadwalnya tidak relevan lagi.
     *  - Lewat dari hari kirim: pengiriman sore hilang sejak pagi.
     *
     * Hari kirim itu sendiri yang menjadi ukuran: sebuah jadwal berhenti
     * menjadi jadwal ketika harinya habis. Pengiriman sore tetap terlihat
     * sepanjang hari itu.
     *
     * Satu tambahan yang menutup lubangnya: jadwal yang HARINYA SUDAH LEWAT
     * tetapi pekerjaannya belum selesai tetap ditampilkan. Tanpa itu,
     * pengiriman yang tertunda lenyap diam-diam pada tengah malam justru
     * ketika ia paling perlu dilihat.
     */
    public function scopeStillRelevant($query)
    {
        return $query->where(function ($query) {
            $query
                ->whereDate('delivery_date', '>=', now()->toDateString())
                ->orWhereHas(
                    'salesOrders',
                    fn ($salesOrders) => $salesOrders->whereNotIn(
                        'status',
                        self::FINISHED_SALES_ORDER_STATUSES,
                    ),
                );
        });
    }

    /** Hari kirimnya sudah lewat sementara pekerjaannya belum selesai. */
    public function isOverdue(): bool
    {
        if ($this->delivery_date >= now()->toDateString()) {
            return false;
        }

        return $this->salesOrders
            ->whereNotIn('status', self::FINISHED_SALES_ORDER_STATUSES)
            ->isNotEmpty();
    }

    /**
     * Sejauh mana jadwal ini sudah berjalan.
     *
     * Dihitung dari relasi yang SUDAH dimuat, jadi tidak menembak kueri
     * tambahan per baris.
     */
    public function progressLabel(): string
    {
        $statuses = $this->salesOrders->pluck('status');

        if ($statuses->isEmpty()) {
            return __('No sales order yet');
        }

        if ($statuses->every(fn ($status) => in_array($status, self::FINISHED_SALES_ORDER_STATUSES, true))) {
            return __('Delivered');
        }

        if ($statuses->contains('on_delivery')) {
            return __('Delivery note issued');
        }

        if ($statuses->contains(fn ($status) => in_array($status, ['processing', 'ready'], true))) {
            return __('Being prepared');
        }

        return __('Not started');
    }

    public function getNotesAttribute(): string
    {
        return $this->salesOrders->pluck('delivery_note')->filter()->unique()->implode(' | ');
    }

    public function getSalesOrdersCountAttribute(): int
    {
        return $this->salesOrders()->count();
    }

    /**
     * Total berat seluruh Sales Order pada jadwal ini.
     *
     * Dijumlahkan dari relasi yang SUDAH dimuat. Bentuk sebelumnya
     * menjumlahkan lewat query builder pada relasi item, yang menembak satu
     * kueri untuk SETIAP Sales Order pada SETIAP baris tabel -- dan daftar
     * ini menampilkan seluruh jadwal yang pernah dibuat.
     */
    public function getTotalQtyAttribute(): float
    {
        return (float) $this->salesOrders->sum(fn ($so) => $so->items->sum('weight'));
    }
}
