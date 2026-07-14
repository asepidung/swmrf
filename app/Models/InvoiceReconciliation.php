<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReconciliation extends Model
{
    // Point to the MySQL view
    protected $table = 'invoice_reconciliation_view';

    // The view uses string IDs (e.g., 'item_1', 'charge_1')
    public $incrementing = false;
    protected $keyType = 'string';

    // Disable timestamps since views don't usually support saving/updating directly
    public $timestamps = false;

    protected $casts = [
        'weight' => 'float',
        'price' => 'float',
        'discount_percent' => 'float',
        'discount_rp' => 'float',
        'amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
