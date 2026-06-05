<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseCattleItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_cattle_id',
        'cattle_class_id',
        'qty',
        'price',
        'item_notes',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'integer',
        'price' => 'integer',
    ];

    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
    }

    public function purchaseCattle(): BelongsTo
    {
        return $this->belongsTo(PurchaseCattle::class);
    }

    public function cattleClass(): BelongsTo
    {
        return $this->belongsTo(CattleClass::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
