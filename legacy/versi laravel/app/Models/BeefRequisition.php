<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $document_number
 * @property int $user_id
 * @property int $supplier_id
 * @property string $due_date
 * @property numeric $total_amount
 * @property string|null $note
 * @property string $status
 * @property string|null $reject_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BeefRequisitionItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Supplier $supplier
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereDocumentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereRejectNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BeefRequisition whereUserId($value)
 * @mixin \Eloquent
 */
class BeefRequisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'user_id',
        'supplier_id',
        'due_date',
        'total_amount',
        'note',
        'status',
        'reject_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(BeefRequisitionItem::class);
    }
}
