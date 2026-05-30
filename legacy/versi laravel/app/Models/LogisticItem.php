<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Tambahin ini biar pro

/**
 * @property int $id
 * @property int $logistic_category_id
 * @property int $unit_id
 * @property string $code
 * @property string $name
 * @property bool $show_in_stock
 * @property int $min_stock
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LogisticCategory $logisticCategory
 * @property-read \App\Models\Unit $unit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereLogisticCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereMinStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereShowInStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogisticItem extends Model
{
    protected $fillable = [
        'logistic_category_id',
        'unit_id',
        'code',
        'name',
        'show_in_stock',
        'min_stock',
        'is_active',
    ];

    protected $casts = [
        'show_in_stock' => 'boolean',
        'is_active' => 'boolean',
        'min_stock' => 'integer',
    ];

    // Nama fungsi ini harus 'logisticCategory' (camelCase)
    public function logisticCategory(): BelongsTo
    {
        return $this->belongsTo(LogisticCategory::class);
    }

    // Nama fungsi ini harus 'unit' (huruf kecil semua)
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
