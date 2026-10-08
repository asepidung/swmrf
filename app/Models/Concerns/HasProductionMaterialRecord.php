<?php

namespace App\Models\Concerns;

use App\Models\Material;
use App\Models\ProductionBomSnapshot;
use App\Services\BomUsageCalculator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Catatan pemakaian bahan sebuah produksi (Boning/Repack), issue #509.
 *
 * Satu rumah untuk dua model: drylog yang WAJIB diisi sebelum dokumen bisa
 * dikunci, dan snapshot hitungan BOM yang dibekukan saat dikunci.
 *
 * Tidak satu pun dari ini menyentuh stok material: stok tetap dikeluarkan
 * lewat jalur manual (Material Usage > Create Manual Usage).
 */
trait HasProductionMaterialRecord
{
    /** Label (satu baris = satu box) yang dihitung BOM-nya. */
    abstract public function bomLabels(): Collection;

    public function drylogMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'drylog_material_id');
    }

    public function bomSnapshots(): MorphMany
    {
        return $this->morphMany(ProductionBomSnapshot::class, 'snapshotable');
    }

    /**
     * Drylog sudah diisi. NULL berarti belum; 0 berarti sudah, hasilnya nol.
     */
    public function drylogWasFilled(): bool
    {
        return $this->drylog_material_id !== null && $this->drylog_qty !== null;
    }

    /** Dilempar dari `lock()` bila drylog belum diisi. */
    protected function refuseToLockWithoutDrylog(): void
    {
        if (! $this->drylogWasFilled()) {
            throw new \RuntimeException(__('Fill in the drylog on the Material Usage page before locking. Zero is allowed.'));
        }
    }

    /** Membekukan hitungan BOM saat ini; menggantikan snapshot lama bila ada. */
    protected function freezeBomUsage(): void
    {
        $this->bomSnapshots()->delete();

        foreach (BomUsageCalculator::calculate($this->bomLabels())['usage'] as $materialId => $qty) {
            $this->bomSnapshots()->create(['material_id' => $materialId, 'qty' => (int) $qty]);
        }
    }

    protected function releaseBomUsage(): void
    {
        $this->bomSnapshots()->delete();
    }
}
