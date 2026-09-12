<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'vehicle_type',
        'police_number',
        'is_active',
    ];
}
