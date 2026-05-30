<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $po_number
 * @property int $beef_requisition_id
 * @property int $supplier_id
 * @property int $approved_by
 * @property string $po_date
 * @property numeric $total_amount
 * @property string|null $note
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $approver
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BeefPurchaseOrderItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\BeefRequisition $requisition
 * @property-read \App\Models\Supplier $supplier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereBeefRequisitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder wherePoDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder wherePoNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefPurchaseOrder whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BeefPurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'beef_requisition_id',
        'supplier_id',
        'approved_by',
        'po_date',
        'total_amount',
        'status', // <-- WAJIB MASUK BIAR BISA UPDATE STATUS GR
        'note',
    ];

    /* Relasi ke Request Beef */
    public function requisition()
    {
        return $this->belongsTo(BeefRequisition::class, 'beef_requisition_id');
    }

    /* Relasi ke Supplier */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /* Relasi ke User Finance yang menyetujui */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* Relasi ke item PO */
    public function items()
    {
        return $this->hasMany(BeefPurchaseOrderItem::class);
    }

    /* --- TAMBAHAN RELASI UNTUK GR & FINANCE --- */

    /* Relasi ke histori GR (Barang Masuk) */
    public function receivings()
    {
        return $this->hasMany(BeefReceiving::class, 'beef_purchase_order_id');
    }

    /* Relasi Polymorphic ke Account Payable (Hutang Finance) */
    public function payables()
    {
        return $this->morphMany(AccountPayable::class, 'payable');
    }
}
