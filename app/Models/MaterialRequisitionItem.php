<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialRequisitionItem extends Model
{
    

    protected $fillable = [
        'material_requisition_id',
        'material_id',
        'qty',
        'price',
        'subtotal',
        'note',
    ];

    
    public function requisition()
    {
        return $this->belongsTo(MaterialRequisition::class, 'material_requisition_id');
    }

    public function item() // Note: use 'item' to match Filament standard for relation or 'material'
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
    
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->subtotal = (float)$item->qty * (float)$item->price;
        });

        static::saved(function ($item) {
            $item->requisition->updateTotalAmount();
        });

        static::deleted(function ($item) {
            $item->requisition->updateTotalAmount();
        });
    }
}
