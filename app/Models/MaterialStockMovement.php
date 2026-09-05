<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'transaction_type',
        'reference_document',
        'qty_in',
        'qty_out',
        'balance',
        'note',
        'created_by',
    ];

    /**
     * Seluruh jenis pergerakan material yang benar-benar ditulis aplikasi ini.
     *
     * Penyaringnya dulu menawarkan empat -- `GR`, `ISSUE`, `ADJUSTMENT`,
     * `RETUR` -- padahal aplikasi menulis sembilan, dan `ISSUE` tidak pernah
     * ditulis satu baris kode pun. Enam jenis karena itu tidak bisa disaring
     * sama sekali, termasuk seluruh pemakaian material.
     *
     * Dua di antaranya berbahasa Indonesia dan memakai SPASI:
     * `TEMUAN MATERIAL` dan `PEMBATALAN TEMUAN MATERIAL`. Bentuknya memang
     * berbeda dari yang lain, dan nilainya TIDAK diubah -- kolomnya sudah
     * berisi teks itu di basis data yang berjalan, dan menggantinya berarti
     * memindahkan data sungguhan.
     *
     * @return array<string, string> jenis => 'in' | 'out' | 'neutral'
     */
    public const TYPES = [
        'GR' => 'in',
        'RETUR' => 'out',
        'ADJUSTMENT' => 'neutral',
        'MATERIAL_USAGE' => 'out',
        'MATERIAL_USAGE_REVERT' => 'in',
        'MATERIAL_USAGE_ADJUST' => 'neutral',
        'STOCK_TAKE_ADJUSTMENT' => 'neutral',
        'TEMUAN MATERIAL' => 'in',
        'PEMBATALAN TEMUAN MATERIAL' => 'out',
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

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
