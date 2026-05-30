<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tally_sheet_id',
        'barcode',
        'product_id',
        'actual_weight',
        'grade_id',
        'warehouse_id',
        'qty_pcs',
        'ph_level',
        'pack_date',
        'exp_date',
        'origin',
    ];

    // Relasi ke tabel header tally_sheets
    public function tallySheet(): BelongsTo
    {
        return $this->belongsTo(TallySheet::class, 'tally_sheet_id');
    }

    // Relasi ke tabel products
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Relasi ke tabel grades
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }
}
