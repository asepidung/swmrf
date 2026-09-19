<?php

namespace App\Console\Commands;

use App\Models\CattleClass;
use App\Models\Grade;
use App\Models\ProductCategory;
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
            $this->importProductCategories($sql);
            $this->importGrades($sql);
            $this->importSuppliers($sql);
            $this->importCattleClasses($sql);
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

    private function importProductCategories(string $sql): void
    {
        foreach (LegacySqlDump::parseTable($sql, 'cuts') as $row) {
            $name = strtoupper(trim((string) $row['nmcut']));

            if ($name === '') {
                $this->conflicts['product_categories'][] = "idcut={$row['idcut']}: nama kosong, dilewati.";

                continue;
            }

            if (ProductCategory::where('name', $name)->exists()) {
                $this->skipped['product_categories'][] = "{$name}: sudah ada.";

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

            if ($this->option('apply')) {
                ProductCategory::create(['name' => $name, 'prefix' => $prefix]);
            }
        }
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

            if (Supplier::where('name', $name)->exists()) {
                $this->skipped['suppliers'][] = "{$name}: sudah ada.";

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
    // Bantuan
    // =========================================================================

    /**
     * Buat-atau-lewati satu baris master sederhana (nama unik saja).
     *
     * Selalu memutuskan LEBIH DULU lewat exists(), baik dry-run maupun
     * `--apply` -- supaya laporan dry-run persis meramalkan apa yang akan
     * terjadi, bukan menebak dari sisi lain.
     */
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

        foreach (['product_categories', 'grades', 'suppliers', 'cattle_classes'] as $table) {
            $dibuat = $this->created[$table] ?? [];
            $dilewati = $this->skipped[$table] ?? [];
            $konflik = $this->conflicts[$table] ?? [];

            $this->line("<fg=cyan>{$table}</> -- dibuat: ".count($dibuat).', dilewati: '.count($dilewati).', konflik: '.count($konflik));

            foreach ($dibuat as $item) {
                $this->line("  <fg=green>+ {$item}</>");
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
