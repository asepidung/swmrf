<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CattleWeighingItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'cattle_weighing_id',
        'cattle_receiving_item_id',
        'actual_weight',
        'notes'
    ];

    protected $appends = [
        'eartag',
        'initial_weight',
        'cattle_class_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function weighing(): BelongsTo
    {
        return $this->belongsTo(CattleWeighing::class, 'cattle_weighing_id');
    }

    public function receivingItem(): BelongsTo
    {
        return $this->belongsTo(CattleReceivingItem::class, 'cattle_receiving_item_id');
    }

    public function getEartagAttribute()
    {
        return optional($this->receivingItem)->eartag;
    }

    public function getInitialWeightAttribute()
    {
        return optional($this->receivingItem)->initial_weight;
    }

    public function getCattleClassIdAttribute()
    {
        return optional($this->receivingItem)->cattle_class_id;
    }
}
