<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $receiving_number
 * @property int $logistic_purchase_order_id
 * @property int $supplier_id
 * @property string $receive_date
 * @property string|null $sj_number Nomor Surat Jalan
 * @property string|null $note
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LogisticReceivingItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\LogisticPurchaseOrder $purchaseOrder
 * @property-read \App\Models\Supplier $supplier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereLogisticPurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereReceiveDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereReceivingNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereSjNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceiving withoutTrashed()
 * @mixin \Eloquent
 */
class LogisticReceiving extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receiving_number',
        'logistic_purchase_order_id',
        'supplier_id',
        'receive_date',
        'sj_number',
        'note',
        'created_by'
    ];

    /* Relasi ke PO Logistic */
    public function purchaseOrder()
    {
        return $this->belongsTo(LogisticPurchaseOrder::class, 'logistic_purchase_order_id');
    }

    /* Relasi ke Supplier */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /* Relasi ke User yang input */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* Relasi ke Detail Item */
    public function items()
    {
        return $this->hasMany(LogisticReceivingItem::class);
    }
}
