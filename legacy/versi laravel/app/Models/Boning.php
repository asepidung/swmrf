<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Boning extends Model
{
    use SoftDeletes;

    protected $fillable = ['doc_no', 'boning_date', 'status', 'note', 'created_by'];

    // Relasi ke Input (Karkas)
    public function carcasses(): HasMany
    {
        return $this->hasMany(BoningCarcass::class);
    }

    // Relasi ke Output (Daging/Label)
    public function items(): HasMany
    {
        return $this->hasMany(BoningItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
