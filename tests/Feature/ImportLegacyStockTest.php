<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-stock`: stok daging legacy (per barcode) -> beef_stocks. Fixture dikarang sendiri.
 */
class ImportLegacyStockTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    /** @return array<string, string> tabel => daftar kolom (nama tipe) */
    private function columns(): array
    {
        return [
            'stock' => 'id int, kdbarcode varchar(30), idgrade int, idbarang int, qty decimal(10,2), pcs varchar(5), ph varchar(5), pod date, origin varchar(2), creatime datetime, price varchar(10)',
            'labelboning' => 'idlabelboning int, idboning int, idbarang int, qty decimal(10,2), pcs varchar(5), ph varchar(5), packdate date, exp date, kdbarcode varchar(30), creatime datetime, iduser int, idgrade int, is_deleted int',
            'boning' => 'idboning int, batchboning varchar(20), idsupplier int, tglboning date',
            'detailhasil' => 'iddetailhasil int, idrepack int, kdbarcode varchar(30), idbarang int, idgrade int, qty decimal(10,2), pcs varchar(5), ph varchar(5), packdate date, exp date, creatime datetime, note varchar(20), is_deleted int',
            'repack' => 'idrepack int, norepack varchar(20), tglrepack date, note varchar(20), is_deleted int, kunci int, donumber varchar(20)',
            'grbeefdetail' => 'idgrbeefdetail int, idgr int, idbarang int, idgrade int, kdbarcode varchar(30), orderqty decimal(10,2), qty decimal(10,2), pcs varchar(5), pod date, creatime datetime, is_deleted int',
            'grbeef' => 'idgr int, idpo int, grnumber varchar(20), receivedate date, idsupplier int, note varchar(20), suppcode varchar(20), creatime datetime, is_deleted int',
            'stockin' => 'id int, kdbarcode varchar(30), idgrade int, idbarang int, qty decimal(10,2), pcs varchar(5), pod date, origin varchar(2), is_deleted int, creatime datetime, ph varchar(5)',
        ];
    }

    private function ddl(): string
    {
        $out = '';

        foreach ($this->columns() as $table => $list) {
            $parts = array_map(fn ($c) => '`'.strtok($c, ' ').'` '.substr($c, strpos($c, ' ') + 1), explode(', ', $list));
            $out .= "CREATE TABLE `{$table}` (\n  ".implode(",\n  ", $parts)."\n) ENGINE=InnoDB;\n";
        }

        return $out;
    }

    /** @param array<string, mixed> $r */
    private function insert(string $table, array $r): string
    {
        $cols = implode(',', array_map(fn ($k) => "`{$k}`", array_keys($r)));
        $vals = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : "'{$v}'", $r));

        return "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
    }

    private function fixture(): string
    {
        $base = ['creatime' => '2026-10-05 19:23:47', 'price' => null];

        return $this->ddl()
            .$this->insert('stock', ['id' => 1, 'kdbarcode' => '1453260912000046713', 'idgrade' => 2, 'idbarang' => 1, 'qty' => '17.40', 'pcs' => '3', 'ph' => '5.6', 'pod' => '2026-09-12', 'origin' => '1'] + $base)
            .$this->insert('stock', ['id' => 2, 'kdbarcode' => '3954260111000001267', 'idgrade' => 1, 'idbarang' => 2, 'qty' => '1.80', 'pcs' => '', 'ph' => null, 'pod' => '2025-12-04', 'origin' => '3'] + $base)
            .$this->insert('stock', ['id' => 3, 'kdbarcode' => '7251125000000000139', 'idgrade' => 2, 'idbarang' => 1, 'qty' => '12.88', 'pcs' => '', 'ph' => null, 'pod' => '2025-11-25', 'origin' => '7'] + $base)
            .$this->insert('stock', ['id' => 4, 'kdbarcode' => '635260804000000042', 'idgrade' => 2, 'idbarang' => 2, 'qty' => '6.72', 'pcs' => '', 'ph' => '5.5', 'pod' => '2026-05-15', 'origin' => '6'] + $base)
            .$this->insert('labelboning', ['idlabelboning' => 1, 'idboning' => 161, 'idbarang' => 1, 'qty' => '17.40', 'pcs' => '3', 'ph' => '5.6', 'packdate' => '2026-09-12', 'exp' => null, 'kdbarcode' => '1453260912000046713', 'creatime' => '2026-09-12 10:00:00', 'iduser' => 1, 'idgrade' => 2, 'is_deleted' => 0])
            .$this->insert('boning', ['idboning' => 161, 'batchboning' => 'BN26083', 'idsupplier' => 1, 'tglboning' => '2026-09-12'])
            .$this->insert('detailhasil', ['iddetailhasil' => 1, 'idrepack' => 630, 'kdbarcode' => '3954260111000001267', 'idbarang' => 2, 'idgrade' => 1, 'qty' => '1.80', 'pcs' => '', 'ph' => null, 'packdate' => '2025-12-04', 'exp' => '2026-12-04', 'creatime' => '2025-12-04 10:00:00', 'note' => '', 'is_deleted' => 0])
            .$this->insert('repack', ['idrepack' => 630, 'norepack' => 'RPC-26026', 'tglrepack' => '2025-12-04', 'note' => '', 'is_deleted' => 0, 'kunci' => 1, 'donumber' => ''])
            .$this->insert('stockin', ['id' => 1, 'kdbarcode' => '7251125000000000139', 'idgrade' => 2, 'idbarang' => 1, 'qty' => '12.88', 'pcs' => '', 'pod' => '2025-11-25', 'origin' => '7', 'is_deleted' => 0, 'creatime' => '2025-11-25 10:00:00', 'ph' => null]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        DB::table('warehouses')->insert([
            ['id' => 1, 'code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'PRM', 'name' => 'PERUM', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('grades')->insert([
            ['id' => 1, 'name' => 'CHILL', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'FROZEN', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'A', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'B', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'R', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $category = ProductCategory::create(['name' => 'PRIME CUT', 'prefix' => 1]);
        Product::create(['id' => 1, 'code' => '100207', 'name' => 'OPERIB WHOLE', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        Product::create(['id' => 2, 'code' => '700900', 'name' => 'DAGING A', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-stock-').'.sql';
        file_put_contents($this->path, $this->fixture());
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false)
    {
        return $this->artisan('legacy:import-stock', ['--source' => $this->path] + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_reports_pairs_and_writes_nothing(): void
    {
        $this->importer()
            ->expectsOutputToContain('DRY-RUN')
            ->expectsOutputToContain('dibuat: 4')
            ->expectsOutputToContain('BARU  beef_stocks')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('beef_stocks')->count());
    }

    /** @test */
    public function apply_pairs_each_column_as_agreed(): void
    {
        $this->importer(true)->assertSuccessful();

        $boning = DB::table('beef_stocks')->where('barcode', '1453260912000046713')->first();
        $this->assertSame(1, (int) $boning->product_id);
        $this->assertSame(1, (int) $boning->warehouse_id);
        $this->assertSame(2, (int) $boning->grade_id, 'J02 -> JGL + FROZEN');
        $this->assertSame(17.4, (float) $boning->weight);
        $this->assertSame(3, (int) $boning->qty_pcs);
        $this->assertSame(5.6, (float) $boning->ph_level);
        $this->assertSame('2026-09-12', $boning->pack_date);
        $this->assertSame('BN26083', $boning->batch_no);
        $this->assertSame('BONING', $boning->origin);
        $this->assertSame('IN_STOCK', $boning->status);

        $repack = DB::table('beef_stocks')->where('barcode', '3954260111000001267')->first();
        $this->assertSame(1, (int) $repack->grade_id, 'J01 -> JGL + CHILL');
        $this->assertSame('R-STCK', $repack->origin);
        $this->assertSame('RPC-26026', $repack->batch_no);
        $this->assertSame(0, (int) $repack->qty_pcs, 'pcs kosong -> 0');
        $this->assertNull($repack->ph_level);
        $this->assertSame('2026-12-04', $repack->exp_date, 'exp dibawa bila ada di tabel sumber');

        $manual = DB::table('beef_stocks')->where('barcode', '7251125000000000139')->first();
        $this->assertSame('-FOUND', $manual->origin, 'asal 7 (stock-in manual) -> -FOUND (keputusan Ayah)');
        $this->assertNull($manual->batch_no);

        $return = DB::table('beef_stocks')->where('barcode', '635260804000000042')->first();
        $this->assertSame('R-RTRN', $return->origin);
        $this->assertNull($return->batch_no, 'retur tanpa sumber: batch kosong');
        $this->assertSame(0, DB::table('beef_stock_movements')->count(), 'stok awal tidak membuat pergerakan');
    }

    /** @test */
    public function running_it_twice_creates_nothing_new_and_a_changed_row_is_refused(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sama (dilewati): 4')->assertSuccessful();

        DB::table('beef_stocks')->where('barcode', '635260804000000042')->update(['weight' => 99]);
        $this->importer(true)->expectsOutputToContain('DITOLAK')->assertFailed();
        $this->assertSame(99.0, (float) DB::table('beef_stocks')->where('barcode', '635260804000000042')->value('weight'), 'Tidak ditimpa.');
    }

    /** @test */
    public function a_product_that_was_not_imported_is_reported_and_skipped(): void
    {
        Product::whereKey(2)->delete();

        $this->importer(true)->expectsOutputToContain('produk idbarang=2 tidak ada')->assertSuccessful();

        $this->assertSame(2, DB::table('beef_stocks')->count());
    }
}
