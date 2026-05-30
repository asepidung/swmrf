<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- Tambahin ini

/**
 * @property int $id
 * @property int $cattle_purchase_order_id
 * @property int $cattle_category_id
 * @property int $qty_head
 * @property numeric $price_per_kg
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\CattleCategory $cattleCategory
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereCattleCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereCattlePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem wherePricePerKg($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereQtyHead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattlePurchaseOrderItem withoutTrashed()
 * @mixin \Eloquent
 */
class CattlePurchaseOrderItem extends Model
{
    use HasFactory, SoftDeletes; // <-- Panggil di sini

    protected $fillable = [
        'cattle_purchase_order_id',
        'cattle_category_id',
        'qty_head',
        'price_per_kg',
        'note'
    ];

    public function setNoteAttribute($value)
    {
        $this->attributes['note'] = strtoupper($value);
    }

    public function cattleCategory()
    {
        return $this->belongsTo(CattleCategory::class);
    }
}
