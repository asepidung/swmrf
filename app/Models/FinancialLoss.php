<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialLoss extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lossable_type',
        'lossable_id',
        'date',
        'transaction_type',
        'reference_number',
        'amount',
        'quantity',
        'unit',
        'note'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'quantity' => 'decimal:2',
    ];

    /**
     * Kerugian yang JUMLAHNYA diketahui tetapi RUPIAHNYA belum.
     *
     * Susut kirim tahu persis berapa kilogram yang hilang, tetapi menilainya
     * butuh HPP -- dan HPP menunggu B.O.M. Sampai itu tiba, `amount` tetap nol
     * sementara `quantity` sudah terisi. Ini yang membedakannya dari kerugian
     * yang memang bernilai nol.
     */
    public function isNotPricedYet(): bool
    {
        return (float) $this->amount <= 0 && (float) $this->quantity > 0;
    }

    
    public function lossable(): MorphTo
    {
        return $this->morphTo();
    }
}
