<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CattleReceiving extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receiving_number',
        'cattle_purchase_order_id',
        'supplier_id',
        'receive_date',
        'doc_no',
        'sv_ok',
        'skkh_ok',
        'note',
        'created_by'
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(CattlePurchaseOrder::class, 'cattle_purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CattleReceivingItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function weighing(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CattleWeighing::class, 'cattle_receiving_id');
    }
}
