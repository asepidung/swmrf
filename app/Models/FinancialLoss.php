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
        'note'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    
    public function lossable(): MorphTo
    {
        return $this->morphTo();
    }
}
