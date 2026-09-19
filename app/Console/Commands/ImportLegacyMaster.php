<?php

namespace App\Console\Commands;

use App\Models\CattleClass;
use App\Models\Grade;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\Supplier;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Impor master data MEKANIS dari snapshot legacy (issue #471).
 *
 * "Mekanis" berarti: memindahkan apa yang sudah jelas artinya (kategori,
 * grade, produk, bahan, supplier, kelas sapi), BUKAN transaksi maupun
 * data yang masih butuh keputusan Owner (customer, grup pelanggan,
 * segment -- sesi terpisah, lihat `.agents/legacy-db.md`).
 *
 * Sumbernya TEKS dump SQL (`--source`), dibaca lewat `LegacySqlDump` --
 * TIDAK PERNAH dieksekusi sebagai query terhadap koneksi mana pun.
 * Perintah ini sendiri menulis lewat koneksi DEFAULT aplikasi (Eloquent
 * biasa), TIDAK PERNAH membuka koneksi ke basis data legacy -- dump-nya
 * sudah berupa berkas lepas, bukan koneksi hidup ke produksi.
 *
 * Default-nya laporan saja (`dry-run`) -- tidak menulis apa pun sampai
 * `--apply` diberikan. Idempoten: setiap baris dicocokkan lewat nama/kode
 * yang SUDAH ADA sebelum diputuskan dibuat, jadi menjalankannya dua kali
 * tidak pernah menggandakan.
 */
class ImportLegacyMaster extends Command
{
    protected $signature = 'legacy:import-master
                            {--source= : Path ke berkas dump SQL legacy}
                            {--apply : Benar-benar menulis perubahan (default hanya laporan dry-run)}';

    protected $description = 'Impor master data (kategori, grade, produk, bahan, supplier, kelas sapi) dari snapshot legacy';

    /** @var array<string, array<int, string>> */
    private array $created = [];

    /** @var array<string, array<int, string>> */
    private array $skipped = [];

    /** @var array<string, array<int, string>> */
    private array $conflicts = [];

    /** @var array<int, string> */
    private array $notes = [];

    /**
     * Nama yang sudah ada di basis data, per tabel, kunci-nya lewat
     * `matchKey()` -- dimuat SEKALI per tabel per jalan (lihat `existingKeysFor()`).
     *
     * @var array<string, array<string, string>>
     */
    private array $existingKeysCache = [];

    /**
     * Nama yang SUDAH DIPUTUSKAN "baru" dalam jalan ini sendiri, per
     * tabel -- menangkap duplikat DI DALAM sumbernya sendiri (mis. dua
     * baris supplier beda spasi/format untuk perusahaan yang sama).
     *
     * @var array<string, array<string, string>>
     */
    private array $seenInBatch = [];

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path) {
            $this->error('--source wajib diisi (path ke berkas dump SQL legacy).');

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');

        $run = function () use ($sql): void {
            $categoryNameByLegacyId = $this->importProductCategories($sql);
            $this->importGrades($sql);
            $this->importSuppliers($sql);
            $this->importCattleClasses($sql);

            $productMapByLegacyId = $this->importProducts($sql, $categoryNameByLegacyId);
            $materialMapByLegacyId = $this->importMaterialCategoriesAndMaterials($sql);
            $this->importProductMaterials($sql, $productMapByLegacyId, $materialMapByLegacyId);
        };

        if ($apply) {
            DB::transaction($run);
        } else {
            $run();
        }

        $this->printReport($apply);

        return self::SUCCESS;
    }

    // =========================================================================
    // cuts -> product_categories
    // =========================================================================

    /**
     * @return array<string, string> idcut legacy => nama kategori, HANYA
     *                                untuk baris yang berhasil dipetakan
     *                                (tanpa konflik) -- dipakai
     *                                `importProducts()` untuk tahu kategori
     *                                mana yang sungguhan bisa dirujuk,
     *                                baik dry-run maupun `--apply`.
     */
    private function importProductCategories(string $sql): array
    {
        $resolved = [];

        foreach (LegacySqlDump::parseTable($sql, 'cuts') as $row) {
            $name = strtoupper(trim((string) $row['nmcut']));

            if ($name === '') {
                $this->conflicts['product_categories'][] = "idcut={$row['idcut']}: nama kosong, dilewati.";

                continue;
            }

            if (ProductCategory::where('name', $name)->exists()) {
                $this->skipped['product_categories'][] = "{$name}: sudah ada.";
                $resolved[$row['idcut']] = $name;

                continue;
            }

            // `prefix` tidak ada padanan LANGSUNG di `cuts` -- tapi
            // `ProductResource::generateNextCode()` menyusun kode produk
            // dari `prefix.sprintf('%03d').00` (mis. prefix 1 -> "100100"),
            // PERSIS pola `kdbarang` legacy (100100=PRIME CUT,
            // 200100=SECONDARY CUT, dst -- digit pertamanya adalah
            // `idcut`). Bukan tebakan: `idcut` DIPAKAI sebagai prefix
            // karena angkanya sudah cocok dengan skema yang ada.
            $prefix = (int) $row['idcut'];

            if (ProductCategory::where('prefix', $prefix)->exists()) {
                $this->conflicts['product_categories'][] = "{$name}: prefix {$prefix} (dari idcut) sudah dipakai kategori lain, dilewati.";

                continue;
            }

            $this->created['product_categories'][] = "{$name} (prefix {$prefix})";
            $resolved[$row['idcut']] = $name;

            if ($this->option('apply')) {
                ProductCategory::create(['name' => $name, 'prefix' => $prefix]);
            }
        }

        return $resolved;
    }

    // =========================================================================
    // grade -> grades
    // =========================================================================

    private function importGrades(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'grade') as $row) {
            $name = strtoupper(trim((string) $row['nmgrade']));

            if ($name === '') {
                $this->conflicts['grades'][] = "idgrade={$row['idgrade']}: nama kosong, dilewati.";

                continue;
            }

            $this->upsert('grades', $name, fn () => Grade::firstOrCreate(['name' => $name], ['is_active' => true]));
        }
    }

    // =========================================================================
    // supplier -> suppliers
    // =========================================================================

    private function importSuppliers(string $sql): void
    {
        $rows = LegacySqlDump::parseTable($sql, 'supplier');

        if ($rows !== []) {
            $this->notes[] = 'Suppliers: `pic` tidak ada padanan di legacy, diisi "-" untuk semua baris baru; '
                .'`top_days` diisi 0 (tidak ada padanan) -- sesuaikan manual bila relevan untuk jatuh tempo hutang.';
        }

        foreach ($rows as $row) {
            $name = strtoupper(trim((string) $row['nmsupplier']));

            if ($name === '') {
                $this->conflicts['suppliers'][] = "idsupplier={$row['idsupplier']}: nama kosong, dilewati.";

                continue;
            }

            if ($this->classifyByName('suppliers', Supplier::class, $name, "idsupplier={$row['idsupplier']}") !== 'new') {
                continue;
            }

            $npwp = trim((string) ($row['npwp'] ?? ''));
            if ($npwp !== '' && $npwp !== '-') {
                $this->conflicts['suppliers'][] = "{$name}: NPWP legacy \"{$npwp}\" tidak diimpor -- Supplier swmrf tidak punya kolom NPWP.";
            }

            $this->created['suppliers'][] = $name;

            if ($this->option('apply')) {
                Supplier::create([
                    'name' => $name,
                    'address' => trim((string) ($row['alamat'] ?? '')) ?: '-',
                    'pic' => '-',
                    'phone' => trim((string) ($row['telepon'] ?? '')) ?: null,
                    'top_days' => 0,
                    'supplied_goods' => trim((string) ($row['jenis_usaha'] ?? '')) ?: null,
                ]);
            }
        }
    }

    // =========================================================================
    // cattle_class -> cattle_classes
    // =========================================================================

    private function importCattleClasses(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'cattle_class') as $row) {
            $name = strtoupper(trim((string) $row['class_name']));

            if ($name === '') {
                $this->conflicts['cattle_classes'][] = "idclass={$row['idclass']}: nama kosong, dilewati.";

                continue;
            }

            $this->upsert('cattle_classes', $name, fn () => CattleClass::firstOrCreate(['name' => $name]));
        }
    }

    // =========================================================================
    // barang -> products
    // =========================================================================

    /**
     * @param  array<string, string>  $categoryNameByLegacyId  dari importProductCategories()
     * @return array<string, array{valid: bool, name?: string, code?: string}> idbarang legacy => hasil,
     *              dipakai `importProductMaterials()` untuk tahu produk mana yang sungguhan bisa dirujuk BOM-nya.
     */
    private function importProducts(string $sql, array $categoryNameByLegacyId): array
    {
        $legacyMap = [];

        foreach (LegacySqlDump::parseTable($sql, 'barang') as $row) {
            $idbarang = $row['idbarang'];
            $name = strtoupper(trim((string) $row['nmbarang']));
            $code = trim((string) ($row['kdbarang'] ?? ''));

            if ($name === '') {
                $this->conflicts['products'][] = "idbarang={$idbarang}: nama kosong, dilewati.";
                $legacyMap[$idbarang] = ['valid' => false];

                continue;
            }

            // Beberapa baris legacy memakai '-' sebagai kode -- bukan
            // kode yang sungguhan, dan `products.code` UNIK: membiarkan
            // beberapa baris berbagi '-' akan menabrak constraint itu di
            // baris KEDUA dan seterusnya. Tidak ada dasar untuk menebak
            // kode yang benar, jadi dilaporkan sebagai konflik.
            if ($code === '' || $code === '-') {
                $this->conflicts['products'][] = "{$name} (idbarang={$idbarang}): kode legacy kosong/\"-\", dilewati -- perlu kode manual.";
                $legacyMap[$idbarang] = ['valid' => false];

                continue;
            }

            if (Product::where('code', $code)->exists()) {
                $this->skipped['products'][] = "{$name} ({$code}): sudah ada (kode).";
                $legacyMap[$idbarang] = ['valid' => true, 'name' => $name, 'code' => $code];

                continue;
            }

            $status = $this->classifyByName('products', Product::class, $name, "idbarang={$idbarang}");

            if ($status === 'exists') {
                $legacyMap[$idbarang] = ['valid' => true, 'name' => $name, 'code' => $code];

                continue;
            }

            if ($status === 'conflict') {
                $legacyMap[$idbarang] = ['valid' => false];

                continue;
            }

            $categoryName = $categoryNameByLegacyId[$row['idcut']] ?? null;

            if ($categoryName === null) {
                $this->conflicts['products'][] = "{$name} ({$code}): kategori (idcut={$row['idcut']}) tidak berhasil dipetakan, produk dilewati.";
                $legacyMap[$idbarang] = ['valid' => false];

                continue;
            }

            // Hafizh eksplisit: kodeinduk & karton/drylog/plastik dicatat
            // ke CATATAN produk, bukan diresolusi ke parent_id/
            // structure_type -- itu hierarki BOM lama yang beririsan
            // dengan `product_materials` (langkah ini juga), dan
            // menyatukan keduanya otomatis butuh keputusan Owner
            // tersendiri di luar cakupan mekanis.
            $noteParts = [];
            if (! empty($row['kodeinduk'])) {
                $noteParts[] = "kodeinduk legacy: {$row['kodeinduk']}";
            }
            if (! empty($row['karton'])) {
                $noteParts[] = "karton: {$row['karton']}";
            }
            if (! empty($row['drylog'])) {
                $noteParts[] = "drylog: {$row['drylog']}";
            }
            if (! empty($row['plastik'])) {
                $noteParts[] = "plastik: {$row['plastik']}";
            }
            $note = $noteParts === [] ? null : ('Legacy -- '.implode('; ', $noteParts));

            $this->created['products'][] = "{$name} ({$code})";
            $legacyMap[$idbarang] = ['valid' => true, 'name' => $name, 'code' => $code];

            if ($this->option('apply')) {
                $category = ProductCategory::where('name', $categoryName)->first();
                Product::create([
                    'code' => $code,
                    'name' => $name,
                    'category_id' => $category->id,
                    'structure_type' => 'main',
                    'is_active' => true,
                    'legacy_note' => $note,
                ]);
            }
        }

        return $legacyMap;
    }

    // =========================================================================
    // rawcategory -> material_categories, rawmate -> materials
    // =========================================================================

    /** Kategori bahan yang benar-benar produksi/kemasan -- lihat `.agents/legacy-db.md`. */
    private const MATERIAL_CATEGORY_WHITELIST = [
        'KARTON', 'PLASTIK', 'LABEL', 'MIKA', 'STYROFOAM', 'TRAY',
        'PACKAGING SUPPORT', 'MEAT PROCESSING', 'LAKBAN', 'PRODUKSI',
    ];

    /**
     * @return array<string, array{valid: bool, name?: string}> idrawmate legacy => hasil,
     *              dipakai `importProductMaterials()`.
     */
    private function importMaterialCategoriesAndMaterials(string $sql): array
    {
        $categoryNameByLegacyId = [];

        foreach (LegacySqlDump::parseTable($sql, 'rawcategory') as $row) {
            $name = strtoupper(trim((string) $row['nmcategory']));

            if (! in_array($name, self::MATERIAL_CATEGORY_WHITELIST, true)) {
                continue;
            }

            $categoryNameByLegacyId[$row['idrawcategory']] = $name;

            if (MaterialCategory::where('name', $name)->exists()) {
                $this->skipped['material_categories'][] = "{$name}: sudah ada.";

                continue;
            }

            $this->created['material_categories'][] = $name;

            if ($this->option('apply')) {
                MaterialCategory::create(['name' => $name]);
            }
        }

        $legacyMap = [];

        foreach (LegacySqlDump::parseTable($sql, 'rawmate') as $row) {
            $idrawmate = $row['idrawmate'];
            $name = strtoupper(trim((string) $row['nmrawmate']));
            $categoryName = $categoryNameByLegacyId[$row['idrawcategory']] ?? null;

            // Bahan di kategori operasional (ATK, INTERNET, dll) SENGAJA
            // tidak diimpor -- instruksi eksplisit "HANYA kategori
            // produksi/kemasan". Ini bukan konflik, melainkan cakupan.
            if ($categoryName === null) {
                $legacyMap[$idrawmate] = ['valid' => false];

                continue;
            }

            if ($name === '') {
                $this->conflicts['materials'][] = "idrawmate={$idrawmate}: nama kosong, dilewati.";
                $legacyMap[$idrawmate] = ['valid' => false];

                continue;
            }

            $status = $this->classifyByName('materials', Material::class, $name, "idrawmate={$idrawmate}");

            if ($status === 'exists') {
                $legacyMap[$idrawmate] = ['valid' => true, 'name' => $name];

                continue;
            }

            if ($status === 'conflict') {
                $legacyMap[$idrawmate] = ['valid' => false];

                continue;
            }

            $unit = strtoupper(trim((string) ($row['unit'] ?? '')));

            if ($unit === '') {
                $this->conflicts['materials'][] = "{$name} (idrawmate={$idrawmate}): satuan kosong di legacy, dilewati -- material_unit_id wajib diisi.";
                $legacyMap[$idrawmate] = ['valid' => false];

                continue;
            }

            $this->created['materials'][] = "{$name} ({$categoryName}, {$unit})";
            $legacyMap[$idrawmate] = ['valid' => true, 'name' => $name];

            if ($this->option('apply')) {
                $category = MaterialCategory::where('name', $categoryName)->first();
                $materialUnit = MaterialUnit::firstOrCreate(['name' => $unit]);

                // `code` TIDAK diisi dari `kdrawmate` legacy -- Material
                // sudah punya penomoran sendiri (MTR001, ...) lewat
                // `Material::booted()`, dan instruksi impor tidak meminta
                // kode legacy dipertahankan (beda dari `barang.kdbarang`
                // yang eksplisit diminta jadi `products.code`).
                Material::create([
                    'name' => $name,
                    'material_category_id' => $category->id,
                    'material_unit_id' => $materialUnit->id,
                    'min_stock' => (int) ($row['barmin'] ?? 0),
                    'is_active' => true,
                ]);
            }
        }

        return $legacyMap;
    }

    // =========================================================================
    // bom_rawmate (is_active=1) -> product_materials
    // =========================================================================

    /**
     * @param  array<string, array{valid: bool, name?: string, code?: string}>  $productMapByLegacyId
     * @param  array<string, array{valid: bool, name?: string}>  $materialMapByLegacyId
     */
    private function importProductMaterials(string $sql, array $productMapByLegacyId, array $materialMapByLegacyId): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'bom_rawmate') as $row) {
            if ($row['is_active'] !== '1') {
                continue;
            }

            $idbom = $row['idbom'];
            $product = $productMapByLegacyId[$row['idbarang']] ?? ['valid' => false];
            $material = $materialMapByLegacyId[$row['idrawmate']] ?? ['valid' => false];

            if (! $product['valid']) {
                $this->conflicts['product_materials'][] = "idbom={$idbom}: idbarang={$row['idbarang']} tidak berhasil dipetakan ke produk, baris BOM dilewati.";

                continue;
            }

            if (! $material['valid']) {
                $this->conflicts['product_materials'][] = "idbom={$idbom}: idrawmate={$row['idrawmate']} tidak berhasil dipetakan ke material (kategori bukan produksi/kemasan?), baris BOM dilewati.";

                continue;
            }

            $productName = $product['name'];
            $materialName = $material['name'];

            if ($this->option('apply')) {
                $productModel = Product::where('name', $productName)->first();
                $materialModel = Material::where('name', $materialName)->first();

                if (! $productModel || ! $materialModel) {
                    $this->conflicts['product_materials'][] = "idbom={$idbom}: {$productName} / {$materialName} tidak ditemukan saat --apply (kemungkinan gagal dibuat di langkah sebelumnya), baris BOM dilewati.";

                    continue;
                }

                if (ProductMaterial::where('product_id', $productModel->id)->where('material_id', $materialModel->id)->exists()) {
                    $this->skipped['product_materials'][] = "{$productName} + {$materialName}: sudah ada.";

                    continue;
                }
            } elseif (ProductMaterial::whereHas('product', fn ($q) => $q->where('name', $productName))
                ->whereHas('material', fn ($q) => $q->where('name', $materialName))
                ->exists()) {
                $this->skipped['product_materials'][] = "{$productName} + {$materialName}: sudah ada.";

                continue;
            }

            // `basis` DEFAULT 'box' sesuai instruksi -- `bom_rawmate` tidak
            // pernah menyatakan dasar hitungnya sendiri. PLASTIK/LABEL/MIKA
            // biasanya membungkus per POTONG, bukan per box, jadi baris
            // dengan kategori itu ditandai untuk ditinjau -- TETAP diimpor
            // dengan basis 'box', bukan ditebak jadi 'piece' diam-diam.
            $needsReview = str_contains($materialName, 'PLASTIK')
                || str_contains($materialName, 'LABEL')
                || str_contains($materialName, 'MIKA');

            $this->created['product_materials'][] = "{$productName} + {$materialName} (qty {$row['qty']}, basis box)"
                .($needsReview ? ' -- TINJAU: mungkin per-pcs, bukan per-box' : '');

            if ($this->option('apply')) {
                ProductMaterial::create([
                    'product_id' => $productModel->id,
                    'material_id' => $materialModel->id,
                    'quantity' => (int) $row['qty'],
                    'basis' => 'box',
                ]);
            }
        }
    }

    // =========================================================================
    // Bantuan
    // =========================================================================

    /**
     * Buat-atau-lewati satu baris master sederhana (nama unik saja).
     *
     * Selalu memutuskan LEBIH DULU lewat exists(), baik dry-run maupun
     * `--apply` -- supaya laporan dry-run persis meramalkan apa yang akan
     * terjadi, bukan menebak dari sisi lain.
     */
    /**
     * Kunci pencocokan longgar -- BUKAN nama yang disimpan.
     *
     * Susulan Hafizh 20 September 2026: legacy punya "CV. SAMUDERA
     * KARUNIA RIZKY" dan "CV.SAMUDERA KARUNIA RIZKY" (NPWP sama, jelas
     * perusahaan yang sama) sebagai DUA baris supplier -- beda spasi
     * saja sudah cukup untuk lolos dari `where('name', $name)->exists()`
     * yang sebelumnya dipakai di sini, dan keduanya akan terimpor sebagai
     * dua supplier terpisah.
     *
     * Menyeragamkan spasi ganda dan spasi sesudah titik ("CV. X" jadi
     * sama dengan "CV.X") cukup untuk menangkap kasus ini tanpa
     * pencocokan fuzzy yang lebih berat -- dan tetap dalam semangat
     * "cocokkan lewat nama", bukan menebak kesamaan dari isinya.
     */
    private function matchKey(string $name): string
    {
        $key = strtoupper(trim($name));
        $key = (string) preg_replace('/\s+/', ' ', $key);
        $key = (string) preg_replace('/\.\s+/', '.', $key);

        return $key;
    }

    /** @return array<string, string> matchKey() => nama asli, untuk satu tabel. */
    private function existingKeysFor(string $table, string $model): array
    {
        if (! isset($this->existingKeysCache[$table])) {
            $this->existingKeysCache[$table] = $model::query()
                ->pluck('name')
                ->mapWithKeys(fn (string $existingName): array => [$this->matchKey($existingName) => $existingName])
                ->all();
        }

        return $this->existingKeysCache[$table];
    }

    /**
     * Putuskan apakah sebuah nama sudah ada, mirip baris lain di sumber
     * yang sama (konflik, tidak dibuat otomatis), atau sungguhan baru --
     * dipakai `importProducts()`, `importMaterialCategoriesAndMaterials()`,
     * dan `importSuppliers()` supaya ketiganya menegakkan aturan yang
     * SAMA (Hafizh: "berlaku untuk semua master: barang, material,
     * supplier").
     *
     * @return 'exists'|'conflict'|'new'
     */
    private function classifyByName(string $table, string $model, string $name, string $legacyRef): string
    {
        $key = $this->matchKey($name);
        $existing = $this->existingKeysFor($table, $model);

        if (isset($existing[$key])) {
            $suffix = $existing[$key] === $name ? '' : " (cocok dengan \"{$existing[$key]}\" yang sudah ada)";
            $this->skipped[$table][] = "{$name}{$suffix}: sudah ada.";

            return 'exists';
        }

        if (isset($this->seenInBatch[$table][$key])) {
            $lain = $this->seenInBatch[$table][$key];
            $this->conflicts[$table][] = "{$name} ({$legacyRef}): mirip \"{$lain}\" di baris lain sumber ini (beda spasi/format) -- "
                .'kemungkinan duplikat, TIDAK dibuat otomatis, putuskan manual mana yang benar.';

            return 'conflict';
        }

        $this->seenInBatch[$table][$key] = $name;

        return 'new';
    }

    private function upsert(string $table, string $name, \Closure $create): void
    {
        $model = match ($table) {
            'product_categories' => ProductCategory::class,
            'grades' => Grade::class,
            'cattle_classes' => CattleClass::class,
            default => throw new \InvalidArgumentException("Tabel tidak dikenal: {$table}"),
        };

        if ($model::where('name', $name)->exists()) {
            $this->skipped[$table][] = "{$name}: sudah ada.";

            return;
        }

        $this->created[$table][] = $name;

        if ($this->option('apply')) {
            $create();
        }
    }

    private function printReport(bool $apply): void
    {
        $this->newLine();
        $this->info($apply ? 'Diterapkan (--apply):' : 'Dry-run -- tidak ada yang ditulis. Tambahkan --apply untuk benar-benar menulis.');
        $this->newLine();

        foreach ([
            'product_categories', 'grades', 'suppliers', 'cattle_classes',
            'products', 'material_categories', 'materials', 'product_materials',
        ] as $table) {
            $dibuat = $this->created[$table] ?? [];
            $dilewati = $this->skipped[$table] ?? [];
            $konflik = $this->conflicts[$table] ?? [];

            $this->line("<fg=cyan>{$table}</> -- dibuat: ".count($dibuat).', dilewati: '.count($dilewati).', konflik: '.count($konflik));

            foreach ($dibuat as $item) {
                $this->line("  <fg=green>+ {$item}</>");
            }
            foreach ($dilewati as $item) {
                $this->line("  <fg=gray>= {$item}</>");
            }
            foreach ($konflik as $item) {
                $this->line("  <fg=yellow>! {$item}</>");
            }
        }

        if ($this->notes !== []) {
            $this->newLine();
            $this->info('Catatan:');
            foreach ($this->notes as $note) {
                $this->line("  - {$note}");
            }
        }
    }
}
