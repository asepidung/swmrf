<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $logistic_requisition_id
 * @property int $logistic_item_id
 * @property numeric $qty
 * @property numeric $price
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LogisticItem $item
 * @property-read \App\Models\LogisticRequisition $requisition
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem whereLogisticItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem whereLogisticRequisitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisitionItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogisticRequisitionItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'logistic_requisition_id',
        'logistic_item_id',
        'qty',
        'price',
        'note',
    ];

    /**
     * Get the requisition that owns the item.
     */
    public function requisition()
    {
        return $this->belongsTo(LogisticRequisition::class, 'logistic_requisition_id');
    }

    /**
     * Get the logistic item associated with the record.
     */
    public function item()
    {
        return $this->belongsTo(LogisticItem::class, 'logistic_item_id');
    }
}
