<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class MaterialAdjustment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'doc_no',
        'adjustment_date',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (empty($model->doc_no)) {
                $currentYear = date('Y');
                $model->doc_no = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'doc_no',
                    prefix: 'MA#'.date('y'),
                    padding: 3,
                );
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materialUsages(): MorphMany
    {
        return $this->morphMany(MaterialUsage::class, 'usageable');
    }
}
