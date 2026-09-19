<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExpenseCategory extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if (! empty($category->name)) {
                $category->name = strtoupper($category->name);
            }
        });

        static::deleting(function (self $category) {
            if ($category->expenses()->exists()) {
                throw new \Exception(__('This expense category cannot be deleted because it is still used by existing expenses.'));
            }
        });
    }
}
