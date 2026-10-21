<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-sales-orders`: riwayat Sales Order legacy -> sales_orders. Fixture dikarang sendiri.
 */
class ImportLegacySalesOrdersTest extends TestCase
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
        $vals = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : "'".str_replace("'", "\\'", (string) $v)."'", $r));

        return "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
    }

    private function so(int $id, string $number, string $progress, int $customer = 6, int $user = 5, string $note = '-'): string
    {
        return $this->insert('salesorder', ['idso' => $id, 'sonumber' => $number, 'idcustomer' => $customer, 'deliverydate' => '2026-10-21', 'po' => 'PO-'.$id, 'alamat' => 'JONGGOL', 'note' => $note, 'progress' => $progress, 'idusers' => $user, 'creatime' => '2026-10-20 08:33:17', 'is_deleted' => 0]);
    }

    private function line(int $id, int $so, int $product, string $weight = '200', string $price = '126000', ?string $discount = '0', string $notes = ''): string
    {
        return $this->insert('salesorderdetail', ['idsodetail' => $id, 'idso' => $so, 'idbarang' => $product, 'weight' => $weight, 'price' => $price, 'discount' => $discount, 'notes' => $notes]);
    }

    private function doc(int $id, int $so, string $status): string
    {
        return $this->insert('do', ['iddo' => $id, 'donumber' => 'DO-'.$id, 'idso' => $so, 'status' => $status, 'deliverydate' => '2026-10-21']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $user = User::create(['name' => 'Pembuat', 'username' => 'pembuat', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);

        $segment = DB::table('customer_segments')->insertGetId(['name' => 'RETAIL', 'created_at' => $now, 'updated_at' => $now]);
        $group = DB::table('customer_groups')->insertGetId(['name' => 'REGULER', 'created_at' => $now, 'updated_at' => $now]);

        foreach ([6 => 'LION DCA', 143 => 'SHERLINDO'] as $id => $name) {
            DB::table('customers')->insert(['id' => $id, 'name' => $name, 'customer_group_id' => $group, 'customer_segment_id' => $segment, 'address' => '-', 'top' => 0, 'default_discount' => 0, 'invoice_exchange' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $category = DB::table('product_categories')->insertGetId(['name' => 'PRIME CUT', 'prefix' => 1, 'created_at' => $now, 'updated_at' => $now]);

        foreach ([1 => '100100', 40 => '200100'] as $id => $code) {
            DB::table('products')->insert(['id' => $id, 'code' => $code, 'name' => 'PRODUK '.$id, 'category_id' => $category, 'structure_type' => 'main', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-so-').'.sql';
        $this->master = tempnam(sys_get_temp_dir(), 'legacy-master-').'.sql';

        // Peta penggabungan pelanggan: 18 (SHERLINDO MAKSIMA SUKSES PT) -> 143 (CUSTOMER_MERGES).
        file_put_contents($this->master, $this->ddl('customers', 'idcustomer int, nama_customer varchar(100), tanggal_update datetime')
            .$this->insert('customers', ['idcustomer' => 6, 'nama_customer' => 'LION DCA', 'tanggal_update' => '2023-01-01 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 18, 'nama_customer' => 'SHERLINDO MAKSIMA SUKSES PT', 'tanggal_update' => '2023-08-04 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 143, 'nama_customer' => 'SHERLINDO', 'tanggal_update' => '2024-03-29 00:00:00']));

        file_put_contents($this->path, $this->ddl('salesorder', 'idso int, sonumber varchar(30), idcustomer int, deliverydate date, po varchar(30), alamat varchar(100), note varchar(50), progress varchar(20), idusers int, creatime datetime, is_deleted int')
            .$this->ddl('salesorderdetail', 'idsodetail int, idso int, idbarang int, weight decimal(10,2), price decimal(15,2), discount varchar(10), notes varchar(50)')
            .$this->ddl('do', 'iddo int, donumber varchar(30), idso int, status varchar(20), deliverydate date')
            .$this->so(1, 'SO-1', 'Delivered').$this->line(1, 1, 1).$this->line(2, 1, 40, '10', '60000', '5')
            .$this->so(2, 'SO-2', 'On Delivery', 18, 99, 'catatan').$this->line(3, 2, 1).$this->doc(1, 2, 'Invoiced')
            .$this->so(3, 'SO-3', 'Waiting').$this->line(4, 3, 1)
            .$this->so(4, 'SO-4', 'Rejected').$this->line(5, 4, 1)
            .$this->so(5, 'SO-5', 'On Delivery').$this->line(6, 5, 1).$this->doc(2, 5, 'Unapproved')
            .$this->so(6, 'SO-6', 'Delivered').$this->line(7, 6, 40, '17', '0', '45000').$this->line(8, 6, 777, '1', '1000'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->master);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-sales-orders', ['--source' => $this->path, '--master' => $this->master] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 6')->expectsOutputToContain('BARU  sales_orders')->assertSuccessful();

        $this->assertSame(0, DB::table('sales_orders')->count());
    }

    /** @test */
    public function statuses_follow_the_legacy_meaning_and_stale_ones_are_fixed_by_their_delivery_orders(): void
    {
        $this->importer(true)->assertSuccessful();

        $status = fn (string $n) => DB::table('sales_orders')->where('so_number', $n)->value('status');
        $this->assertSame('completed', $status('SO-1'), 'Delivered');
        $this->assertSame('completed', $status('SO-2'), 'On Delivery yang semua DO-nya Invoiced = basi -> completed');
        $this->assertSame('waiting', $status('SO-3'));
        $this->assertSame('cancelled', $status('SO-4'), 'Rejected -> cancelled');
        $this->assertSame('on_delivery', $status('SO-5'), 'On Delivery dengan DO yang belum Invoiced tetap on_delivery');
        $this->assertStringContainsString('Ditolak di legacy', DB::table('sales_orders')->where('so_number', 'SO-4')->value('note'));
    }

    /** @test */
    public function columns_are_paired_as_agreed_and_a_merged_customer_uses_the_survivor(): void
    {
        $this->importer(true)->assertSuccessful();

        $so = DB::table('sales_orders')->where('so_number', 'SO-1')->first();
        $this->assertSame(6, (int) $so->customer_id);
        $this->assertSame('2026-10-21', $so->delivery_date);
        $this->assertSame('PO-1', $so->po_number);
        $this->assertSame('JONGGOL', $so->shipping_address);
        $this->assertNull($so->note, 'catatan "-" dianggap kosong');
        $this->assertSame(5, (int) $so->created_by);
        $this->assertSame('2026-10-20 08:33:17', $so->created_at);

        $merged = DB::table('sales_orders')->where('so_number', 'SO-2')->first();
        $this->assertSame(143, (int) $merged->customer_id, 'pelanggan 18 digabung ke 143');
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $merged->created_by);
        $this->assertStringContainsString('catatan', $merged->note);
    }

    /** @test */
    public function item_columns_discounts_and_non_imported_products_are_handled(): void
    {
        $this->importer(true)->assertSuccessful();

        $items = DB::table('sales_order_items')->where('sales_order_id', DB::table('sales_orders')->where('so_number', 'SO-1')->value('id'))->orderBy('id')->get();
        $this->assertSame(200, (int) $items[0]->weight);
        $this->assertSame(126000, (int) $items[0]->price);
        $this->assertSame(5, (int) $items[1]->discount, 'diskon 5 = persen');

        $six = DB::table('sales_orders')->where('so_number', 'SO-6')->first();
        $item = DB::table('sales_order_items')->where('sales_order_id', $six->id)->first();
        $this->assertSame(0, (int) $item->discount, 'diskon legacy > 100 (bukan persen) tidak dibawa');
        $this->assertStringContainsString('Diskon legacy 45000 tidak dibawa', $item->note);
        $this->assertSame(1, DB::table('sales_order_items')->where('sales_order_id', $six->id)->count(), 'produk yang tidak diimpor dilewati');
        $this->assertStringContainsString('1 baris dilewati', $six->note);
    }

    /** @test */
    public function a_missing_customer_is_refused_and_nothing_is_written(): void
    {
        DB::table('customers')->where('id', 6)->delete();

        $this->importer(true)->expectsOutputToContain('pelanggan idcustomer=6 tidak ada')->assertFailed();

        $this->assertSame(0, DB::table('sales_orders')->count());
    }

    /** @test */
    public function the_id_map_is_written_it_creates_no_side_effects_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 6')->assertSuccessful();

        $this->assertSame(6, DB::table('legacy_import_map')->where('entity', 'sales_order')->count());
        $this->assertSame(7, DB::table('legacy_import_map')->where('entity', 'sales_order_item')->count());
        $this->assertSame(6, DB::table('sales_orders')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
