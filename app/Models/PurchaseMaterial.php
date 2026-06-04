<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function materialRequisition()
    {
        return $this->belongsTo(MaterialRequisition::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseMaterialItem::class);
    }
}
