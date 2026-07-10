<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'customer_group_id',
        'customer_segment_id',
        'address',
        'top',
        'pic',
        'phone',
        'required_documents',
        'invoice_exchange',
        'is_taxable',
        'is_active',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'invoice_exchange' => 'boolean',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function segment()
    {
        return $this->belongsTo(CustomerSegment::class, 'customer_segment_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function receivables()
    {
        return $this->hasMany(Receivable::class, 'customer_id');
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }
}
