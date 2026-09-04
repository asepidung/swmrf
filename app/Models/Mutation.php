<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Mutation extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Mutasi has been {$eventName}");
    }

    protected $fillable = [
        'mutation_number',
        'mutation_date',
        'from_warehouse_id',
        'to_warehouse_id',
        'note',
        'status',
        'created_by',
        'received_by',
    ];

    protected $casts = [
        'mutation_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }

            if (empty($model->mutation_number)) {
                // withTrashed(): mutasi yang DIHAPUS tetap memegang nomornya.
                //
                // Tanpa ini, dokumen yang dihapus lunak menjadi tak terlihat
                // oleh penomoran, dan mutasi berikutnya memakai nomor yang
                // sama. Dua dokumen bernomor sama, tanpa satu pun gejala
                // sampai seseorang mencari MT#26001 dan menemukan dua.
                //
                // Dari enam belas model yang memakai `DocumentNumber`, hanya
                // yang ini tertinggal. Dijaga `DocumentNumberingTest`.
                $model->mutation_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'mutation_number',
                    prefix: 'MT#'.date('y'),
                    padding: 3,
                );
            }
        });
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MutationItem::class);
    }
}
