<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $logistic_item_id
 * @property string $transaction_type GR, ISSUE, RETUR, dll
 * @property string|null $reference_document Nomor GR / Dokumen
 * @property int $qty_in
 * @property int $qty_out
 * @property int $balance Sisa stok akhir saat transaksi ini terjadi
 * @property string|null $note
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\LogisticItem $item
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereLogisticItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereQtyIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereQtyOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereReferenceDocument($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereTransactionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticStockMovement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogisticStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistic_item_id',
        'transaction_type',
        'reference_document',
        'qty_in',
        'qty_out',
        'balance',
        'note',
        'created_by',
    ];

    public function item()
    {
        return $this->belongsTo(LogisticItem::class, 'logistic_item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
