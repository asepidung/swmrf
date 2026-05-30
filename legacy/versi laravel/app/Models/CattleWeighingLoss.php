<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CattleWeighingLoss extends Model
{
    protected $fillable = [
        'cattle_weighing_id',
        'loss_number',
        'loss_date',
        'total_receive_weight',
        'total_actual_weight',
        'total_loss_weight',
        'total_loss_cost',
        'note',
        'created_by'
    ];

    public function weighing(): BelongsTo
    {
        return $this->belongsTo(CattleWeighing::class, 'cattle_weighing_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CattleWeighingLossItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Tembakan ke tabel Terminal Keuangan (Polymorphic)
    public function financialLoss(): MorphOne
    {
        return $this->morphOne(FinancialLoss::class, 'lossable');
    }
}
