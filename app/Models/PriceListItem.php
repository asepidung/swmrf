<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PriceListItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'price_list_id',
        'product_id',
        'price',
        'note',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['priceList.id', 'product.name', 'price', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
