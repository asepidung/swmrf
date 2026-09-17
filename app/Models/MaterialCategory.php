<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        // Kategori yang masih dipakai TIDAK BOLEH ikut menghapus
        // Material-nya diam-diam. FK-nya sendiri sekarang restrict (lihat
        // migrasi 2026_09_17_180000), tapi guard ini yang menegakkannya di
        // level Eloquent -- berlaku di semua mesin basis data (termasuk
        // SQLite saat test) dan memberi pesan ramah SEBELUM permintaan
        // sampai ke database sama sekali.
        static::deleting(function (self $category) {
            if ($category->materials()->exists()) {
                throw new \Exception(__('This material category cannot be deleted because it is still used by existing materials.'));
            }
        });
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'material_category_id');
    }
}
