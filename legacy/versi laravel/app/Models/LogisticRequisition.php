<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB; // Tambahkan ini

/**
 * @property int $id
 * @property string $document_number
 * @property int $user_id
 * @property int|null $supplier_id
 * @property string $due_date
 * @property string|null $note
 * @property string|null $reject_note
 * @property string|null $terms_of_payment
 * @property string $tax_type
 * @property numeric $tax_amount
 * @property numeric $total_amount
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LogisticRequisitionItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Supplier|null $supplier
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereDocumentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereRejectNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereTaxType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereTermsOfPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticRequisition withoutTrashed()
 * @mixin \Eloquent
 */
class LogisticRequisition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_number',
        'user_id',
        'supplier_id',
        'due_date',
        'note',
        'terms_of_payment',
        'tax_type',
        'tax_amount',
        'total_amount',
        'status',
        'reject_note'
    ];

    /* LOGIKA AUTO-GENERATE NOMOR REQUEST (AMAN DARI BENTROK) */
    protected static function booted()
    {
        static::creating(function ($model) {
            DB::transaction(function () use ($model) {
                $currentYear = date('Y');

                // Hitung jumlah data tahun ini dan kunci row agar tidak bentrok
                $count = DB::table('logistic_requisitions')
                    ->whereYear('created_at', $currentYear)
                    ->lockForUpdate()
                    ->count();

                $nextNumber = $count + 1;
                $requestNumber = sprintf("%03s", $nextNumber);

                // Masukkan langsung ke model sebelum di-save ke database
                $model->document_number = "LRQ#" . substr($currentYear, 2) . $requestNumber;
            });
        });
    }

    public function items()
    {
        return $this->hasMany(LogisticRequisitionItem::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
