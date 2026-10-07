<?php

namespace App\Filament\Concerns;

use App\Models\Material;
use App\Services\BomUsageCalculator;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Halaman "Pemakaian Material" Boning dan Repack: pemakaian bahan menurut BOM.
 *
 * Issue #509, keputusan Owner 7 Oktober 2026. Halaman ini dulu sebuah form
 * yang menulis `MaterialUsage` -- dan `MaterialUsage` MEMOTONG STOK. Sekarang
 * ia hanya MENAMPILKAN kebutuhan bahan menurut BOM:
 *
 *  - dihitung ulang dari label terkini tiap kali halaman dibuka (belum ada
 *    snapshot sebelum dokumennya dikunci);
 *  - TERKUNCI: tidak ada field yang bisa diubah, dan `save()` yang dikirim
 *    langsung ke Livewire pun tidak menulis apa-apa (lihat
 *    `handleRecordUpdate`) -- menyembunyikan tombol saja tidak menutup
 *    permintaan langsung;
 *  - TIDAK memotong stok, tidak melahirkan `MaterialUsage` maupun
 *    `MaterialStockMovement`. Stok tetap dikeluarkan lewat jalur manual
 *    (Material Usage > Create Manual Usage).
 *
 * Satu rumah untuk dua halaman: Boning dan Repack hanya berbeda pada relasi
 * labelnya (`items` vs `results`), yang disebut `bomUsageLabels()`.
 */
trait ShowsBomMaterialUsage
{
    /** Label (satu baris = satu box) yang dihitung BOM-nya. */
    abstract protected function bomUsageLabels(): Collection;

    /**
     * @return array{
     *     rows: array<int, array{material: string, qty: int|float, unit: string}>,
     *     products: array<int, array{product_name: string, box: int, pcs: int}>,
     *     without_bom: array<int, array{product_id: int, product_name: string}>,
     *     skipped: array<int, array{product_name: string, material_name: string}>
     * }
     */
    public function bomUsage(): array
    {
        $result = BomUsageCalculator::calculate($this->bomUsageLabels());

        $materials = Material::with('unit')->whereIn('id', array_keys($result['usage']))->get()->keyBy('id');

        $rows = [];
        foreach ($result['usage'] as $materialId => $qty) {
            $material = $materials->get($materialId);
            $rows[] = [
                'material' => $material?->name ?? '-',
                'qty' => $qty,
                'unit' => $material?->unit?->name ?? '',
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['material'], $b['material']));

        return [
            'rows' => $rows,
            'products' => $result['products'],
            'without_bom' => $result['without_bom'],
            'skipped' => $result['skipped'],
        ];
    }

    protected function bomUsageSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Usage per BOM'))
            ->description(__('Calculated automatically from the BOM and the labels of this document. Read-only; it does not change material stock.'))
            ->schema([
                Forms\Components\View::make('filament.partials.bom-material-usage')
                    ->viewData(fn (): array => $this->bomUsage()),
            ]);
    }

    /** Tidak ada yang disimpan dari halaman ini -- tidak ada tombol Save. */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Penjaga di sisi server. Tombol Save sudah tidak ada, tetapi `save()`
     * tetap bisa dipanggil lewat permintaan Livewire langsung; hasilnya harus
     * tidak menulis apa-apa.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $record;
    }
}
