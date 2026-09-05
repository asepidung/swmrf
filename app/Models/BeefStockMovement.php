<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class BeefStockMovement extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'beef_stock_movements';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'condition',
        'barcode',
        'transaction_type',
        'reference_document',
        'weight_in',
        'weight_out',
        'pcs_in',
        'pcs_out',
        'note',
        'created_by'
    ];

    protected $casts = [
        'weight_in' => 'float',
        'weight_out' => 'float',
        'pcs_in' => 'integer',
        'pcs_out' => 'integer',
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
        });
    }

    /**
     * Seluruh jenis pergerakan yang benar-benar ditulis aplikasi ini, beserta
     * ARAHNYA.
     *
     * Sebelumnya daftarnya ditulis ulang di dua tempat -- penyaring dan peta
     * warna -- dan keduanya tidak cocok dengan kenyataan:
     *
     *   - aplikasi menulis 19 jenis, penyaringnya menawarkan 10;
     *   - `TALLY_REVERT` ada di kedua daftar itu tetapi TIDAK PERNAH ditulis
     *     satu baris kode pun -- pilihan hantu yang selalu mengembalikan
     *     daftar kosong;
     *   - `VOID_STOCK` diberi warna HIJAU, padahal itu penghapusan stok
     *     manual. Barang KELUAR ditampilkan dengan warna yang berarti masuk.
     *
     * Bentuk yang sama sudah ditambal tiga kali di modul lain (Invoice, Sales
     * Order, Tally). Cara menemukannya selalu sama: tanyakan ke KODE nilai apa
     * saja yang benar-benar ditulis, jangan membaca daftar yang ditulis
     * tangan.
     *
     * Arahnya yang menentukan warna, bukan daftar warna tersendiri: sekali
     * sebuah jenis didaftarkan di sini, ia otomatis punya warna dan otomatis
     * bisa disaring.
     *
     * @return array<string, string> jenis => 'in' | 'out' | 'neutral'
     */
    public const TYPES = [
        'IN_GR_BEEF' => 'in',
        'VOID_GR_BEEF' => 'out',
        'IN_BONING' => 'in',
        'VOID_BONING' => 'out',
        'IN_REPACK' => 'in',
        'VOID_IN_REPACK' => 'out',
        'OUT_TO_REPACK' => 'out',
        'VOID_OUT_REPACK' => 'in',
        'TALLY' => 'out',
        'TALLY_RELABEL' => 'neutral',
        'MUTATION_IN' => 'in',
        'MUTATION_OUT' => 'out',
        'MUTATION_CANCEL' => 'in',
        'SALES_RETURN' => 'in',
        'CANCEL_SALES_RETURN' => 'out',
        'STOCK_TAKE_FOUND' => 'in',
        'STOCK_TAKE_LOSS' => 'out',
        'FOUND_ITEM' => 'in',
        'VOID_STOCK' => 'out',
    ];

    /** Pilihan untuk penyaring: seluruhnya, tanpa kecuali. */
    public static function typeOptions(): array
    {
        return array_combine(array_keys(self::TYPES), array_keys(self::TYPES));
    }

    /** Warna badge, ditentukan ARAH pergerakannya. */
    public static function typeColor(?string $type): string
    {
        return match (self::TYPES[$type] ?? null) {
            'in' => 'success',
            'out' => 'danger',
            'neutral' => 'warning',
            default => 'gray',
        };
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'condition'); // condition field stores grade_id
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
