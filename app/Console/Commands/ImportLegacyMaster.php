<?php

namespace App\Console\Commands;

use App\Models\CattleClass;
use App\Models\CustomerGroup;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\Supplier;
use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Impor master data MEKANIS dari snapshot legacy (issue #471), dengan ID LEGACY
 * DIPERTAHANKAN (keputusan Ayah, 13 Oktober 2026).
 *
 * "Mekanis" berarti: memindahkan apa yang sudah jelas artinya (kategori, grade,
 * produk, bahan, supplier, kelas sapi, grup pelanggan), BUKAN transaksi maupun
 * data yang masih butuh keputusan Owner (customer, segment -- sesi terpisah, lihat
 * `.agents/legacy-db.md`).
 *
 * ATURAN ID: setiap baris dimasukkan dengan `id` = id legacy-nya (cuts ->
 * product_categories, grade, barang -> products, rawcategory -> material_categories,
 * rawmate -> materials, supplier, cattle_class, groupcs -> customer_groups,
 * bom_rawmate -> product_materials). Kunci asing antartabel memakai id legacy apa
 * adanya (products.category_id = idcut, materials.material_category_id =
 * idrawcategory, dst). Auto-increment MySQL/SQLite sendiri melanjutkan dari
 * max(id). Pencocokan menurut NAMA hanya untuk melaporkan duplikat -- bukan
 * penentu id.
 *
 * MODE AMAN: kalau tabel tujuan sudah berisi id yang sama dengan isi BERBEDA, baris
 * itu DITOLAK dan dilaporkan -- tidak pernah menimpa diam-diam. Satu saja penolakan
 * membatalkan seluruh `--apply` (tidak ada yang ditulis), karena baris lain yang
 * bergantung padanya akan menunjuk ke id yang salah. Isi yang SAMA persis dilewati
 * (idempoten). Karena itu perintah ini dijalankan pada basis data yang bersih
 * (lihat prosedur database baru di `.agents/agents.md`).
 *
 * Baris legacy yang dilewati (kategori bahan operasional, nama kosong, kode produk
 * "-", dst) cukup tidak diimpor -- lubang id dibiarkan. Duplikat yang hanya beda
 * spasi/format: KEDUANYA diimpor dan dilaporkan sebagai konflik (Ayah memutuskan
 * yang mana dinonaktifkan). Nama kembar PERSIS tidak bisa diimpor dua kali (indeks
 * unik) dan dilaporkan.
 *
 * Sumbernya TEKS dump SQL (`--source`), dibaca lewat `LegacySqlDump` -- TIDAK PERNAH
 * dieksekusi sebagai query terhadap koneksi mana pun. Perintah ini sendiri menulis
 * lewat koneksi DEFAULT aplikasi, TIDAK PERNAH membuka koneksi ke basis data legacy.
 *
 * Default-nya laporan saja (`dry-run`) -- tidak menulis apa pun sampai `--apply`.
 */
class ImportLegacyMaster extends Command
{
    protected $signature = 'legacy:import-master
                            {--source= : Path ke berkas dump SQL legacy}
                            {--only= : Hanya langkah ini (pisah koma): kategori,grade,supplier,kelas-sapi,grup,produk,kategori-material,material,bom}
                            {--apply : Benar-benar menulis perubahan (default hanya laporan dry-run)}';

    protected $description = 'Impor master data (kategori, grade, produk, bahan, supplier, kelas sapi, grup pelanggan) dari snapshot legacy, DENGAN id legacy';

    /** Kategori bahan yang benar-benar produksi/kemasan -- lihat `.agents/legacy-db.md`. */
    private const MATERIAL_CATEGORY_WHITELIST = [
        'KARTON', 'PLASTIK', 'LABEL', 'MIKA', 'STYROFOAM', 'TRAY',
        'PACKAGING SUPPORT', 'MEAT PROCESSING', 'LAKBAN', 'PRODUKSI', 'PERCETAKAN',
    ];

    /**
     * Supplier legacy yang DIGABUNG (keputusan Ayah, 19 Oktober 2026): id lama => id yang dipakai. Importer riwayat
     * WAJIB memakai peta ini supaya transaksi lama menempel ke supplier yang selamat.
     */
    public const SUPPLIER_MERGES = [
        41 => 46, // CV. SAMUDERA KARUNIA RIZKY (alamat dan NPWP sama persis)
        14 => 26, // SINERGY INDOPACK MAKMUR
        9 => 32,  // INDOGUNA PT -> PT INDOGUNA UTAMA
        18 => 19, // E - COMMERCE -> MARKETPLACE
    ];

    /** Nama supplier yang selamat dari penggabungan: memakai nama lengkap dengan badan usahanya. */
    private const SUPPLIER_RENAMES = [26 => 'SINERGY INDOPACK MAKMUR PT'];

    /**
     * Bukan bahan produksi (operasional/aset menurut pemilahan Hafizh) tetapi sudah ada transaksinya di legacy:
     * tetap diimpor, satuan PCS, status NONAKTIF (keputusan Ayah, 19 Oktober 2026).
     */
    private const NON_MATERIAL_IDS = [56, 66, 74, 176, 177, 178];

    /** Satuan untuk bahan yang satuannya kosong di legacy (usul Ruby, disetujui Ayah): id lama => satuan. */
    private const UNIT_WHEN_EMPTY_BY_ID = [180 => 'PCS'];

    /** Kolom bermakna "baris yang sama" per tabel, dan kolom beraturan unik di basis data. */
    private const TABLES = [
        'product_categories' => ['model' => ProductCategory::class, 'identity' => ['name', 'prefix'], 'unique' => ['name', 'prefix']],
        'suppliers' => ['model' => Supplier::class, 'identity' => ['name'], 'unique' => ['name']],
        'cattle_classes' => ['model' => CattleClass::class, 'identity' => ['name'], 'unique' => ['name']],
        'customer_groups' => ['model' => CustomerGroup::class, 'identity' => ['name'], 'unique' => ['name']],
        'products' => ['model' => Product::class, 'identity' => ['code', 'name'], 'unique' => ['code', 'name']],
        'material_categories' => ['model' => MaterialCategory::class, 'identity' => ['name'], 'unique' => ['name']],
        'materials' => ['model' => Material::class, 'identity' => ['name'], 'unique' => ['name']],
        'product_materials' => ['model' => ProductMaterial::class, 'identity' => ['product_id', 'material_id'], 'unique' => []],
    ];

    /** @var array<string, array<int, string>> */
    private array $created = [];

    /** @var array<string, array<int, string>> */
    private array $skipped = [];

    /** @var array<string, array<int, string>> */
    private array $conflicts = [];

    /** @var array<string, array<int, string>> id bentrok: sudah ada dengan isi berbeda */
    private array $refusals = [];

    /** @var array<int, string> */
    private array $notes = [];

    /** @var array<int, string> id legacy => nama, untuk label laporan dan penilaian baris BOM */
    private array $materialCategoryNames = [];

    /** @var array<int, string> */
    private array $materialNames = [];

    /** @var array<int, string> baris laporan terjemahan grade legacy */
    private array $gradeLines = [];

    /**
     * Id legacy yang SETELAH jalan ini benar-benar ada di tabelnya (dibuat, atau sudah
     * ada dengan isi sama) -- tempat baris lain memeriksa kunci asingnya.
     *
     * @var array<string, array<int, true>>
     */
    private array $valid = [];

    /**
     * Nilai kolom unik yang sudah diklaim dalam jalan ini: tabel => kolom => nilai => id.
     *
     * @var array<string, array<string, array<string, int>>>
     */
    private array $claimed = [];

    /**
     * Kunci pencocokan longgar yang sudah terlihat (basis data + sumber): tabel => kunci => [id, nama].
     *
     * @var array<string, array<string, array{0: int, 1: string}>>
     */
    private array $looseSeen = [];

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

        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $unknown = array_diff($only, ['kategori', 'grade', 'supplier', 'kelas-sapi', 'grup', 'produk', 'kategori-material', 'material', 'bom']);

        if ($unknown !== []) {
            $this->error('--only tidak dikenal: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $run = function () use ($sql, $apply, $only): void {
            $steps = [
                'kategori' => fn () => $this->importProductCategories($sql),
                'grade' => fn () => $this->verifyGradeMap($sql),
                'supplier' => fn () => $this->importSuppliers($sql),
                'kelas-sapi' => fn () => $this->importCattleClasses($sql),
                'grup' => fn () => $this->importCustomerGroups($sql),
                'produk' => fn () => $this->importProducts($sql),
                'kategori-material' => fn () => $this->importMaterialCategories($sql),
                'material' => function () use ($sql, $only) {
                    if (! in_array('kategori-material', $only, true)) {
                        $this->importMaterialCategories($sql);
                    }
                    $this->importMaterials($sql);
                },
                'bom' => fn () => $this->importProductMaterials($sql),
            ];

            foreach ($steps as $name => $step) {
                if ($only === [] || in_array($name, $only, true)) {
                    $step();
                }
            }

            // Satu penolakan saja membatalkan seluruh penulisan.
            if ($apply && $this->refusals !== []) {
                throw new \RuntimeException('refused');
            }
        };

        try {
            $apply ? DB::transaction($run) : $run();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'refused') {
                throw $e;
            }
        }

        $refused = $this->refusals !== [];

        $this->printReport($apply, $refused);

        return $refused ? self::FAILURE : self::SUCCESS;
    }

    // =========================================================================
    // cuts -> product_categories
    // =========================================================================

    private function importProductCategories(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'cuts') as $row) {
            $id = (int) $row['idcut'];
            $name = $this->clean($row['nmcut']);

            if ($name === '') {
                $this->conflicts['product_categories'][] = "idcut={$id}: nama kosong, dilewati.";

                continue;
            }

            // `prefix` tidak ada padanan LANGSUNG di `cuts` -- tapi `ProductResource::generateNextCode()`
            // menyusun kode produk dari `prefix.sprintf('%03d').00` (mis. prefix 1 -> "100100"), PERSIS
            // pola `kdbarang` legacy (digit pertamanya `idcut`). `idcut` DIPAKAI sebagai prefix.
            $this->place('product_categories', $id, ['name' => $name, 'prefix' => $id], "{$name} (prefix {$id})", $name);
        }
    }

    // =========================================================================
    // grade -> TIDAK diimpor; diverifikasi terhadap tabel terjemahan (gudang, grade)
    // =========================================================================

    /**
     * Grade legacy menggabungkan gudang dan kondisi, jadi TIDAK diimpor sebagai baris (keputusan Owner,
     * 16 Okt 2026, #535). Di sini hanya diperiksa: setiap grade legacy harus ada di `LegacyGradeMap` dengan
     * nama yang diharapkan (selain itu DITOLAK), dan gudang/grade baru yang dituju harus ada di aplikasi.
     */
    private function verifyGradeMap(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'grade') as $row) {
            $id = (int) $row['idgrade'];
            $name = $this->clean($row['nmgrade']);

            if (! LegacyGradeMap::knows($id)) {
                $this->refusals['grades'][] = "idgrade={$id} ({$name}): di luar tabel terjemahan -- DITOLAK.";

                continue;
            }

            if ($name !== LegacyGradeMap::legacyName($id)) {
                $this->refusals['grades'][] = "idgrade={$id}: bernama \"{$name}\", tabel terjemahan mengharapkan \"".LegacyGradeMap::legacyName($id).'" -- DITOLAK.';

                continue;
            }

            [, $warehouse, $grade] = LegacyGradeMap::MAP[$id];
            $this->gradeLines[] = "{$name} (idgrade {$id}) -> gudang {$warehouse} + grade {$grade}";
        }

        foreach (LegacyGradeMap::appSideProblems() as $problem) {
            $this->notes[] = "Grade: {$problem}; terjemahan grade legacy belum bisa dipakai untuk stok awal/dokumen.";
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

        $extraGoods = [];
        foreach ($rows as $row) {
            $to = self::SUPPLIER_MERGES[(int) $row['idsupplier']] ?? null;
            $goods = trim((string) ($row['jenis_usaha'] ?? ''));

            if ($to !== null && $goods !== '') {
                $extraGoods[$to][] = $goods;
            }
        }

        foreach ($rows as $row) {
            $id = (int) $row['idsupplier'];
            $name = self::SUPPLIER_RENAMES[$id] ?? \App\Support\TidyName::company($row['nmsupplier']);

            if (isset(self::SUPPLIER_MERGES[$id])) {
                $this->notes[] = "Supplier digabung: idsupplier={$id} ({$name}) -> id ".self::SUPPLIER_MERGES[$id].' (tidak dibuat sendiri).';

                continue;
            }

            if ($name === '') {
                $this->conflicts['suppliers'][] = "idsupplier={$id}: nama kosong, dilewati.";

                continue;
            }

            $npwp = trim((string) ($row['npwp'] ?? ''));
            if ($npwp !== '' && $npwp !== '-') {
                $this->conflicts['suppliers'][] = "{$name}: NPWP legacy \"{$npwp}\" tidak diimpor -- Supplier swmrf tidak punya kolom NPWP.";
            }

            $this->place('suppliers', $id, ['name' => $name], $name, $name, [
                'address' => trim((string) ($row['alamat'] ?? '')) ?: '-',
                'pic' => '-',
                'phone' => trim((string) ($row['telepon'] ?? '')) ?: null,
                'top_days' => 0,
                'supplied_goods' => collect([trim((string) ($row['jenis_usaha'] ?? '')), ...($extraGoods[$id] ?? [])])
                    ->filter()->unique(fn ($g) => strtoupper($g))->implode('; ') ?: null,
            ]);
        }
    }

    // =========================================================================
    // cattle_class -> cattle_classes
    // =========================================================================

    private function importCattleClasses(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'cattle_class') as $row) {
            $id = (int) $row['idclass'];
            $name = $this->clean($row['class_name']);

            if ($name === '') {
                $this->conflicts['cattle_classes'][] = "idclass={$id}: nama kosong, dilewati.";

                continue;
            }

            $this->place('cattle_classes', $id, ['name' => $name], $name, $name);
        }
    }

    // =========================================================================
    // groupcs -> customer_groups
    // =========================================================================

    private function importCustomerGroups(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'groupcs') as $row) {
            $id = (int) $row['idgroup'];
            $name = $this->clean($row['nmgroup']);

            if ($name === '') {
                $this->conflicts['customer_groups'][] = "idgroup={$id}: nama kosong, dilewati.";

                continue;
            }

            // `trading_terms_percent` dan penanda acuan costing TIDAK datang dari legacy
            // (kolom `terms` kosong di legacy); diisi `legacy:import-prices`.
            $this->place('customer_groups', $id, ['name' => $name], $name, $name);
        }
    }

    // =========================================================================
    // barang -> products
    // =========================================================================

    private function importProducts(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'barang') as $row) {
            $id = (int) $row['idbarang'];
            $name = $this->clean($row['nmbarang']);
            $code = trim((string) ($row['kdbarang'] ?? ''));
            $categoryId = (int) $row['idcut'];

            if ($name === '') {
                $this->conflicts['products'][] = "idbarang={$id}: nama kosong, dilewati.";

                continue;
            }

            // Beberapa baris legacy memakai '-' sebagai kode -- bukan kode yang sungguhan, dan
            // `products.code` UNIK. Tidak ada dasar menebak kode yang benar: dilaporkan.
            if ($code === '' || $code === '-') {
                $this->conflicts['products'][] = "{$name} (idbarang={$id}): kode legacy kosong/\"-\", dilewati -- perlu kode manual.";

                continue;
            }

            if (! isset($this->valid['product_categories'][$categoryId])) {
                $this->conflicts['products'][] = "{$name} ({$code}): kategori (idcut={$categoryId}) tidak ada/tidak diimpor, produk dilewati.";

                continue;
            }

            // kodeinduk & karton/drylog/plastik dicatat ke CATATAN produk, bukan diresolusi ke
            // parent_id/structure_type (hierarki BOM lama yang butuh keputusan Owner tersendiri).
            $noteParts = [];
            foreach (['kodeinduk' => 'kodeinduk legacy', 'karton' => 'karton', 'drylog' => 'drylog', 'plastik' => 'plastik'] as $column => $label) {
                if (! empty($row[$column])) {
                    $noteParts[] = "{$label}: {$row[$column]}";
                }
            }

            $this->place('products', $id, ['code' => $code, 'name' => $name], "{$name} ({$code})", $name, [
                'category_id' => $categoryId,
                'structure_type' => 'main',
                'is_active' => true,
                'legacy_note' => $noteParts === [] ? null : ('Legacy -- '.implode('; ', $noteParts)),
            ]);
        }
    }

    // =========================================================================
    // rawcategory -> material_categories, rawmate -> materials
    // =========================================================================

    private function importMaterialCategories(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'rawcategory') as $row) {
            $id = (int) $row['idrawcategory'];
            $name = $this->clean($row['nmcategory']);

            // Kategori operasional (ATK, INTERNET, dll) SENGAJA tidak diimpor -- bukan konflik, melainkan cakupan.
            if (! in_array($name, self::MATERIAL_CATEGORY_WHITELIST, true)) {
                continue;
            }

            if ($this->place('material_categories', $id, ['name' => $name], $name, $name) !== 'refused') {
                $this->materialCategoryNames[$id] = $name;
            }
        }
    }

    private function importMaterials(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'rawmate') as $row) {
            $id = (int) $row['idrawmate'];
            $name = $this->clean($row['nmrawmate']);
            $categoryId = (int) $row['idrawcategory'];

            // Bahan di kategori yang tidak diimpor tidak ikut -- cakupan, bukan konflik.
            if (! isset($this->valid['material_categories'][$categoryId])) {
                continue;
            }

            if ($name === '') {
                $this->conflicts['materials'][] = "idrawmate={$id}: nama kosong, dilewati.";

                continue;
            }

            $unit = $this->clean($row['unit'] ?? '');
            $active = true;

            if ($unit === '') {
                if (in_array($id, self::NON_MATERIAL_IDS, true)) {
                    $unit = 'PCS';
                    $active = false;
                } elseif (isset(self::UNIT_WHEN_EMPTY_BY_ID[$id])) {
                    $unit = self::UNIT_WHEN_EMPTY_BY_ID[$id];
                } elseif (($this->materialCategoryNames[$categoryId] ?? '') === 'MEAT PROCESSING') {
                    $unit = preg_match('/^(KECAP|SAUS|MINYAK)\b/', $name) ? 'LITER' : 'KG';
                } else {
                    $this->conflicts['materials'][] = "{$name} (idrawmate={$id}): satuan kosong di legacy, dilewati -- material_unit_id wajib diisi.";

                    continue;
                }
            }

            $category = $this->materialCategoryNames[$categoryId] ?? '';

            // `code` = MTR + id (kdrawmate RMxxxx legacy selalu sama dengan id-nya, jadi RM0001 -> MTR001).
            // Satuan dicocokkan menurut nama (tabel satuan tidak punya id legacy).
            $status = $this->place('materials', $id, ['name' => $name], "{$name} ({$category}, {$unit})", $name, [
                'code' => 'MTR'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
                'material_category_id' => $categoryId,
                'min_stock' => (int) ($row['barmin'] ?? 0),
                'is_active' => $active,
            ], fn (): array => ['material_unit_id' => MaterialUnit::firstOrCreate(['name' => $unit])->id]);

            if (in_array($status, ['created', 'same'], true)) {
                $this->materialNames[$id] = $name;
            }
        }
    }

    // =========================================================================
    // bom_rawmate (is_active=1) -> product_materials
    // =========================================================================

    private function importProductMaterials(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'bom_rawmate') as $row) {
            if ($row['is_active'] !== '1') {
                continue;
            }

            $id = (int) $row['idbom'];
            $productId = (int) $row['idbarang'];
            $materialId = (int) $row['idrawmate'];

            if (! isset($this->valid['products'][$productId])) {
                $this->conflicts['product_materials'][] = "idbom={$id}: idbarang={$productId} tidak ada/tidak diimpor sebagai produk, baris BOM dilewati.";

                continue;
            }

            if (! isset($this->valid['materials'][$materialId])) {
                $this->conflicts['product_materials'][] = "idbom={$id}: idrawmate={$materialId} tidak ada/tidak diimpor sebagai material (kategori bukan produksi/kemasan?), baris BOM dilewati.";

                continue;
            }

            // Pasangan produk + material harus unik (`product_materials`); pasangan kembar di legacy dilaporkan.
            $pair = "{$productId}+{$materialId}";
            if (isset($this->claimed['product_materials']['pair'][$pair]) && $this->claimed['product_materials']['pair'][$pair] !== $id) {
                $this->conflicts['product_materials'][] = "idbom={$id}: pasangan produk {$productId} + material {$materialId} sudah ada di idbom={$this->claimed['product_materials']['pair'][$pair]}, dilewati.";

                continue;
            }

            // Pasangan yang sudah dipakai baris BOM lain di basis data (id berbeda) juga tidak bisa dimasukkan.
            if (ProductMaterial::query()->where('product_id', $productId)->where('material_id', $materialId)->where('id', '!=', $id)->exists()) {
                $this->conflicts['product_materials'][] = "idbom={$id}: pasangan produk {$productId} + material {$materialId} sudah ada di basis data dengan id lain, dilewati.";

                continue;
            }

            // `basis` DEFAULT 'box' -- `bom_rawmate` tidak pernah menyatakan dasar hitungnya sendiri.
            // PLASTIK/LABEL/MIKA biasanya membungkus per POTONG, jadi baris itu ditandai untuk ditinjau --
            // TETAP diimpor dengan basis 'box', bukan ditebak jadi 'piece' diam-diam.
            $materialName = $this->materialNames[$materialId] ?? '';
            $needsReview = str_contains($materialName, 'PLASTIK')
                || str_contains($materialName, 'LABEL')
                || str_contains($materialName, 'MIKA');

            $label = "produk {$productId} + material {$materialId} (qty {$row['qty']}, basis box)".($needsReview ? ' -- TINJAU: mungkin per-pcs, bukan per-box' : '');

            $status = $this->place('product_materials', $id, ['product_id' => $productId, 'material_id' => $materialId], $label, null, [
                'quantity' => (int) $row['qty'],
                'basis' => 'box',
            ]);

            if ($status !== 'refused') {
                $this->claimed['product_materials']['pair'][$pair] = $id;
            }
        }
    }

    // =========================================================================
    // Bantuan
    // =========================================================================

    /** Nama/teks legacy: huruf besar, rapikan ujung. Spasi di dalam TIDAK diubah (duplikat beda spasi dilaporkan). */
    private function clean(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    /**
     * Kunci pencocokan longgar -- BUKAN nama yang disimpan. Spasi ganda dan spasi sesudah titik
     * diseragamkan ("CV. X" = "CV.X") supaya duplikat beda spasi/format TERLAPOR. Hanya untuk laporan.
     */
    private function matchKey(string $name): string
    {
        return \App\Support\LooseName::key($name);
    }

    /**
     * Tempatkan satu baris legacy di tabelnya DENGAN id legacy.
     *
     * - id sudah ada, isi sama -> dilewati (idempoten), id tercatat valid;
     * - id sudah ada, isi BERBEDA -> DITOLAK (dilaporkan; seluruh --apply dibatalkan);
     * - id kosong -> dibuat (hanya bila --apply). Nilai kolom unik yang sudah terpakai di id lain
     *   (nama kembar persis) -> dilewati dan dilaporkan; yang hanya mirip -> tetap dibuat, dilaporkan.
     *
     * @param  array<string, mixed>  $identity  kolom yang menentukan "baris yang sama"
     * @param  array<string, mixed>  $extra  kolom lain saat membuat
     * @param  (\Closure(): array<string, mixed>)|null  $lazy  kolom yang baru dihitung saat menulis
     * @return 'created'|'same'|'refused'|'conflict'
     */
    private function place(string $table, int $id, array $identity, string $label, ?string $looseName, array $extra = [], ?\Closure $lazy = null): string
    {
        $config = self::TABLES[$table];
        /** @var class-string<Model> $model */
        $model = $config['model'];

        $existing = $model::query()->find($id);

        if ($existing) {
            foreach ($identity as $column => $value) {
                if ($this->clean($existing->getAttribute($column)) !== $this->clean($value)) {
                    $theirs = collect($identity)->map(fn ($v, $c) => $c.'='.$existing->getAttribute($c))->implode(', ');
                    $this->refusals[$table][] = "id {$id}: sudah berisi [{$theirs}], legacy [{$label}] -- DITOLAK (tidak ditimpa).";

                    return 'refused';
                }
            }

            $this->skipped[$table][] = "id {$id}: {$label} sudah ada.";
            $this->valid[$table][$id] = true;
            $this->claimBatch($table, $identity, $id);

            return 'same';
        }

        // Kolom unik yang sudah dipakai id lain (basis data atau baris sebelumnya di sumber ini).
        foreach ($config['unique'] as $column) {
            $value = (string) ($identity[$column] ?? $extra[$column] ?? '');
            $owner = $this->claimed[$table][$column][$this->clean($value)] ?? null;

            if ($owner === null && $value !== '' && $model::query()->where($column, $value)->where('id', '!=', $id)->exists()) {
                $owner = (int) $model::query()->where($column, $value)->value('id');
            }

            if ($owner !== null && $owner !== $id) {
                $this->conflicts[$table][] = "{$label} (id {$id}): {$column} \"{$value}\" sama persis dengan id {$owner}, tidak diimpor (nilai unik).";

                return 'conflict';
            }
        }

        // Duplikat yang hanya mirip (beda spasi/format): tetap diimpor, dilaporkan.
        if ($looseName !== null) {
            $key = $this->matchKey($looseName);
            $this->seedLoose($table, $model);

            if (isset($this->looseSeen[$table][$key]) && $this->looseSeen[$table][$key][0] !== $id) {
                [$otherId, $otherName] = $this->looseSeen[$table][$key];
                $this->conflicts[$table][] = "{$label} (id {$id}): mirip \"{$otherName}\" (id {$otherId}) -- beda spasi/format, kemungkinan duplikat. "
                    .'KEDUANYA diimpor (id legacy dipertahankan); putuskan mana yang dinonaktifkan.';
            }

            $this->looseSeen[$table][$key] ??= [$id, $looseName];
        }

        $this->created[$table][] = "id {$id}: {$label}";
        $this->valid[$table][$id] = true;
        $this->claimBatch($table, $identity + $extra, $id);

        if ($this->option('apply')) {
            $attributes = $identity + $extra + ($lazy ? $lazy() : []);
            $model::unguarded(fn () => $model::create(['id' => $id] + $attributes));
        }

        return 'created';
    }

    /** Catat nilai kolom unik baris ini sebagai milik $id. */
    private function claimBatch(string $table, array $values, int $id): void
    {
        foreach (self::TABLES[$table]['unique'] as $column) {
            if (isset($values[$column])) {
                $this->claimed[$table][$column][$this->clean($values[$column])] = $id;
            }
        }
    }

    /** Isi kunci longgar dengan baris yang SUDAH ada di basis data (sekali per tabel). */
    private function seedLoose(string $table, string $model): void
    {
        if (isset($this->looseSeen[$table])) {
            return;
        }

        $this->looseSeen[$table] = [];

        if (! in_array('name', self::TABLES[$table]['identity'], true)) {
            return;
        }

        foreach ($model::query()->orderBy('id')->get(['id', 'name']) as $row) {
            $this->looseSeen[$table][$this->matchKey((string) $row->name)] ??= [(int) $row->id, (string) $row->name];
        }
    }

    private function printReport(bool $apply, bool $refused): void
    {
        $this->newLine();

        if ($refused) {
            $this->error($apply
                ? 'DIBATALKAN -- ada id legacy yang bentrok dengan isi berbeda; TIDAK ADA yang ditulis. Pakai basis data yang bersih.'
                : 'Dry-run -- ada id legacy yang bentrok dengan isi berbeda; --apply akan DIBATALKAN. Pakai basis data yang bersih.');
        } else {
            $this->info($apply ? 'Diterapkan (--apply), id legacy dipertahankan:' : 'Dry-run -- tidak ada yang ditulis. Tambahkan --apply untuk benar-benar menulis.');
        }
        $this->newLine();

        foreach (array_keys(self::TABLES) as $table) {
            $dibuat = $this->created[$table] ?? [];
            $dilewati = $this->skipped[$table] ?? [];
            $konflik = $this->conflicts[$table] ?? [];
            $ditolak = $this->refusals[$table] ?? [];

            $this->line("<fg=cyan>{$table}</> -- dibuat: ".count($dibuat).', sama (dilewati): '.count($dilewati).', konflik: '.count($konflik).', DITOLAK: '.count($ditolak));

            foreach ($ditolak as $item) {
                $this->line("  <fg=red>x {$item}</>");
            }
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

        $this->line('<fg=cyan>grades</> -- TIDAK diimpor sebagai baris; diterjemahkan ke (gudang, grade): '.count($this->gradeLines).' terpetakan, DITOLAK: '.count($this->refusals['grades'] ?? []));
        foreach ($this->refusals['grades'] ?? [] as $item) {
            $this->line("  <fg=red>x {$item}</>");
        }
        foreach ($this->gradeLines as $item) {
            $this->line("  <fg=gray>= {$item}</>");
        }

        $this->newLine();
        $this->info('Catatan:');
        foreach ($this->notes as $note) {
            $this->line("  - {$note}");
        }
        $this->line('  - Id legacy dipertahankan; auto-increment melanjutkan dari max(id). Baris yang dilewati meninggalkan lubang id.');
    }
}
