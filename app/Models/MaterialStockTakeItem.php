<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaterialStockTakeItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'material_stock_take_id',
        'material_id',
        'system_qty',
        'physical_qty',
        'difference_qty',
        'note',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    /**
     * Jejak angka lama saat "Minta Hitung Ulang" membuka kunci baris ini --
     * `logOnlyDirty()` menyimpan nilai lama DAN baru tiap kali physical_qty/
     * difference_qty berubah, dan Spatie mencatat siapa yang melakukannya
     * secara bawaan.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['physical_qty', 'difference_qty', 'is_locked'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Selisih hitungan ini dibaca sebagai apa: Over, Short, atau Sesuai.
     *
     * Sebelumnya penentuan ini beserta warnanya ditulis DUA KALI, di
     * `ItemsRelationManager` dan di `ManageMaterialStockTakeItems`, dengan isi
     * yang sama persis. Dua salinan dari satu aturan adalah bentuk yang
     * berkali-kali terbukti berakhir berbeda -- di modul ini saja sudah
     * terjadi pada cara menerapkan hasil opname (#285).
     */
    public function varianceLabel(): string
    {
        if ($this->physical_qty === null) {
            return '-';
        }

        return match (true) {
            $this->difference_qty > 0 => __('Over'),
            $this->difference_qty < 0 => __('Short'),
            default => __('Matches'),
        };
    }

    /** Warna untuk `varianceLabel()`. */
    public function varianceColor(): string
    {
        if ($this->physical_qty === null) {
            return 'gray';
        }

        return match (true) {
            $this->difference_qty > 0 => 'info',
            $this->difference_qty < 0 => 'danger',
            default => 'success',
        };
    }

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(MaterialStockTake::class, 'material_stock_take_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
