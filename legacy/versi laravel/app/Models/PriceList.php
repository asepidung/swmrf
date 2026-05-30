<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_group_id',
        'created_by',
    ];

    /**
     * Relasi ke entitas grup customer.
     */
    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Relasi ke entitas detail item daftar harga.
     */
    public function items()
    {
        return $this->hasMany(PriceListItem::class);
    }

    /**
     * Relasi ke entitas pengguna.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
