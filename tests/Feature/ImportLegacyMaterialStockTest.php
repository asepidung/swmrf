<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-material-stock`: saldo stok material legacy -> material_stocks. Fixture dikarang sendiri.
 */
class ImportLegacyMaterialStockTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private function ddl(): string
    {
        $cols = [
            'stockraw' => 'id int, idtransaksi varchar(20), idrawmate int, qty decimal(12,2), creatime datetime, is_deleted int',
            'raw_stock_out' => 'idstockout int, nostockout varchar(20), tgl date, kegiatan varchar(20), ref_id int, ref_no varchar(20), kegiatan_note varchar(50), idusers int, createtime datetime, updatetime datetime, is_deleted int',
            'raw_stock_out_detail' => 'iddetail int, idstockout int, idrawmate int, qty decimal(12,2), note varchar(50)',
            'raw_usage' => 'idusage int, sumber varchar(20), idsumber int, idrawmate int, qty decimal(12,2), note varchar(50), iduser int, createtime datetime',
            'rawmate' => 'idrawmate int, kdrawmate varchar(10), nmrawmate varchar(50), idrawcategory int, stock int, unit varchar(10), barmin int',
        ];
        $out = '';

        foreach ($cols as $table => $list) {
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

    private function raw(int $id, int $material, string $qty, int $deleted = 0): string
    {
        return $this->insert('stockraw', ['id' => $id, 'idtransaksi' => '1260000000'.$id, 'idrawmate' => $material, 'qty' => $qty, 'creatime' => '2026-01-01 10:00:00', 'is_deleted' => $deleted]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $unit = DB::table('material_units')->insertGetId(['name' => 'BOX', 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('material_categories')->insertGetId(['name' => 'KARTON', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([23 => 'KARTON A', 91 => 'KARTON B', 92 => 'KARTON C'] as $id => $name) {
            DB::table('materials')->insert(['id' => $id, 'code' => 'MTR'.$id, 'name' => $name, 'material_category_id' => $category, 'material_unit_id' => $unit, 'min_stock' => 0, 'is_active' => 1, 'show_in_stock' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-ms-').'.sql';
        file_put_contents($this->path, $this->ddl()
            .$this->insert('rawmate', ['idrawmate' => 23, 'kdrawmate' => 'RM0023', 'nmrawmate' => 'KARTON A', 'idrawcategory' => 2, 'stock' => 1, 'unit' => 'Box', 'barmin' => 0])
            .$this->insert('rawmate', ['idrawmate' => 91, 'kdrawmate' => 'RM0091', 'nmrawmate' => 'KARTON B', 'idrawcategory' => 2, 'stock' => 1, 'unit' => 'Box', 'barmin' => 0])
            .$this->insert('rawmate', ['idrawmate' => 92, 'kdrawmate' => 'RM0092', 'nmrawmate' => 'KARTON C', 'idrawcategory' => 2, 'stock' => 1, 'unit' => 'Box', 'barmin' => 0])
            .$this->insert('rawmate', ['idrawmate' => 500, 'kdrawmate' => 'RM0500', 'nmrawmate' => 'SPIDOL', 'idrawcategory' => 26, 'stock' => 1, 'unit' => 'Pcs', 'barmin' => 0])
            // material 23: masuk 100 + 50 - penyesuaian 10 (negatif), dikurangi usage 5 dan stock_out 20 -> 115; baris dihapus (7) diabaikan
            .$this->raw(1, 23, '100').$this->raw(2, 23, '50').$this->raw(3, 23, '-10').$this->raw(4, 23, '7', 1)
            .$this->insert('raw_usage', ['idusage' => 1, 'sumber' => 'REPACK', 'idsumber' => 1, 'idrawmate' => 23, 'qty' => '5', 'note' => '', 'iduser' => 1, 'createtime' => '2025-12-20 10:00:00'])
            .$this->insert('raw_stock_out', ['idstockout' => 1, 'nostockout' => 'SOM-1', 'tgl' => '2026-02-01', 'kegiatan' => 'BONING', 'ref_id' => null, 'ref_no' => 'BN1', 'kegiatan_note' => '', 'idusers' => 1, 'createtime' => '2026-02-01 10:00:00', 'updatetime' => '2026-02-01 10:00:00', 'is_deleted' => 0])
            .$this->insert('raw_stock_out', ['idstockout' => 2, 'nostockout' => 'SOM-2', 'tgl' => '2026-02-02', 'kegiatan' => 'BONING', 'ref_id' => null, 'ref_no' => 'BN2', 'kegiatan_note' => '', 'idusers' => 1, 'createtime' => '2026-02-02 10:00:00', 'updatetime' => '2026-02-02 10:00:00', 'is_deleted' => 1])
            .$this->insert('raw_stock_out_detail', ['iddetail' => 1, 'idstockout' => 1, 'idrawmate' => 23, 'qty' => '20', 'note' => ''])
            .$this->insert('raw_stock_out_detail', ['iddetail' => 2, 'idstockout' => 2, 'idrawmate' => 23, 'qty' => '99', 'note' => ''])
            // material 91: saldo negatif kecil -> 0; material 92: saldo nol; material 500: di luar material impor
            .$this->raw(5, 91, '3').$this->insert('raw_usage', ['idusage' => 2, 'sumber' => 'BONING', 'idsumber' => 1, 'idrawmate' => 91, 'qty' => '9', 'note' => '', 'iduser' => 1, 'createtime' => '2025-12-21 10:00:00'])
            .$this->raw(6, 92, '4').$this->insert('raw_usage', ['idusage' => 3, 'sumber' => 'BONING', 'idsumber' => 2, 'idrawmate' => 92, 'qty' => '4', 'note' => '', 'iduser' => 1, 'createtime' => '2025-12-22 10:00:00'])
            .$this->raw(7, 500, '25'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false)
    {
        return $this->artisan('legacy:import-material-stock', ['--source' => $this->path] + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_reports_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 1')->assertSuccessful();

        $this->assertSame(0, DB::table('material_stocks')->count());
    }

    /** @test */
    public function the_balance_is_ledger_minus_usage_and_stock_out_ignoring_deleted_rows(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame(115.0, (float) DB::table('material_stocks')->where('material_id', 23)->value('qty'), '100 + 50 - 10 - 5 - 20; baris dan dokumen yang dihapus diabaikan');
        $this->assertSame(1, DB::table('material_stocks')->count(), 'saldo nol atau negatif tidak dibawa; material di luar impor tidak dibawa');
    }

    /** @test */
    public function negative_and_out_of_scope_balances_are_reported(): void
    {
        $this->importer()->expectsOutputToContain('KARTON B -6')->expectsOutputToContain('SPIDOL 25')->assertSuccessful();
    }

    /** @test */
    public function running_it_twice_does_not_overwrite_stock_changed_by_operations(): void
    {
        $this->importer(true)->assertSuccessful();
        DB::table('material_stocks')->where('material_id', 23)->update(['qty' => 50]);

        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 1')->assertSuccessful();

        $this->assertSame(50.0, (float) DB::table('material_stocks')->where('material_id', 23)->value('qty'));
        $this->assertSame(0, DB::table('material_stock_movements')->count(), 'tanpa catatan pergerakan');
    }
}
