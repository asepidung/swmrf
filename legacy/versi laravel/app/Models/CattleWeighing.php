<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CattleWeighing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cattle_receiving_id',
        'weigh_no',
        'weigh_date',
        'note',
        'created_by'
    ];

    // Relasi ke tabel Penerimaan (GRC)
    public function receiving(): BelongsTo
    {
        return $this->belongsTo(CattleReceiving::class, 'cattle_receiving_id');
    }

    // Relasi ke Detail Timbangan
    public function items(): HasMany
    {
        return $this->hasMany(CattleWeighingItem::class, 'cattle_weighing_id');
    }

    // Relasi ke User yang nimbang
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // KUNCI GEMBOK: Relasi ke tabel Karkas
    public function carcasses(): HasMany
    {
        return $this->hasMany(Carcass::class, 'cattle_weighing_id');
    }
}
