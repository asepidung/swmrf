<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * (PHPDoc block lu udah bener, biarkan saja atau generate ulang pakai ide-helper nanti)
 */
class CattlePurchaseOrder extends Model
{
    use HasFactory, SoftDeletes; // <-- Udah mantap pakai SoftDeletes

    protected $fillable = [
        'po_number',
        'supplier_id',
        'po_date',
        'note',
        'created_by'
    ];

    public function setNoteAttribute($value)
    {
        $this->attributes['note'] = strtoupper($value);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi balik ke Penerimaan Sapi (Cattle Receiving)
     */
    public function receivings(): HasMany
    {
        return $this->hasMany(CattleReceiving::class, 'cattle_purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CattlePurchaseOrderItem::class, 'cattle_purchase_order_id');
    }
}
