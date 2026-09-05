<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialStock extends Model
{
    use HasFactory;

    protected $fillable = ['material_id', 'qty'];

    /**
     * Stok material tidak boleh bergerak selama opname material berjalan.
     *
     * Bentuknya sama persis dengan `BeefStock`, dan itu memang disengaja --
     * keputusan Owner: konsepnya sama dengan opname daging. Sebelum ini sisi
     * material tidak punya pembekuan sama sekali, sehingga penerimaan dan
     * pemakaian tetap berjalan di tengah hitungan dan membuat selisih yang
     * tersimpan mengukur jarak ke angka yang sudah tidak ada lagi.
     */
    protected static function booted(): void
    {
        static::creating(fn () => \App\Services\MaterialStockFreezeService::check());
        static::updating(fn () => \App\Services\MaterialStockFreezeService::check());
        static::deleting(fn () => \App\Services\MaterialStockFreezeService::check());
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
