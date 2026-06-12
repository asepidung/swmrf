<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerGroup extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'head_office_address',
        'head_office_pic',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function priceList()
    {
        return $this->hasOne(PriceList::class);
    }
}
