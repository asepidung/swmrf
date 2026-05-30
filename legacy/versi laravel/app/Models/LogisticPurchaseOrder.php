<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $po_number
 * @property int $logistic_requisition_id
 * @property int $supplier_id
 * @property int $approved_by
 * @property string $status
 * @property \Illuminate\Support\Carbon $po_date
 * @property float $total_amount
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $approver
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LogisticPurchaseOrderItem> $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LogisticReceiving> $receivings
 * @property-read int|null $receivings_count
 * @property-read \App\Models\LogisticRequisition $requisition
 * @property-read \App\Models\Supplier $supplier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereLogisticRequisitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder wherePoDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder wherePoNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticPurchaseOrder whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogisticPurchaseOrder extends Model
{
    // Izinkan semua kolom diisi otomatis oleh sistem
    protected $guarded = [];

    /**
     * Konversi tipe data otomatis
     */
    protected $casts = [
        'po_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relasi ke permohonan asalnya
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(LogisticRequisition::class, 'logistic_requisition_id');
    }

    /**
     * Relasi ke Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Siapa Finance yang melakukan approval
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Daftar item yang dibeli
     */
    public function items(): HasMany
    {
        return $this->hasMany(LogisticPurchaseOrderItem::class, 'logistic_purchase_order_id');
    }

    /**
     * Relasi ke penerimaan barang (GR / Logistic Receiving)
     * Ini yang tadi bikin error karena nggak ada jalannya!
     */
    public function receivings(): HasMany
    {
        return $this->hasMany(LogisticReceiving::class, 'logistic_purchase_order_id');
    }
}
