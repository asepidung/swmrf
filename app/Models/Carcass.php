<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carcass extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'carcasses';

    protected $fillable = [
        'carcass_number',
        'cattle_weighing_id',
        'kill_date',
        'note',
        'created_by'
    ];

    protected $casts = [
        'kill_date' => 'date',
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

            if (empty($model->carcass_number)) {
                $model->carcass_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'carcass_number',
                    prefix: 'CC#'.date('y'),
                    padding: 3,
                );
            }
        });

        // Cascading Delete
        static::deleting(function ($carcass) {
            if (\App\Models\BoningCarcass::where('carcass_id', $carcass->id)->exists()) {
                throw new \Exception(__('Cannot delete Carcass because it has already been processed into Boning.'));
            }

            if ($carcass->isForceDeleting()) {
                $carcass->items()->forceDelete();
            } else {
                $carcass->items()->delete();
            }
        });

        static::restoring(function ($carcass) {
            $carcass->items()->withTrashed()->restore();
        });
    }

    public function weighing(): BelongsTo
    {
        return $this->belongsTo(CattleWeighing::class, 'cattle_weighing_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarcassItem::class, 'carcass_id');
    }

    public function boningCarcasses(): HasMany
    {
        return $this->hasMany(BoningCarcass::class, 'carcass_id');
    }

    // =================================================================
    // Rendemen karkas
    // =================================================================

    /**
     * Berat hidup seluruh sapi di dokumen ini.
     *
     * Diambil dari berat TIMBANG ULANG, bukan berat surat jalan -- itu angka
     * yang dipakai perusahaan sebagai kebenaran, dan selisihnya terhadap surat
     * jalan sudah tercatat sendiri sebagai kerugian.
     */
    public function liveWeight(): float
    {
        return round((float) $this->items()
            ->join('cattle_weighing_items', 'carcass_items.cattle_weighing_item_id', '=', 'cattle_weighing_items.id')
            ->sum('cattle_weighing_items.actual_weight'), 2);
    }

    /** Berat karkasnya saja: belahan A ditambah belahan B, seluruh ekor. */
    public function carcassWeight(): float
    {
        return round((float) $this->items()->sum(\Illuminate\Support\Facades\DB::raw('carcass_1 + carcass_2')), 2);
    }

    public function hidesWeight(): float
    {
        return round((float) $this->items()->sum('hides'), 2);
    }

    public function tailWeight(): float
    {
        return round((float) $this->items()->sum('tail'), 2);
    }

    /**
     * Berat yang akan MASUK proses boning.
     *
     * Karkas ditambah buntut. Kulit dan jeroan tidak ikut -- keduanya dijual
     * langsung ke kontraktor dan tidak pernah masuk ruang boning. Keputusan
     * Project Owner, 4 September 2026, dan rumus yang sama persis ada di kode
     * aplikasi lama:
     *
     *     $offal = $totalCarcass + $totalTails;
     *
     * Perhatikan namanya di sana: `offal`. Itu KELIRU -- jumlah karkas dan
     * buntut bukan jeroan, melainkan bahan boning. Angka itu tercetak di
     * Carcas Report dengan label "Offal", dan ikut muncul di ringkasan boning
     * sebagai baris produk bernama OFFAL. Lihat catatan di `agents.md`.
     */
    public function boningInputWeight(): float
    {
        return round($this->carcassWeight() + $this->tailWeight(), 2);
    }

    /**
     * Rendemen karkas: berapa persen bobot hidup yang menjadi karkas.
     *
     * Angka baku di rumah potong, dan sudah ADA di aplikasi lama:
     *
     *     $yield = $totalCarcass / $totalLive * 100;
     *
     * Ia HILANG saat aplikasinya ditulis ulang. Diperiksa pada laporan
     * sungguhan tanggal 27 Agustus 2026: 3.943,32 / 6.856,00 = 57,52%, persis
     * angka yang tercetak di sana.
     *
     * `null` berarti belum bisa dihitung -- bukan nol persen.
     */
    public function yieldPercent(): ?float
    {
        $hidup = $this->liveWeight();

        if ($hidup <= 0) {
            return null;
        }

        return round(($this->carcassWeight() / $hidup) * 100, 2);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
