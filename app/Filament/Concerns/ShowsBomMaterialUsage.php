<?php

namespace App\Filament\Concerns;

use App\Models\Material;
use App\Services\BomUsageCalculator;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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
 *  - bagian BOM-nya TERKUNCI (hanya tampilan), sedangkan DRYLOG diisi manual dan
 *    WAJIB sebelum dokumen bisa dikunci; satu-satunya yang disimpan dari
 *    halaman ini adalah drylog (lihat `handleRecordUpdate`);
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

    /**
     * Drylog: satu-satunya bahan yang diisi manual, karena jumlahnya terlalu
     * dinamis untuk dihitung BOM. WAJIB diisi sebelum dokumen bisa dikunci;
     * 0 boleh (diisi, hasilnya nol), kosong tidak. Materialnya dipilih dari
     * master material -- bukan id yang ditulis di kode.
     */
    protected function drylogSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Drylog'))
            ->description(__('Required before this document can be locked. Enter 0 if none was used.'))
            ->schema([
                Forms\Components\Select::make('drylog_material_id')
                    ->label(__('Drylog Material'))
                    ->options(fn (): array => Material::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->columnSpan(['default' => 1, 'md' => 1]),

                // Tanpa komponen angka bawaan (tombol panahnya gampang
                // tertekan). Nol sah; kosong tidak.
                Forms\Components\TextInput::make('drylog_qty')
                    ->label(__('Quantity'))
                    ->suffix(__('pcs'))
                    ->extraInputAttributes(['inputmode' => 'numeric'])
                    ->rules(['required', 'integer', 'min:0'])
                    ->required(),
            ])
            ->columns(['default' => 1, 'md' => 2]);
    }

    /**
     * Hanya drylog yang disimpan dari halaman ini, dan hanya selama dokumennya
     * belum dikunci. Field lain (nomor dokumen, tanggal) tidak dikirim, tetapi
     * permintaan Livewire langsung tetap bisa membawa apa saja -- karena itu
     * penjaganya di sini, bukan hanya di tampilan.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_if($record->fresh()->kunci, 403, 'Data has been locked.');

        $record->update(Arr::only($data, ['drylog_material_id', 'drylog_qty']));

        return $record;
    }
}
