<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Angka dan pilihan yang DITENTUKAN MANUSIA, bukan ditulis di kode.
 *
 * Dipakai untuk aturan yang mengikat seluruh perusahaan dan sesekali perlu
 * disesuaikan -- yang pertama: ambang susut wajar Repack.
 *
 * Nilainya selalu teks. Yang membacanya yang tahu ia sedang meminta angka atau
 * kalimat, dan mengubahnya di sana. Menyimpan tipe data di tabel ini berarti
 * membuat lapisan yang menebak, dan tebakan itu akan salah pada nilai pertama
 * yang bentuknya di luar dugaan.
 */
class Setting extends Model
{
    /** Ambang susut wajar sebuah dokumen Repack, dalam persen. */
    public const REPACK_MAX_SHRINK_PERCENT = 'repack.max_shrink_percent';

    /** Ambang susut wajar satu batch Boning, dalam persen. */
    public const BONING_MAX_SHRINK_PERCENT = 'boning.max_shrink_percent';

    protected $fillable = ['key', 'value', 'updated_by'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Baca satu setelan.
     *
     * Mengembalikan `null` kalau belum pernah disetel, dan itu ARTI TERSENDIRI:
     * "belum ada manusia yang memilih angkanya". Pemanggilnya yang memutuskan
     * apa yang terjadi pada keadaan itu -- pada ambang susut Repack, jawabannya
     * gerbangnya tidak menyala sama sekali.
     */
    public static function read(string $key): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return ($value === null || $value === '') ? null : $value;
    }

    /** Baca sebagai angka; `null` tetap berarti belum disetel. */
    public static function number(string $key): ?float
    {
        $value = static::read($key);

        return $value === null ? null : (float) str_replace(',', '.', $value);
    }

    public static function write(string $key, mixed $value, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'updated_by' => $userId ?? auth()->id()],
        );
    }
}
