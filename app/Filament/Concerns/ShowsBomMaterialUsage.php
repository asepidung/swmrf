<?php

namespace App\Filament\Concerns;

use App\Models\Material;
use App\Services\BomUsageCalculator;
use App\Services\ProductionMaterialSummary;
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

    /** Dokumennya sudah dikunci: halaman hanya menampilkan angka beku. */
    protected function isLockedRecord(): bool
    {
        return (bool) $this->getRecord()->fresh()?->kunci;
    }

    /**
     * Bagian-bagian halaman. Belum dikunci: BOM (tampilan), drylog, dan bahan
     * terbuang yang bisa diisi. Sudah dikunci: ringkasan beku yang hanya bisa
     * dibaca -- sama dengan yang tampil di halaman View dan cetakan.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function materialUsageSections(): array
    {
        if ($this->isLockedRecord()) {
            return [
                Forms\Components\Section::make(__('Material Usage'))
                    ->description(__('This document is locked. The figures below are final; unlock it to change them.'))
                    ->schema([
                        Forms\Components\View::make('filament.partials.production-material-summary')
                            ->viewData(fn (): array => ['summary' => ProductionMaterialSummary::for($this->getRecord()->fresh())]),
                    ]),
            ];
        }

        return [$this->bomUsageSection(), $this->drylogSection(), $this->wasteSection()];
    }

    /** Tombol cetak lembar pemakaian bahan dokumen ini. */
    protected function printMaterialUsageAction(string $kind): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('print')
            ->label(__('Print'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->url(fn (): string => route('production-material.print', ['kind' => $kind, 'id' => $this->getRecord()->getKey()]))
            ->openUrlInNewTab();
    }

    /** Tidak ada tombol Save untuk dokumen yang sudah dikunci. */
    protected function getFormActions(): array
    {
        return $this->isLockedRecord() ? [] : parent::getFormActions();
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
     * Drylog / pad absorber: satu-satunya bahan yang diisi manual, karena
     * jumlahnya terlalu dinamis untuk dihitung BOM. Ia SATU material yang pasti,
     * jadi cukup jumlahnya yang diisi -- tanpa pilihan material (Owner, 8
     * Oktober 2026). WAJIB diisi sebelum dokumen bisa dikunci; 0 boleh (diisi,
     * hasilnya nol), kosong tidak.
     */
    protected function drylogSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Drylog / Pad Absorber'))
            ->description(__('Required before this document can be locked. Enter 0 if none was used.'))
            ->schema([
                // Tanpa komponen angka bawaan (tombol panahnya gampang
                // tertekan). Nol sah; kosong tidak.
                Forms\Components\TextInput::make('drylog_qty')
                    ->label(__('Drylog / Pad Absorber'))
                    ->suffix(__('pcs'))
                    ->extraInputAttributes(['inputmode' => 'numeric'])
                    ->rules(['required', 'integer', 'min:0'])
                    ->required(),
            ]);
    }

    /**
     * Bahan terbuang: boleh banyak baris, boleh kosong sama sekali. Bahan bebas
     * dipilih dari master material; jumlahnya bilangan bulat (satuan pakai,
     * mis. pcs); ALASAN wajib. Pemakaian yang melebihi BOM dicatat di sini,
     * bukan dengan mengubah BOM. Tidak memotong stok.
     */
    protected function wasteSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Wasted Material'))
            ->description(__('Material that was thrown away, with the reason. Leave empty if nothing was wasted. Its value is recorded as a financial loss when the document is locked.'))
            ->schema([
                Forms\Components\Repeater::make('materialWastes')
                    ->relationship('materialWastes')
                    ->hiddenLabel()
                    ->addActionLabel(__('Add Wasted Material'))
                    ->defaultItems(0)
                    ->columns(['default' => 1, 'md' => 12])
                    ->schema([
                        Forms\Components\Select::make('material_id')
                            ->hiddenLabel()
                            ->placeholder(__('Select material'))
                            ->options(fn (): array => Material::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            // Satu material, satu baris (Owner, 8 Oktober 2026): di layar,
                            // pilihan yang sudah dipakai baris lain dinonaktifkan; di
                            // server, `distinct()` menolak permintaan yang tetap
                            // membawa material kembar. Dua alasan untuk satu material
                            // ditulis bersama di kolom alasan.
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->distinct()
                            ->validationMessages(['distinct' => __('This material is already listed. Use one row per material and write all the reasons together.')])
                            ->columnSpan(['default' => 1, 'md' => 5]),

                        Forms\Components\TextInput::make('qty')
                            ->hiddenLabel()
                            ->placeholder(__('Quantity'))
                            ->suffix(__('pcs'))
                            ->extraInputAttributes(['inputmode' => 'numeric'])
                            ->rules(['required', 'integer', 'min:1'])
                            ->required()
                            ->columnSpan(['default' => 1, 'md' => 3]),

                        Forms\Components\TextInput::make('reason')
                            ->hiddenLabel()
                            ->placeholder(__('Reason (required)'))
                            ->maxLength(255)
                            ->required()
                            ->columnSpan(['default' => 1, 'md' => 4]),
                    ]),
            ]);
    }

    /**
     * Hanya drylog (dan baris bahan terbuang lewat relasinya) yang disimpan dari halaman ini, dan hanya selama dokumennya
     * belum dikunci. Field lain (nomor dokumen, tanggal) tidak dikirim, tetapi
     * permintaan Livewire langsung tetap bisa membawa apa saja -- karena itu
     * penjaganya di sini, bukan hanya di tampilan.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_if($record->fresh()->kunci, 403, 'Data has been locked.');

        $record->update(Arr::only($data, ['drylog_qty']));

        return $record;
    }
}
