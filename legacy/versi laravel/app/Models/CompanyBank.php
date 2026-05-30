<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyBank extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Tampilan di dropdown pembayaran
    public function getFullAccountAttribute()
    {
        if ($this->account_number) {
            // Bakal tampil: "BCA PT - 7115534882 (PT. SANTI WIJAYA MEAT)"
            return "{$this->initial}";
        }

        // Kalau kas tunai
        return $this->initial ?? $this->bank_name;
    }
}
