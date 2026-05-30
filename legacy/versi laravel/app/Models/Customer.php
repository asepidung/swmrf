<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_group_id
 * @property int $segment_id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property int $top_days
 * @property int $req_invoice
 * @property int $req_joss
 * @property int $req_nkv
 * @property int $req_phd
 * @property int $req_halal
 * @property int $req_uji_lab
 * @property int $req_sv
 * @property int $req_po
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CustomerGroup $customerGroup
 * @property-read \App\Models\Segment $segment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCustomerGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqHalal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqInvoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqJoss($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqNkv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqPhd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqPo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqSv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereReqUjiLab($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereSegmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereTopDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_group_id',
        'segment_id',
        'name',
        'address',
        'phone',
        'top_days',
        'req_po',       // Pastikan 8 dokumen ini masuk semua!
        'req_invoice',
        'req_halal',
        'req_uji_lab',
        'req_nkv',
        'req_sv',
        'req_phd',
        'req_joss',
        'is_active',
    ];

    // Penting: Biar Laravel otomatis ubah JSON jadi Array PHP
    protected $casts = [
        'document_requirements' => 'array',
        'is_tukar_faktur' => 'boolean',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
