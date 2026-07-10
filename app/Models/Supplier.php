<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'address',
        'pic',
        'phone',
        'top_days',
        'is_tax_11',
        'is_active',
        'supplied_goods',
        'bank_name',
        'account_number',
        'account_name',
    ];
}
