<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris Bill of Material: satu bahan penolong yang dipakai satu produk.
 */
class ProductMaterial extends Model
{
    use HasFactory;

    /**
     * Dasar hitung sebuah baris BOM.
     *
     * **Satu rumah.** Daftarnya dipakai form, tabel, cetakan, dan penjaganya;
     * disalin ke salah satunya saja, salinan itu akan berbeda pada perubahan
     * berikutnya. Nilainya mengikuti bentuk stok daging yang sudah ada: satu
     * baris `beef_stocks` adalah satu BOX berbarcode, dan `qty_pcs` isinya.
     */
    public const BASIS = [
        'box' => 'Per Box',
        'piece' => 'Per Pcs',
    ];

    protected $fillable = [
        'product_id',
        'material_id',
        'quantity',
        'basis',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Bahannya dipakai, tetapi jumlahnya tidak tetap.
     *
     * Drylog adalah alasan keadaan ini ada: jumlahnya berbeda-beda walau
     * produknya sama, jadi tidak ada satu angka pun yang benar untuk ditulis.
     * Kosong TIDAK sama dengan nol -- nol berarti tidak dipakai, dan baris
     * yang tidak dipakai memang dihapus, bukan dinolkan.
     */
    public function jumlahnyaTidakTetap(): bool
    {
        return $this->quantity === null;
    }

    /** Label dasar hitungnya, atau nilai mentahnya bila belum dikenal. */
    public function labelBasis(): string
    {
        return static::BASIS[$this->basis] ?? $this->basis;
    }
}
