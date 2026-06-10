<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoningCarcass extends Model
{
    protected $table = 'boning_carcasses';

    protected $fillable = [
        'boning_id',
        'carcass_id'
    ];

    public function boning(): BelongsTo
    {
        return $this->belongsTo(Boning::class, 'boning_id');
    }

    public function carcass(): BelongsTo
    {
        return $this->belongsTo(Carcass::class, 'carcass_id');
    }
}
