<?php

namespace App\Models;

use App\Services\StockService;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MaterialFinding extends Model
{
    /**
     * Hapus LUNAK, supaya nomor dokumennya tidak dipakai ulang.
     *
     * Penghapusan menulis catatan pergerakan yang menyebut nomor dokumen ini
     * sebagai acuan. Kalau barisnya benar-benar hilang, nomornya bebas dan
     * dipakai lagi oleh temuan berikutnya -- dua dokumen berbeda dirujuk satu
     * nomor yang sama di buku besar.
     */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_number',
        'date',
        'material_id',
        'qty',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        static::creating(function (MaterialFinding $finding) {
            // Pembekuan diperiksa SEBELUM barisnya ada.
            //
            // Penyesuaian stoknya dilakukan di `created`, sesudah barisnya
            // tersimpan. Sejak stok material ikut dibekukan selama opname
            // (#285), penyesuaian itu bisa DITOLAK -- dan barisnya sudah
            // terlanjur ada. Hasilnya dokumen temuan yang mengaku menambah
            // stok padahal tidak ada satu pun pergerakan yang tercatat.
            //
            // Sudah dibuktikan: baris temuan tersimpan, buku besarnya kosong.
            \App\Services\MaterialStockFreezeService::check();

            if (! $finding->document_number) {
                // Nomornya lewat `DocumentNumber`, bukan penomoran sendiri.
                //
                // Bentuk lamanya membaca dokumen TERAKHIR MENURUT ID pada
                // tanggal yang sama lalu memungut empat digit terakhirnya
                // dengan regex. Model ini tidak memakai hapus lunak, jadi
                // menghapus satu temuan MEMBEBASKAN nomornya -- dan nomor itu
                // dipakai lagi oleh temuan berikutnya, sementara catatan
                // pergerakan yang lama masih menyebutnya sebagai acuan. Dua
                // dokumen berbeda, satu nomor acuan.
                //
                // `DocumentNumber` juga mengunci barisnya, sehingga dua orang
                // yang menyimpan bersamaan tidak mendapat nomor yang sama.
                $finding->document_number = DocumentNumber::next(
                    // withTrashed(): temuan yang DIHAPUS tetap memegang
                    // nomornya.
                    query: static::withTrashed(),
                    column: 'document_number',
                    prefix: 'FND-MTR-'.Carbon::parse($finding->date ?? now())->format('ymd').'-',
                    padding: 4,
                );
            }

            if (! $finding->created_by) {
                $finding->created_by = auth()->id() ?? 1;
            }
        });

        static::created(function (MaterialFinding $finding) {
            StockService::adjustStock(
                $finding->material_id,
                $finding->qty,
                'TEMUAN MATERIAL',
                $finding->document_number,
                $finding->note,
                $finding->created_by
            );
        });

        // Penjagaan yang sama untuk penghapusan: diperiksa sebelum barisnya
        // hilang, bukan sesudah.
        static::deleting(fn () => \App\Services\MaterialStockFreezeService::check());

        static::deleted(function (MaterialFinding $finding) {
            StockService::adjustStock(
                $finding->material_id,
                -$finding->qty,
                'PEMBATALAN TEMUAN MATERIAL',
                $finding->document_number,
                'Menghapus dokumen temuan',
                auth()->id() ?? $finding->created_by
            );
        });
    }
}
