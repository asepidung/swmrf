<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CattleWeighingItem extends Model
{
    use HasFactory;

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
        'reference_weight',
    ];

    
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

    /**
     * Bobot sapi ini sebagai acuan pemeriksaan di modul sesudahnya.
     *
     * Berat hasil penimbangan bila memang ditimbang; berat saat PENERIMAAN
     * bila tidak. Catatan Project Owner: kadang ada proses yang tidak melewati
     * penimbangan, dan dalam kasus itu satu-satunya bobot yang pernah tercatat
     * adalah yang diisi saat sapi datang.
     *
     * Dipakai Carcass untuk memastikan total potongan tidak melebihi bobot
     * sapinya sendiri.
     */
    public function getReferenceWeightAttribute(): float
    {
        $actual = (float) ($this->actual_weight ?? 0);

        return $actual > 0
            ? $actual
            : (float) (optional($this->receivingItem)->initial_weight ?? 0);
    }

    public function getInitialWeightAttribute()
    {
        return optional($this->receivingItem)->initial_weight;
    }

    public function getCattleClassIdAttribute()
    {
        return optional($this->receivingItem)->cattle_class_id;
    }

    public function carcassItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CarcassItem::class, 'cattle_weighing_item_id');
    }
}
