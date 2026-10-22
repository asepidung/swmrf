<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:reconcile`: setelah impor, angka legacy dan aplikasi baru harus cocok; selisih sungguhan harus ketahuan. Fixture dikarang sendiri.
 */
class ReconcileLegacyTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $master;

    private function ddl(string $table, string $list): string
    {
        $parts = array_map(fn ($c) => '`'.strtok($c, ' ').'` '.substr($c, strpos($c, ' ') + 1), explode(', ', $list));

        return "CREATE TABLE `{$table}` (\n  ".implode(",\n  ", $parts)."\n) ENGINE=InnoDB;\n";
    }

    /** @param array<string, mixed> $r */
    private function insert(string $table, array $r): string
    {
        $cols = implode(',', array_map(fn ($k) => "`{$k}`", array_keys($r)));
        $vals = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : "'{$v}'", $r));

        return "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $user = User::create(['name' => 'Pembuat', 'username' => 'pembuat', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);

        DB::table('warehouses')->insert([['id' => 1, 'code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now], ['id' => 2, 'code' => 'PRM', 'name' => 'PERUM', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]]);
        DB::table('grades')->insert(array_map(fn ($i, $n) => ['id' => $i, 'name' => $n, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now], [1, 2, 3, 4, 5], ['CHILL', 'FROZEN', 'A', 'B', 'R']));
        $category = DB::table('product_categories')->insertGetId(['name' => 'PRIME CUT', 'prefix' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('products')->insert(['id' => 21, 'code' => '100207', 'name' => 'OPERIB WHOLE', 'category_id' => $category, 'structure_type' => 'main', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $segment = DB::table('customer_segments')->insertGetId(['name' => 'RETAIL', 'created_at' => $now, 'updated_at' => $now]);
        $group = DB::table('customer_groups')->insertGetId(['name' => 'REGULER', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => 6, 'name' => 'LION DCA', 'customer_group_id' => $group, 'customer_segment_id' => $segment, 'address' => '-', 'top' => 0, 'default_discount' => 0, 'invoice_exchange' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $this->dir = sys_get_temp_dir().'/legacy-recon-'.uniqid();
        mkdir($this->dir);
        $this->master = $this->dir.'/master.sql';

        file_put_contents($this->master, $this->ddl('customers', 'idcustomer int, nama_customer varchar(100), tanggal_update datetime').$this->insert('customers', ['idcustomer' => 6, 'nama_customer' => 'LION DCA', 'tanggal_update' => '2023-01-01 00:00:00']));

        file_put_contents($this->dir.'/SWM-retur-1.sql', $this->ddl('returjual', 'idreturjual int, returnnumber varchar(30), returdate date, idcustomer int, note varchar(255), idusers int, creatime datetime, is_deleted int')
            .$this->ddl('returjualdetail', 'idreturjualdetail int, idreturjual int, idgrade int, idbarang int, kdbarcode varchar(30), qty decimal(6,2), pcs varchar(5), pod date, creatime datetime, is_deleted int')
            .$this->insert('returjual', ['idreturjual' => 1, 'returnnumber' => 'RJ-1', 'returdate' => '2026-08-04', 'idcustomer' => 6, 'note' => '-', 'idusers' => 5, 'creatime' => '2026-08-04 09:00:00', 'is_deleted' => 0])
            .$this->insert('returjualdetail', ['idreturjualdetail' => 1, 'idreturjual' => 1, 'idgrade' => 1, 'idbarang' => 21, 'kdbarcode' => '6240730000000001', 'qty' => '12.50', 'pcs' => '2', 'pod' => '2026-07-30', 'creatime' => '2026-08-04 09:05:00', 'is_deleted' => 0])
            .$this->insert('returjualdetail', ['idreturjualdetail' => 2, 'idreturjual' => 1, 'idgrade' => 2, 'idbarang' => 21, 'kdbarcode' => '6240730000000002', 'qty' => '7.25', 'pcs' => '1', 'pod' => '2026-07-30', 'creatime' => '2026-08-04 09:05:00', 'is_deleted' => 0]));

        file_put_contents($this->dir.'/SWM-repack-1.sql', $this->ddl('repack', 'idrepack int, norepack varchar(30), tglrepack date, note varchar(255), idusers int, is_deleted int, dibuat datetime, kunci int')
            .$this->ddl('detailbahan', 'iddetailbahan int, idrepack int, barcode varchar(30), idbarang int, idgrade int, qty decimal(6,2), pcs int, pod date, origin int, creatime datetime')
            .$this->ddl('detailhasil', 'iddetailhasil int, idrepack int, kdbarcode varchar(30), idbarang int, idgrade int, qty decimal(6,2), pcs varchar(10), packdate date, exp date, creatime datetime, note varchar(100), is_deleted int')
            .$this->insert('repack', ['idrepack' => 1, 'norepack' => 'RP-1', 'tglrepack' => '2026-10-19', 'note' => '', 'idusers' => 5, 'is_deleted' => 0, 'dibuat' => '2026-10-19 08:00:00', 'kunci' => 1])
            .$this->insert('detailbahan', ['iddetailbahan' => 1, 'idrepack' => 1, 'barcode' => '1453260912000046713', 'idbarang' => 21, 'idgrade' => 2, 'qty' => '20.50', 'pcs' => 2, 'pod' => '2026-10-10', 'origin' => 1, 'creatime' => '2026-10-19 08:30:00'])
            .$this->insert('detailhasil', ['iddetailhasil' => 1, 'idrepack' => 1, 'kdbarcode' => '1453261019000000001', 'idbarang' => 21, 'idgrade' => 1, 'qty' => '10.25', 'pcs' => '3', 'packdate' => '2026-10-19', 'exp' => '', 'creatime' => '2026-10-19 09:00:00', 'note' => '', 'is_deleted' => 0]));

        $this->artisan('legacy:import-sales-returns', ['--source' => $this->dir.'/SWM-retur-1.sql', '--master' => $this->master, '--fallback-user' => 'cadangan', '--apply' => true])->assertSuccessful();
        $this->artisan('legacy:import-repacks', ['--source' => $this->dir.'/SWM-repack-1.sql', '--fallback-user' => 'cadangan', '--apply' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    /** @test */
    public function everything_matches_right_after_the_import(): void
    {
        $this->artisan('legacy:reconcile', ['--dir' => $this->dir, '--only' => 'retur,repack'])
            ->expectsOutputToContain('barcode retur')
            ->expectsOutputToContain('HASIL: SEMUA COCOK (8 pemeriksaan).')
            ->assertSuccessful();
    }

    /** @test */
    public function a_real_difference_is_reported_and_fails_the_command(): void
    {
        DB::table('sales_return_items')->where('barcode', '6240730000000002')->delete();

        $this->artisan('legacy:reconcile', ['--dir' => $this->dir, '--only' => 'retur,repack'])
            ->expectsOutputToContain('SELISIH -1')
            ->expectsOutputToContain('HASIL: ADA SELISIH di 2 dari 8 pemeriksaan.')
            ->assertFailed();
    }

    /** @test */
    public function a_document_created_after_the_import_does_not_disturb_the_numbers(): void
    {
        $now = now();
        $repack = DB::table('repacks')->insertGetId(['doc_no' => 'RP#26001', 'repack_date' => '2026-11-03', 'status' => 'OPEN', 'kunci' => 0, 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('repack_materials')->insert(['repack_id' => $repack, 'product_id' => 21, 'warehouse_id' => 1, 'grade_id' => 1, 'barcode' => 'NEW1', 'weight' => 99, 'pack_date' => '2026-11-03', 'created_at' => $now, 'updated_at' => $now]);

        $this->artisan('legacy:reconcile', ['--dir' => $this->dir, '--only' => 'repack'])->expectsOutputToContain('SEMUA COCOK')->assertSuccessful();
    }

    /** @test */
    public function the_folder_and_the_module_names_are_validated(): void
    {
        $this->artisan('legacy:reconcile', ['--dir' => $this->dir.'/tidak-ada'])->assertFailed();
        $this->artisan('legacy:reconcile', ['--dir' => $this->dir, '--only' => 'bogus'])->assertFailed();
    }
}
