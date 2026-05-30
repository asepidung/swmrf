<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $beef_requisition_id
 * @property int $product_id
 * @property numeric $qty
 * @property numeric $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\BeefRequisition|null $requisition
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem whereBeefRequisitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisitionItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BeefRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'beef_requisition_id',
        'product_id',
        'qty',
        'price',
    ];

    public function requisition()
    {
        return $this->belongsTo(BeefRequisition::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
