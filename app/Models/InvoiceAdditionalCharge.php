<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAdditionalCharge extends Model
{
    protected $table = 'invoice_additional_charges';

    protected $fillable = [
        'invoice_id',
        'name',
        'qty',
        'price',
        'discount_percent',
        'discount_rp',
        'amount',
    ];

    protected $casts = [
        'qty' => 'float',
        'price' => 'float',
        'discount_percent' => 'float',
        'discount_rp' => 'float',
        'amount' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
