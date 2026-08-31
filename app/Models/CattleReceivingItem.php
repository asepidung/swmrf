<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CattleReceivingItem extends Model
{
    use HasFactory;

    protected $table = 'cattle_receiving_items';

    protected $fillable = [
        'cattle_receiving_id',
        'cattle_class_id',
        'eartag',
        'initial_weight',
        'notes',
    ];

    /**
     * Eartag dinormalkan di sini, bukan di form.
     *
     * Kolom eartag di form memakai `text-transform: uppercase`, yang hanya
     * mengubah TAMPILAN. Operator mengetik `a001`, layarnya menunjukkan
     * `A001`, dan yang terkirim ke server tetap `a001` -- perbedaan yang
     * tidak mungkin disadari siapa pun dari layar.
     *
     * Akibatnya perbandingan duplikat bergantung pada collation database:
     * MySQL case-insensitive sehingga duplikatnya tertangkap, SQLite
     * case-sensitive sehingga lolos. Perilaku berbeda antara server dan
     * testing adalah kondisi terburuk: test hijau untuk sesuatu yang tidak
     * berlaku di tempat yang sebenarnya.
     *
     * Ditaruh di model supaya berlaku dari jalur mana pun -- form, import,
     * seeder, maupun tinker.
     */
    public function setEartagAttribute($value): void
    {
        $this->attributes['eartag'] = strtoupper(trim((string) $value));
    }

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(CattleReceiving::class, 'cattle_receiving_id');
    }

    public function cattleClass(): BelongsTo
    {
        return $this->belongsTo(CattleClass::class, 'cattle_class_id');
    }
}
