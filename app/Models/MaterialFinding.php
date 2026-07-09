<?php

namespace App\Models;

use App\Services\StockService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MaterialFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'date',
        'material_id',
        'qty',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        static::creating(function (MaterialFinding $finding) {
            if (!$finding->document_number) {
                // Generate Document Number
                $datePrefix = Carbon::parse($finding->date ?? now())->format('ymd');
                $lastDoc = self::whereDate('date', $finding->date ?? now())
                    ->orderBy('id', 'desc')
                    ->first();

                $lastNumber = 0;
                if ($lastDoc && preg_match('/FND-MTR-\d{6}-(\d{4})/', $lastDoc->document_number, $matches)) {
                    $lastNumber = (int) $matches[1];
                }

                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $finding->document_number = "FND-MTR-{$datePrefix}-{$newNumber}";
            }

            if (!$finding->created_by) {
                $finding->created_by = auth()->id() ?? 1;
            }
        });

        static::created(function (MaterialFinding $finding) {
            StockService::adjustStock(
                $finding->material_id,
                $finding->qty,
                'TEMUAN MATERIAL',
                $finding->document_number,
                $finding->note,
                $finding->created_by
            );
        });

        static::deleted(function (MaterialFinding $finding) {
            StockService::adjustStock(
                $finding->material_id,
                -$finding->qty,
                'PEMBATALAN TEMUAN MATERIAL',
                $finding->document_number,
                'Menghapus dokumen temuan',
                auth()->id() ?? $finding->created_by
            );
        });
    }
}
