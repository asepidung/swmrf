<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoningCarcass extends Model
{
    protected $fillable = [
        'boning_id',
        'slaughter_id'
    ];

    public function boning(): BelongsTo
    {
        return $this->belongsTo(Boning::class);
    }

    // Nanti buka comment ini kalau nama Model karkas/slaughter lu udah fix
    /*
    public function slaughter(): BelongsTo
    {
        return $this->belongsTo(NamaModelSlaughterLu::class, 'slaughter_id');
    }
    */
}
