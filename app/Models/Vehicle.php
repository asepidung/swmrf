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

    /** Jenis dan plat kendaraan selalu huruf besar (Owner, 22 Oktober 2026). */
    public function setVehicleTypeAttribute($value): void
    {
        $this->attributes['vehicle_type'] = strtoupper(trim((string) $value));
    }

    public function setPoliceNumberAttribute($value): void
    {
        $this->attributes['police_number'] = strtoupper(trim((string) $value));
    }
}
