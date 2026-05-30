<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankLedger extends Model
{
    use HasFactory;

    // Buka pintu gerbang buat semua kolom
    protected $guarded = [];

    /**
     * Relasi balik ke Bank
     */
    public function companyBank(): BelongsTo
    {
        return $this->belongsTo(CompanyBank::class);
    }

    /**
     * Relasi Polymorphic (Bisa nyambung ke Cicilan AP atau Sales nanti)
     */
    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
