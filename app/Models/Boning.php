<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Boning extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'bonings';

    protected $fillable = ['doc_no', 'boning_date', 'status', 'kunci', 'note', 'created_by'];

    protected $casts = [
        'boning_date' => 'date',
        'kunci' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (empty($model->doc_no)) {
                /*
                 * Dulu nomornya dihitung dari COUNT baris, bukan dari nomor
                 * terakhir -- satu-satunya generator di aplikasi ini yang
                 * begitu.
                 *
                 * Akibatnya nomor bisa TERULANG: satu dokumen yang dihapus
                 * permanen membuat hitungan turun, dan dokumen berikutnya
                 * memakai nomor yang sudah dipakai, langsung menabrak unique
                 * index dengan error yang tidak menjelaskan apa-apa. Tidak ada
                 * pula penguncian, sehingga dua penyimpanan bersamaan
                 * mendapat hitungan yang sama.
                 */
                $model->doc_no = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'doc_no',
                    prefix: 'BN'.date('y'),
                    padding: 3,
                );
            }

            if (empty($model->status)) {
                $model->status = 'OPEN';
            }
        });
    }

    public function carcasses(): HasMany
    {
        return $this->hasMany(BoningCarcass::class, 'boning_id');
    }

    public function materialUsages(): MorphMany
    {
        return $this->morphMany(MaterialUsage::class, 'usageable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoningItem::class, 'boning_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
