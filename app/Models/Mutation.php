<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mutation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mutation_number',
        'mutation_date',
        'from_warehouse_id',
        'to_warehouse_id',
        'note',
        'status',
        'created_by',
        'received_by',
    ];

    protected $casts = [
        'mutation_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->mutation_number)) {
                $year = date('y');
                $prefix = 'MT#' . $year;
                $latest = self::where('mutation_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->first();
                $counter = 1;
                if ($latest) {
                    $latestCounter = (int) substr($latest->mutation_number, -3);
                    $counter = $latestCounter + 1;
                }
                $model->mutation_number = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MutationItem::class);
    }
}
