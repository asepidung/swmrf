<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $logistic_receiving_id
 * @property int $logistic_item_id
 * @property int $qty_received
 * @property numeric $price Harga satuan saat PO
 * @property numeric $subtotal qty_received * price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\LogisticItem $item
 * @property-read \App\Models\LogisticReceiving $receiving
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereLogisticItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereLogisticReceivingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereQtyReceived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticReceivingItem withoutTrashed()
 * @mixin \Eloquent
 */
class LogisticReceivingItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'logistic_receiving_id',
        'logistic_item_id',
        'qty_received',
        'price',
        'subtotal'
    ];

    public function receiving()
    {
        return $this->belongsTo(LogisticReceiving::class, 'logistic_receiving_id');
    }

    /* Relasi ini nembak ke tabel logistic_items lu */
    public function item()
    {
        return $this->belongsTo(LogisticItem::class, 'logistic_item_id');
    }
}
