<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-material-stock`: saldo stok material legacy (penerimaan GR - pengeluaran, seperti layar stok legacy) -> material_stocks.
 * Fixture dikarang sendiri.
 */
class ImportLegacyMaterialStockTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private string $grs;

    private function ddl(array $cols): string
    {
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

    private function gr(int $id, int $deleted = 0): string
    {
        return $this->insert('grraw', ['idgr' => $id, 'is_deleted' => $deleted]);
    }

    private function grLine(int $id, int $gr, int $material, string $qty, int $deleted = 0): string
    {
        return $this->insert('grrawdetail', ['idgrdetail' => $id, 'idgr' => $gr, 'idrawmate' => $material, 'qty' => $qty, 'is_deleted' => $deleted]);
    }

    private function out(int $id, int $deleted = 0): string
    {
        return $this->insert('raw_stock_out', ['idstockout' => $id, 'nostockout' => 'SOM-'.$id, 'is_deleted' => $deleted]);
    }

    private function outLine(int $id, int $out, int $material, string $qty): string
    {
        return $this->insert('raw_stock_out_detail', ['iddetail' => $id, 'idstockout' => $out, 'idrawmate' => $material, 'qty' => $qty]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $unit = DB::table('material_units')->insertGetId(['name' => 'BOX', 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('material_categories')->insertGetId(['name' => 'KARTON', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([23 => 'KARTON A', 91 => 'KARTON B', 92 => 'KARTON C'] as $id => $name) {
            DB::table('materials')->insert(['id' => $id, 'code' => 'MTR'.$id, 'name' => $name, 'material_category_id' => $category, 'material_unit_id' => $unit, 'min_stock' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-ms-').'.sql';
        $this->grs = tempnam(sys_get_temp_dir(), 'legacy-gr-').'.sql';

        file_put_contents($this->path, $this->ddl([
            'raw_stock_out' => 'idstockout int, nostockout varchar(20), is_deleted int',
            'raw_stock_out_detail' => 'iddetail int, idstockout int, idrawmate int, qty decimal(12,2)',
            'rawmate' => 'idrawmate int, nmrawmate varchar(50), unit varchar(10)',
        ])
            .$this->insert('rawmate', ['idrawmate' => 23, 'nmrawmate' => 'KARTON A', 'unit' => 'Box'])
            .$this->insert('rawmate', ['idrawmate' => 91, 'nmrawmate' => 'KARTON B', 'unit' => 'Box'])
            .$this->insert('rawmate', ['idrawmate' => 92, 'nmrawmate' => 'KARTON C', 'unit' => 'Box'])
            .$this->insert('rawmate', ['idrawmate' => 500, 'nmrawmate' => 'SPIDOL', 'unit' => 'Pcs'])
            // pengeluaran material 23: 20 (dokumen hidup) dan 99 (dokumen dihapus, diabaikan)
            .$this->out(1).$this->out(2, 1).$this->outLine(1, 1, 23, '20').$this->outLine(2, 2, 23, '99')
            // material 91 keluar 9 (lebih besar dari masuk 3 -> saldo negatif); material 92 keluar 4 (habis); material 500 di luar material impor
            .$this->outLine(3, 1, 91, '9').$this->outLine(4, 1, 92, '4'));

        file_put_contents($this->grs, $this->ddl([
            'grraw' => 'idgr int, is_deleted int',
            'grrawdetail' => 'idgrdetail int, idgr int, idrawmate int, qty decimal(12,2), is_deleted int',
        ])
            // material 23: masuk 100 + 50; baris dihapus (7) dan GR yang dihapus (1000) diabaikan -> 150 - 20 = 130
            .$this->gr(1).$this->gr(2, 1)
            .$this->grLine(1, 1, 23, '100').$this->grLine(2, 1, 23, '50').$this->grLine(3, 1, 23, '7', 1).$this->grLine(4, 2, 23, '1000')
            .$this->grLine(5, 1, 91, '3').$this->grLine(6, 1, 92, '4').$this->grLine(7, 1, 500, '25'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->grs);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withGrs = true)
    {
        return $this->artisan('legacy:import-material-stock', ['--source' => $this->path] + ($withGrs ? ['--grs' => $this->grs] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_reports_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 1')->assertSuccessful();

        $this->assertSame(0, DB::table('material_stocks')->count());
    }

    /** @test */
    public function the_balance_is_receipts_minus_stock_outs_ignoring_deleted_rows_and_documents(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame(130.0, (float) DB::table('material_stocks')->where('material_id', 23)->value('qty'), '100 + 50 - 20; baris, GR, dan pengeluaran yang dihapus diabaikan');
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

    /** @test */
    public function both_snapshots_are_required(): void
    {
        $this->importer(true, false)->assertFailed();

        $this->assertSame(0, DB::table('material_stocks')->count());
    }
}
