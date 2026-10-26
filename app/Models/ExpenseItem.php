<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu barang dalam pengajuan Expense: kategori, keterangan yang diketik, dan total harganya. */
class ExpenseItem extends Model
{
    protected $fillable = ['expense_id', 'expense_category_id', 'description', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
