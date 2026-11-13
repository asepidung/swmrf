<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Grade extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'grades';

    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Tampilan satu huruf di layar scan/hasil barcode (Owner, 13 Nov 2026): CHILL=C, FROZEN=F, A, B, R. */
    public static function initial(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? '' : mb_strtoupper(mb_substr($name, 0, 1));
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper($value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
