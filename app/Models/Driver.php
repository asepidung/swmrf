<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    /** Nama supir selalu huruf besar (Owner, 22 Oktober 2026). */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = strtoupper(trim((string) $value));
    }
}
