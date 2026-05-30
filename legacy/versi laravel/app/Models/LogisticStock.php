<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $logistic_item_id
 * @property int $qty
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LogisticItem $item
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock whereLogisticItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStock whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogisticStock extends Model
{
    use HasFactory;

    protected $fillable = ['logistic_item_id', 'qty'];

    public function item()
    {
        return $this->belongsTo(LogisticItem::class, 'logistic_item_id');
    }
}
