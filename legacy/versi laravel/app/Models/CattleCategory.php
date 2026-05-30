<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CattlePurchaseOrderItem> $purchaseOrderItems
 * @property-read int|null $purchase_order_items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CattleCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CattleCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    // Umumnya kategori ini akan digunakan di detail PO Cattle nanti
    public function purchaseOrderItems()
    {
        return $this->hasMany(CattlePurchaseOrderItem::class);
    }
}
