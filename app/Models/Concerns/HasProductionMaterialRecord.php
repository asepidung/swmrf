<?php

namespace App\Models\Concerns;

use App\Models\FinancialLoss;
use App\Models\Material;
use App\Models\ProductionMaterialWaste;
use App\Models\ProductionBomSnapshot;
use App\Services\BomUsageCalculator;
use App\Services\DrylogMaterial;
use App\Services\MaterialUnitPrice;
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

    public function bomSnapshots(): MorphMany
    {
        return $this->morphMany(ProductionBomSnapshot::class, 'snapshotable');
    }

    /**
     * Drylog sudah diisi. NULL berarti belum; 0 berarti sudah, hasilnya nol.
     */
    public function drylogWasFilled(): bool
    {
        return $this->drylog_qty !== null;
    }

    /** Dilempar dari `lock()` bila drylog belum diisi. */
    protected function refuseToLockWithoutDrylog(): void
    {
        if (! $this->drylogWasFilled()) {
            throw new \RuntimeException(__('Fill in the drylog on the Material Usage page before locking. Zero is allowed.'));
        }
    }

    public function materialWastes(): MorphMany
    {
        return $this->morphMany(ProductionMaterialWaste::class, 'wasteable');
    }

    /** Kerugian rupiah dari bahan terbuang; satu baris per baris bahan terbuang. */
    public function materialWasteLosses(): MorphMany
    {
        return $this->morphMany(FinancialLoss::class, 'lossable')
            ->where('transaction_type', FinancialLoss::SUMBER_MATERIAL_WASTE);
    }

    /**
     * Dipanggil `lock()`: membekukan hitungan BOM saat ini DAN menulis kerugian
     * rupiah bahan terbuang. Keduanya ditulis ulang dari nol, jadi memanggilnya
     * dua kali tidak menggandakan apa pun.
     */
    protected function finaliseMaterialRecord(): void
    {
        $this->bomSnapshots()->delete();

        foreach (BomUsageCalculator::calculate($this->bomLabels())['usage'] as $materialId => $qty) {
            $this->bomSnapshots()->create(['material_id' => $materialId, 'qty' => (int) $qty]);
        }

        $this->freezeDrylogValue();
        $this->writeMaterialWasteLosses();
    }

    /** Dipanggil `unlock()`: snapshot dan kerugian bahan terbuang dilepas. */
    protected function releaseMaterialRecord(): void
    {
        $this->bomSnapshots()->delete();
        $this->materialWasteLosses()->delete();
        $this->materialWastes()->update(['unit_price' => null, 'amount' => null]);
        $this->forceFill(['drylog_unit_price' => null, 'drylog_amount' => null])->save();
    }

    /**
     * Nilai drylog = jumlah x harga per pcs material drylog (`DrylogMaterial`),
     * dibekukan saat Lock. Bahan perhitungan kerugian nanti; belum ada baris
     * Financial Loss untuknya. Tanpa material/harga: 0.
     */
    private function freezeDrylogValue(): void
    {
        $price = DrylogMaterial::unitPrice();

        $this->forceFill([
            'drylog_unit_price' => $price,
            'drylog_amount' => $price === null ? 0.00 : round((int) $this->drylog_qty * $price, 2),
        ])->save();
    }

    /**
     * Satu baris Financial Loss per baris bahan terbuang.
     *
     * Nilai = qty x harga per satuan pakai (`MaterialUnitPrice`), di-SNAPSHOT
     * saat dikunci: harga beli yang berubah kemudian tidak menggeser angka
     * lama. Tanpa harga sama sekali, barisnya tetap dicatat dengan `amount` 0
     * -- `FinancialLoss::isNotPricedYet()` menandainya "belum ada harga" --
     * dan Lock tidak diblokir.
     *
     * Ditulis saat Lock dan dihapus saat Unlock, pola yang sama dengan susut
     * repack: dokumen yang belum final tidak menerbitkan kerugian.
     */
    private function writeMaterialWasteLosses(): void
    {
        $this->materialWasteLosses()->delete();

        $date = $this->boning_date ?? $this->repack_date;

        foreach ($this->materialWastes()->with('material.unit')->get() as $waste) {
            $material = $waste->material;
            $unitPrice = $material ? MaterialUnitPrice::perUsageUnit($material) : null;

            $amount = $unitPrice === null ? 0.00 : round($waste->qty * $unitPrice, 2);

            // Nilai juga disimpan di barisnya sendiri, supaya halaman dokumen,
            // cetakan, dan laporan periode menampilkan angka yang sama tanpa
            // menebak pasangan baris Financial Loss-nya.
            $waste->update(['unit_price' => $unitPrice, 'amount' => $amount]);

            $this->materialWasteLosses()->create([
                'date' => $date,
                'transaction_type' => FinancialLoss::SUMBER_MATERIAL_WASTE,
                'reference_number' => $this->doc_no,
                'amount' => $amount,
                'quantity' => $waste->qty,
                'unit' => 'pcs',
                'note' => ($material?->name ?? '-').': '.$waste->reason,
            ]);
        }
    }
}
