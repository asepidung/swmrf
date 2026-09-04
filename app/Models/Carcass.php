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
     * Berat yang akan MASUK proses boning -- dan sekaligus berat OFFAL.
     *
     * Karkas ditambah buntut. Kulit tidak ikut; ia dijual langsung ke
     * kontraktor.
     *
     * Rumus yang sama ada di aplikasi lama dengan nama `$offal`:
     *
     *     $offal = $totalCarcass + $totalTails;
     *
     * Nama itu sempat dikira SALAH di catatan proyek ini -- karkas ditambah
     * buntut jelas bukan jeroan. Kekeliruannya ada pada yang menuduh, bukan
     * pada kodenya. Project Owner, 4 September 2026:
     *
     *   "ketika sapi di potong, beberapa bagian seperti jeroan hati, usus,
     *    kepala, kaki dan lain-lain itu dipisahkan menjadi 1 item offal nah
     *    untuk beratnya TIDAK DITIMBANG ... total berat karkas a dan karkas b
     *    ditambah buntut ITULAH yang jadi berat offal"
     *
     * Jadi jeroannya memang tidak pernah ditimbang, dan angka ini dipakai
     * sebagai beratnya menurut kesepakatan -- cara yang berlaku di rumah
     * potong modern. Ia BUKAN pengukuran, melainkan konvensi.
     *
     * Karena itu satu angka yang sama menjawab dua pertanyaan berbeda: berapa
     * yang masuk boning, dan berapa berat offal yang dijual. Keduanya memang
     * sama besar. Jangan "diperbaiki" menjadi dua rumus.
     */
    public function boningInputWeight(): float
    {
        return round($this->carcassWeight() + $this->tailWeight(), 2);
    }

    /**
     * Berat offal, menurut kesepakatan.
     *
     * Sengaja diberi nama sendiri walaupun angkanya sama persis dengan
     * `boningInputWeight()`. Yang membacanya sedang menanyakan hal yang
     * berbeda, dan kalau kelak kesepakatannya berubah, hanya satu di antara
     * keduanya yang ikut berubah.
     */
    public function offalWeight(): float
    {
        return $this->boningInputWeight();
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
