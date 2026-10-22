<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-sales-returns`: retur penjualan legacy -> sales_returns + sales_return_items. Fixture dikarang sendiri.
 */
class ImportLegacySalesReturnsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

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

    private function retur(int $id, string $number, int $customer = 6, string $note = '-', int $user = 5): string
    {
        return $this->insert('returjual', ['idreturjual' => $id, 'returnnumber' => $number, 'returdate' => '2026-08-04', 'idcustomer' => $customer, 'note' => $note, 'idusers' => $user, 'creatime' => '2026-08-04 09:00:00', 'is_deleted' => 0]);
    }

    private function line(int $id, int $retur, string $barcode, ?string $grade = '1', string $pcs = '2', int $product = 21): string
    {
        return $this->insert('returjualdetail', ['idreturjualdetail' => $id, 'idreturjual' => $retur, 'idgrade' => $grade, 'idbarang' => $product, 'kdbarcode' => $barcode, 'qty' => '12.50', 'pcs' => $pcs, 'pod' => '2026-07-30', 'creatime' => '2026-08-04 09:05:00', 'is_deleted' => 0]);
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

        foreach ([6 => 'LION DCA', 143 => 'SHERLINDO'] as $id => $name) {
            DB::table('customers')->insert(['id' => $id, 'name' => $name, 'customer_group_id' => $group, 'customer_segment_id' => $segment, 'address' => '-', 'top' => 0, 'default_discount' => 0, 'invoice_exchange' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Barcode retur yang kemudian sudah dipakai sebagai bahan repack di aplikasi baru.
        $repack = DB::table('repacks')->insertGetId(['doc_no' => 'RP-1', 'repack_date' => '2026-08-10', 'status' => 'LOCKED', 'kunci' => 1, 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('repack_materials')->insert(['repack_id' => $repack, 'product_id' => 21, 'warehouse_id' => 1, 'grade_id' => 1, 'barcode' => '6240730000000002', 'weight' => 12.5, 'pack_date' => '2026-07-30', 'created_at' => $now, 'updated_at' => $now]);

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-rt-').'.sql';
        $this->master = tempnam(sys_get_temp_dir(), 'legacy-master-').'.sql';

        file_put_contents($this->master, $this->ddl('customers', 'idcustomer int, nama_customer varchar(100), tanggal_update datetime')
            .$this->insert('customers', ['idcustomer' => 6, 'nama_customer' => 'LION DCA', 'tanggal_update' => '2023-01-01 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 18, 'nama_customer' => 'SHERLINDO MAKSIMA SUKSES PT', 'tanggal_update' => '2023-08-04 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 143, 'nama_customer' => 'SHERLINDO', 'tanggal_update' => '2024-03-29 00:00:00']));

        file_put_contents($this->path, $this->ddl('returjual', 'idreturjual int, returnnumber varchar(30), returdate date, idcustomer int, note varchar(255), idusers int, creatime datetime, is_deleted int')
            .$this->ddl('returjualdetail', 'idreturjualdetail int, idreturjual int, idgrade int, idbarang int, kdbarcode varchar(30), qty decimal(6,2), pcs varchar(5), pod date, creatime datetime, is_deleted int')
            .$this->retur(1, 'RJ-1', 6, 'tolakan lion').$this->line(1, 1, '6240730000000001').$this->line(2, 1, '6240730000000002', null, '').$this->line(3, 1, '1240730000000003', '2')
            .$this->retur(2, 'RJ-1', 18, '-', 99).$this->line(4, 2, '6240730000000004')
            .$this->retur(3, 'RJ-3'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->master);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-sales-returns', ['--source' => $this->path, '--master' => $this->master] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 3')->expectsOutputToContain('BARU  sales_returns')->assertSuccessful();

        $this->assertSame(0, DB::table('sales_returns')->count());
    }

    /** @test */
    public function headers_are_paired_and_a_duplicate_number_gets_a_suffix(): void
    {
        $this->importer(true)->assertSuccessful();

        $one = DB::table('sales_returns')->where('return_number', 'RJ-1')->first();
        $this->assertSame('2026-08-04', $one->return_date);
        $this->assertSame(6, (int) $one->customer_id);
        $this->assertSame('tolakan lion', $one->note);
        $this->assertSame('Approved', $one->status);
        $this->assertSame(5, (int) $one->created_by);
        $this->assertSame('2026-08-04 09:00:00', $one->created_at);
        $this->assertNull($one->delivery_order_id);
        $this->assertSame(0.0, (float) $one->credit_amount);

        $two = DB::table('sales_returns')->where('return_number', 'RJ-1-2')->first();
        $this->assertSame(143, (int) $two->customer_id, 'pelanggan 18 digabung ke 143');
        $this->assertNull($two->note, 'catatan "-" dianggap kosong');
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $two->created_by);
        $this->assertNotNull(DB::table('sales_returns')->where('return_number', 'RJ-3')->first(), 'retur tanpa barcode tetap dibawa');
    }

    /** @test */
    public function items_are_paired_with_grade_warehouse_origin_and_repack_flag(): void
    {
        $this->importer(true)->assertSuccessful();

        $id = DB::table('sales_returns')->where('return_number', 'RJ-1')->value('id');
        $a = DB::table('sales_return_items')->where(['sales_return_id' => $id, 'barcode' => '6240730000000001'])->first();
        $this->assertSame(21, (int) $a->product_id);
        $this->assertSame(1, (int) $a->warehouse_id);
        $this->assertSame(1, (int) $a->grade_id);
        $this->assertSame(12.5, (float) $a->weight);
        $this->assertSame(2, (int) $a->qty_pcs);
        $this->assertSame('2026-07-30', $a->pack_date);
        $this->assertSame('R-RTRN', $a->origin, 'digit awal 6 = retur');
        $this->assertSame(0, (int) $a->is_repacked);

        $b = DB::table('sales_return_items')->where('barcode', '6240730000000002')->first();
        $this->assertSame(0, (int) $b->qty_pcs, 'pcs kosong -> 0');
        $this->assertSame(1, (int) $b->grade_id, 'grade kosong -> J01 (CHILL)');
        $this->assertSame(1, (int) $b->is_repacked, 'barcode sudah jadi bahan repack');

        $c = DB::table('sales_return_items')->where('barcode', '1240730000000003')->first();
        $this->assertSame(2, (int) $c->grade_id, 'J02 -> JGL + FROZEN');
        $this->assertSame('BONING', $c->origin);
    }

    /** @test */
    public function it_adds_no_stock_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 3')->assertSuccessful();

        $this->assertSame(3, DB::table('sales_returns')->count());
        $this->assertSame(4, DB::table('sales_return_items')->count());
        $this->assertSame(0, DB::table('beef_stocks')->count());
        $this->assertSame(3, DB::table('legacy_import_map')->where('entity', 'sales_return')->count());
    }

    /** @test */
    public function a_missing_customer_is_refused_and_nothing_is_written(): void
    {
        DB::table('customers')->where('id', 6)->delete();

        $this->importer(true)->expectsOutputToContain('pelanggan idcustomer=6 tidak ada')->assertFailed();

        $this->assertSame(0, DB::table('sales_returns')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
