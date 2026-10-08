<?php

namespace App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Forms;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductMaterial;
use App\Support\ProductBomSync;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Bill of Material sebuah produk, sebagai BARIS-BARIS di form Edit produk
 * (issue #507).
 *
 * Sebelumnya BOM adalah panel dengan modal: tiap bahan = buka modal, isi empat
 * kolom, simpan -- lima sampai delapan putaran untuk satu produk. Sekarang
 * semua baris diisi sekaligus dan disimpan SEKALI lewat tombol Save changes
 * produk, jadi daftarnya bisa ditinjau utuh sebelum disimpan.
 *
 * Aturan Repeater di `project.md` diikuti: label baris disembunyikan dan
 * cukup memakai placeholder, tanpa masking `$money()` di dalam baris.
 *
 * Penegakan izin yang sebenarnya ada di `ProductBomSync` (server). Pengaturan
 * `addable`/`deletable` di sini hanya menyederhanakan layar.
 */
class BillOfMaterialSection
{
    /**
     * Bagian BOM di form Edit produk: HANYA TAMPILAN (permintaan Owner, 7
     * Oktober 2026). BOM diisi lewat tombol BOM di daftar produk; di sini
     * pemakai cukup melihat bahan apa saja yang dipakai produk ini. Karena
     * tidak ada field, menyimpan produk tidak menyentuh BOM sama sekali.
     */
    public static function make(): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Bill of Material'))
            ->description(__('View only here. Fill it in with the Bill of Material button on the product list.'))
            ->icon('heroicon-o-archive-box')
            ->visible(fn (string $operation): bool => $operation === 'edit' && self::can('view'))
            ->schema([
                Forms\Components\View::make('filament.partials.product-bom-view')
                    ->viewData(fn (?Model $record): array => [
                        'rows' => $record
                            ? $record->billOfMaterials()->with('material')->orderBy('id')->get()
                            : collect(),
                    ]),
            ]);
    }

    /**
     * Isi jendela tombol BOM di daftar produk: pilihan "Copy from Another
     * Product" di atas, lalu baris-baris BOM.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function modalSchema(): array
    {
        return [self::copyFrom(), self::rows()];
    }

    /**
     * Baris-baris BOM di jendela tombol BOM (tanpa relasi, disimpan lewat
     * `ProductBomSync`).
     */
    public static function rows(): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('billOfMaterials')
                    ->hiddenLabel()
                    ->addActionLabel(__('Add Material'))
                    ->addable(fn (): bool => self::can('create'))
                    ->deletable(fn (): bool => self::can('delete'))
                    ->disabled(fn (): bool => ! self::can('create') && ! self::can('edit'))
                    ->reorderable(false)
                    ->cloneable(false)
                    ->defaultItems(0)
                    // Strip di atas tiap baris (tempat ikon hapus) diisi ringkasan
                    // satu kalimat, supaya baris terbaca "1 DUS per box" dan
                    // jumlah yang kosong terbaca "tidak tetap" -- bukan strip
                    // kosong yang cuma memakan tinggi.
                    ->itemLabel(fn (array $state): ?string => self::summary($state))
                    ->columns(12)
                    ->schema([
                        Forms\Components\Hidden::make('id'),

                        \App\Filament\Support\MasterSelect::material('material_id', withCode: true)
                            ->label('')
                            ->hiddenLabel()
                            ->placeholder(__('Select material'))
                            ->required()
                            ->live()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->columnSpan(['default' => 1, 'lg' => 5]),

                        Forms\Components\Select::make('basis')
                            ->label('')
                            ->hiddenLabel()
                            ->options(collect(ProductMaterial::BASIS)->map(fn (string $label): string => __($label))->all())
                            ->default('box')
                            ->required()
                            // Live supaya judul baris (ringkasan) ikut berubah.
                            ->live()
                            ->selectablePlaceholder(false)
                            ->columnSpan(['default' => 1, 'lg' => 3]),

                        // Tidak ada isian jumlah (Owner, 7 Oktober 2026): yang menentukan
                        // hanya bahan ini dihitung per pcs atau per box di produk ini,
                        // jumlahnya selalu 1. Kolom `quantity` tetap ada di database dan
                        // DIBAWA apa adanya: baris baru berisi 1, baris lama yang
                        // jumlahnya 2/6/10 atau kosong (drylog "tidak tetap") tidak
                        // diubah diam-diam.
                        Forms\Components\Hidden::make('quantity')
                            ->default(1),

                        Forms\Components\TextInput::make('note')
                            ->label('')
                            ->hiddenLabel()
                            ->placeholder(__('Note (optional)'))
                            ->maxLength(500)
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                    ]);
    }

    /**
     * Tombol BOM di daftar produk: membuka baris-baris BOM yang SAMA dengan
     * form Edit produk, tanpa masuk halaman Edit (permintaan Owner, 7 Oktober
     * 2026 -- tombolnya di daftar, bukan di dalam tiap produk). Semua baris
     * diisi sekaligus dan disimpan SEKALI lewat `ProductBomSync`, yang juga
     * menegakkan izin di server.
     */
    public static function tableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('bill_of_material')
            ->iconButton()
            // Belum punya BOM = tombol "buat" (abu-abu, tanda tambah); sudah
            // punya = tombol "ubah" (kuning, pensil) dengan angka jumlah bahan.
            ->icon(fn (Product $record): string => self::bomCount($record) > 0 ? 'heroicon-o-pencil-square' : 'heroicon-o-plus-circle')
            ->color(fn (Product $record): string => self::bomCount($record) > 0 ? 'warning' : 'gray')
            ->badge(fn (Product $record): ?int => self::bomCount($record) ?: null)
            ->tooltip(fn (Product $record): string => self::bomCount($record) > 0
                ? __('Edit Bill of Material')
                : __('Create Bill of Material'))
            ->visible(fn (): bool => self::can('view'))
            ->modalHeading(fn (Product $record): string => __('Bill of Material').' -- '.$record->name)
            ->modalDescription(__('List the packaging this product uses. Add all the rows you need, then press Save changes once.'))
            ->modalSubmitActionLabel(__('Save changes'))
            ->modalWidth('5xl')
            ->fillForm(fn (Product $record): array => [
                'billOfMaterials' => $record->billOfMaterials()->orderBy('id')->get()
                    ->mapWithKeys(fn (ProductMaterial $row): array => ["record-{$row->id}" => [
                        'id' => $row->id,
                        'material_id' => $row->material_id,
                        'basis' => $row->basis,
                        'quantity' => $row->quantity,
                        'note' => $row->note,
                    ]])
                    ->all(),
            ])
            ->form(self::modalSchema())
            ->action(function (array $data, Product $record): void {
                try {
                    ProductBomSync::sync($record, $data['billOfMaterials'] ?? []);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title(__('Bill of material not saved'))
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();

                    throw new Halt();
                }

                Notification::make()->title(__('Bill of material saved'))->success()->send();
            });
    }

    /**
     * Menyalin BOM produk lain KE FORM, belum ke database.
     *
     * Memilih produk sumber langsung mengisi baris-barisnya di bawah. Bahan
     * yang sudah ada barisnya tidak ditimpa (yang menyalin sedang melengkapi
     * daftarnya, bukan menggantinya), dan salinannya putus dari asalnya begitu
     * disimpan -- data produksi memperlihatkan daftar produk yang mirip memang
     * sering berbeda sedikit (BACKRIB memakai karton top, BACKRIB CUT tidak).
     * Karena baru mengisi form, hasilnya bisa ditinjau dan diubah sebelum
     * Save changes.
     *
     * Berupa Select biasa, bukan aksi komponen: aksi di dalam jendela aksi
     * tabel butuh `key()` dan bersarang dua tingkat.
     */
    private static function copyFrom(): Forms\Components\Select
    {
        return \App\Filament\Support\MasterSelect::server(
            Forms\Components\Select::make('copy_from')
                ->label(__('Copy from Another Product'))
                ->placeholder(__('Select a source product')),
            Product::class,
            activeOnly: false,
            // Hanya produk yang sudah punya BOM, selain produk yang sedang diisi.
            scope: fn ($query, $livewire) => $query
                ->whereKeyNot(self::currentProduct($livewire)?->getKey())
                ->whereHas('billOfMaterials'),
        )
            ->live()
            ->dehydrated(false)
            ->visible(fn (): bool => self::can('create'))
            ->helperText(__('Only products that already have a bill of material are listed.'))
            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                if (blank($state)) {
                    return;
                }

                [$merged, $added] = self::copyRows($get('billOfMaterials') ?? [], (int) $state);

                $set('copy_from', null);

                if ($added === 0) {
                    Notification::make()
                        ->title(__('Nothing new to copy'))
                        ->body(__('Every material of that product is already listed here.'))
                        ->warning()
                        ->send();

                    return;
                }

                $set('billOfMaterials', $merged);

                Notification::make()
                    ->title(trans_choice(':count material added to the list|:count materials added to the list', $added, [
                        'count' => $added,
                    ]))
                    ->body(__('Not saved yet. Check the rows, then press Save changes.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Baris form hasil menyalin BOM produk lain, tanpa menyentuh database.
     *
     * Bahan yang sudah ada barisnya di form dilewati (jumlah yang sudah
     * disesuaikan tangan tidak boleh tertimpa). Baris baru diberi kunci acak,
     * bukan `record-{id}`, sehingga disimpan sebagai baris BARU.
     *
     * @param  array<string, array<string, mixed>>  $current
     * @return array{0: array<string, array<string, mixed>>, 1: int}  [state baru, jumlah baris yang ditambahkan]
     */
    public static function copyRows(array $current, int $sourceProductId): array
    {
        $listed = collect($current)->pluck('material_id')->filter()->map(fn ($id) => (int) $id)->all();

        $source = ProductMaterial::query()
            ->where('product_id', $sourceProductId)
            ->whereNotIn('material_id', $listed)
            ->orderBy('id')
            ->get();

        foreach ($source as $row) {
            $current[(string) Str::uuid()] = [
                'material_id' => $row->material_id,
                'basis' => $row->basis,
                'quantity' => $row->quantity,
                'note' => $row->note,
            ];
        }

        return [$current, $source->count()];
    }

    /** @param array<string, mixed> $state */
    private static function summary(array $state): ?string
    {
        if (empty($state['material_id'])) {
            return null;
        }

        $material = Material::with('unit')->find($state['material_id']);

        if (! $material) {
            return null;
        }

        $quantity = $state['quantity'] ?? null;
        $basis = $state['basis'] ?? 'box';
        $unit = __('pcs');

        if ($quantity === null || $quantity === '') {
            // Hanya baris lama (drylog "tidak tetap" dari impor legacy).
            $text = __($basis === 'piece' ? 'amount not fixed, per pcs' : 'amount not fixed, per box');
        } elseif ((int) $quantity === 1) {
            $text = __($basis === 'piece' ? 'counted per pcs' : 'counted per box');
        } else {
            $text = __($basis === 'piece' ? ':qty :unit per pcs' : ':qty :unit per box', [
                'qty' => number_format((int) $quantity, 0, ',', '.'),
                'unit' => $unit,
            ]);
        }

        return $material->name.' -- '.$text;
    }

    /** Jumlah baris BOM sebuah produk; memakai withCount() dari tabel bila ada (tanpa query per baris). */
    private static function bomCount(Product $record): int
    {
        return (int) ($record->bill_of_materials_count ?? $record->billOfMaterials()->count());
    }

    /** Produk yang sedang diedit: dari halaman Edit, atau dari aksi tabel di daftar produk. */
    private static function currentProduct(mixed $livewire): ?Model
    {
        return method_exists($livewire, 'getMountedTableActionRecord')
            ? $livewire->getMountedTableActionRecord()
            : $livewire->getRecord();
    }

    private static function can(string $ability): bool
    {
        return auth()->user()?->hasPermission("{$ability}_product_materials") ?? false;
    }
}
