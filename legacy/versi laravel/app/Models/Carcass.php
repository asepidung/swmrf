<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carcass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'carcass_no',
        'cattle_weighing_id',
        'kill_date',
        'note',
        'created_by'
    ];

    // ==========================================
    // JAMU SAKTI: CASCADING DELETE
    // ==========================================
    protected static function booted()
    {
        // Pas Header Carcass dihapus (Soft Delete)
        static::deleting(function ($carcass) {
            if ($carcass->isForceDeleting()) {
                // Kalau hapus permanen, detailnya juga hapus permanen
                $carcass->items()->forceDelete();
            } else {
                // Kalau soft delete, detailnya juga ikut soft delete
                $carcass->items()->delete();
            }
        });

        // Pas Header Carcass di-restore (dikembalikan dari sampah)
        static::restoring(function ($carcass) {
            $carcass->items()->restore();
        });
    }

    public function weighing(): BelongsTo
    {
        return $this->belongsTo(CattleWeighing::class, 'cattle_weighing_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarcassItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
