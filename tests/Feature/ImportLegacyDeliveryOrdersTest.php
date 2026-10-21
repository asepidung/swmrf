<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-delivery-orders`: DO + tanda terima legacy -> delivery_orders / delivery_order_receipts. Fixture dikarang sendiri.
 */
class ImportLegacyDeliveryOrdersTest extends TestCase
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

    private function doc(int $id, string $status = 'Invoiced', ?int $so = 1, ?int $tally = 1, string $driver = 'MPE', string $plat = 'F 8489 HV', int $customer = 6, int $user = 5): string
    {
        return $this->insert('do', ['iddo' => $id, 'donumber' => 'DO-'.$id, 'idcustomer' => $customer, 'idso' => $so, 'idtally' => $tally, 'po' => 'PO-'.$id, 'deliverydate' => '2026-10-21', 'driver' => $driver, 'plat' => $plat, 'note' => '', 'sealnumb' => 'S1', 'status' => $status, 'xbox' => 3, 'xweight' => '60', 'idusers' => $user, 'created' => '2026-10-20 10:00:00', 'rweight' => null, 'is_deleted' => 0]);
    }

    private function line(int $id, int $do, int $product = 21): string
    {
        return $this->insert('dodetail', ['iddodetail' => $id, 'iddo' => $do, 'idbarang' => $product, 'box' => 3, 'weight' => '60.50', 'notes' => '']);
    }

    private function receipt(int $id, int $do, string $number, string $status = 'Invoiced'): string
    {
        return $this->insert('doreceipt', ['iddoreceipt' => $id, 'iddo' => $do, 'donumber' => $number, 'deliverydate' => '2026-10-22', 'idcustomer' => 6, 'po' => 'PO', 'driver' => '', 'plat' => '', 'note' => 'terima', 'status' => $status, 'xbox' => 3, 'xweight' => '60.50', 'idusers' => 5, 'created' => '2026-10-22 08:00:00', 'alamat' => null, 'idso' => null, 'is_deleted' => 0]);
    }

    private function receiptLine(int $id, int $receipt): string
    {
        return $this->insert('doreceiptdetail', ['iddoreceiptdetail' => $id, 'iddoreceipt' => $receipt, 'idbarang' => 21, 'box' => 3, 'weight' => '60.50', 'notes' => '']);
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
        DB::table('products')->insert(['id' => 21, 'code' => '100207', 'name' => 'OPERIB WHOLE', 'category_id' => $category, 'structure_type' => 'main', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('drivers')->insert(['name' => 'MPE', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('vehicles')->insert(['vehicle_type' => 'TRUCK', 'police_number' => 'F 8489 HV', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // SO dan tally hasil impor sebelumnya, dihubungkan lewat legacy_import_map (legacy id 1).
        $so = DB::table('sales_orders')->insertGetId(['so_number' => 'SO-1', 'customer_id' => 6, 'delivery_date' => '2026-10-21', 'status' => 'completed', 'down_payment' => 0, 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);
        $tally = DB::table('tallies')->insertGetId(['sales_order_id' => $so, 'tally_number' => 'TS1', 'status' => 'do', 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);

        foreach (['sales_order' => $so, 'tally' => $tally] as $entity => $new) {
            DB::table('legacy_import_map')->insert(['entity' => $entity, 'legacy_id' => 1, 'new_id' => $new, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-do-').'.sql';
        $this->master = tempnam(sys_get_temp_dir(), 'legacy-master-').'.sql';

        file_put_contents($this->master, $this->ddl('customers', 'idcustomer int, nama_customer varchar(100), tanggal_update datetime')
            .$this->insert('customers', ['idcustomer' => 6, 'nama_customer' => 'LION DCA', 'tanggal_update' => '2023-01-01 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 18, 'nama_customer' => 'SHERLINDO MAKSIMA SUKSES PT', 'tanggal_update' => '2023-08-04 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 143, 'nama_customer' => 'SHERLINDO', 'tanggal_update' => '2024-03-29 00:00:00']));

        $do = 'iddo int, donumber varchar(30), idcustomer int, idso int, idtally int, po varchar(30), deliverydate date, driver varchar(20), plat varchar(12), note varchar(255), sealnumb varchar(7), status varchar(20), xbox int, xweight decimal(12,2), idusers int, created datetime, rweight decimal(12,2), is_deleted int';
        $receipt = 'iddoreceipt int, iddo int, donumber varchar(30), deliverydate date, idcustomer int, po varchar(50), driver varchar(20), plat varchar(12), note varchar(255), status varchar(20), xbox int, xweight decimal(12,2), idusers int, created datetime, alamat varchar(255), idso int, is_deleted int';
        file_put_contents($this->path, $this->ddl('do', $do)
            .$this->ddl('dodetail', 'iddodetail int, iddo int, idbarang int, box int, weight decimal(12,2), notes varchar(255)')
            .$this->ddl('doreceipt', $receipt)
            .$this->ddl('doreceiptdetail', 'iddoreceiptdetail int, iddoreceipt int, idbarang int, box int, weight decimal(12,2), notes varchar(255)')
            .$this->doc(1).$this->line(1, 1).$this->line(2, 1, 777)
            .$this->doc(2, 'Unapproved', null, null, 'H. MPE', 'f8489hv', 18, 99).$this->line(3, 2)
            .$this->doc(3, 'Rejected', null, null, 'BUDI/CASUAL', 'B 1 X').$this->line(4, 3)
            .$this->doc(4, 'Approved', 1, 77, '-', '').$this->line(5, 4)
            .$this->receipt(1, 1, 'DO-1').$this->receiptLine(1, 1)
            .$this->receipt(2, 4, 'DO-4', 'Approved').$this->receiptLine(2, 2).$this->receipt(3, 4, 'DO-4'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->master);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-delivery-orders', ['--source' => $this->path, '--master' => $this->master] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 4')->expectsOutputToContain('BARU  delivery_orders')->assertSuccessful();

        $this->assertSame(0, DB::table('delivery_orders')->count());
        $this->assertSame(0, DB::table('delivery_order_receipts')->count());
    }

    /** @test */
    public function an_order_is_paired_with_its_so_tally_driver_and_vehicle(): void
    {
        $this->importer(true)->assertSuccessful();

        $do = DB::table('delivery_orders')->where('delivery_order_number', 'DO-1')->first();
        $this->assertSame((int) DB::table('legacy_import_map')->where(['entity' => 'sales_order', 'legacy_id' => 1])->value('new_id'), (int) $do->sales_order_id);
        $this->assertSame((int) DB::table('legacy_import_map')->where(['entity' => 'tally', 'legacy_id' => 1])->value('new_id'), (int) $do->tally_id);
        $this->assertSame(DB::table('drivers')->where('name', 'MPE')->value('id'), $do->driver_id);
        $this->assertSame(DB::table('vehicles')->value('id'), $do->vehicle_id);
        $this->assertSame('Invoiced', $do->status);
        $this->assertSame('PO-1', $do->po_number);
        $this->assertSame('S1', $do->seal_number);
        $this->assertSame(5, (int) $do->created_by);
        $this->assertSame('2026-10-20 10:00:00', $do->created_at);
        $this->assertNull($do->deleted_at);
    }

    /** @test */
    public function items_are_paired_and_non_imported_products_skipped(): void
    {
        $this->importer(true)->assertSuccessful();

        $id = DB::table('delivery_orders')->where('delivery_order_number', 'DO-1')->value('id');
        $items = DB::table('delivery_order_items')->where('delivery_order_id', $id)->get();
        $this->assertCount(1, $items);
        $this->assertSame(21, (int) $items[0]->product_id);
        $this->assertSame(3, (int) $items[0]->box);
        $this->assertSame(60.5, (float) $items[0]->weight);
    }

    /** @test */
    public function driver_and_plate_variants_match_and_unknown_text_is_kept_in_the_note(): void
    {
        $this->importer(true)->assertSuccessful();

        $two = DB::table('delivery_orders')->where('delivery_order_number', 'DO-2')->first();
        $this->assertSame(DB::table('drivers')->where('name', 'MPE')->value('id'), $two->driver_id, '"H. MPE" cocok ke MPE');
        $this->assertSame(DB::table('vehicles')->value('id'), $two->vehicle_id, 'plat tanpa spasi/huruf kecil cocok');
        $this->assertSame('Ready', $two->status, 'Unapproved -> Ready');
        $this->assertSame(143, (int) $two->customer_id, 'pelanggan 18 digabung ke 143');
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $two->created_by);

        $three = DB::table('delivery_orders')->where('delivery_order_number', 'DO-3')->first();
        $this->assertNull($three->driver_id);
        $this->assertNull($three->vehicle_id);
        $this->assertStringContainsString('Supir legacy: BUDI/CASUAL', $three->note);
        $this->assertStringContainsString('Plat legacy: B 1 X', $three->note);
    }

    /** @test */
    public function a_rejected_order_is_soft_deleted_and_a_lost_tally_stays_empty(): void
    {
        $this->importer(true)->assertSuccessful();

        $rejected = DB::table('delivery_orders')->where('delivery_order_number', 'DO-3')->first();
        $this->assertNotNull($rejected->deleted_at);
        $this->assertStringContainsString('Ditolak di legacy', $rejected->note);

        $approved = DB::table('delivery_orders')->where('delivery_order_number', 'DO-4')->first();
        $this->assertNull($approved->tally_id, 'tally 77 tidak ada di aplikasi baru');
        $this->assertSame('Approved', $approved->status);
        $this->assertNull($approved->driver_id, 'supir "-" dianggap kosong');
        $this->assertNull($approved->note);
    }

    /** @test */
    public function receipts_are_paired_and_duplicate_numbers_get_a_suffix(): void
    {
        $this->importer(true)->assertSuccessful();

        $doId = DB::table('delivery_orders')->where('delivery_order_number', 'DO-1')->value('id');
        $receipt = DB::table('delivery_order_receipts')->where('receipt_number', 'DO-1')->first();
        $this->assertSame($doId, $receipt->delivery_order_id);
        $this->assertSame(3, (int) $receipt->total_box);
        $this->assertSame(60.5, (float) $receipt->total_weight);
        $this->assertSame('Invoiced', $receipt->status);
        $this->assertNotNull($receipt->sales_order_id, 'SO diambil dari DO-nya');
        $this->assertSame(1, DB::table('delivery_order_receipt_items')->where('delivery_order_receipt_id', $receipt->id)->count());

        $this->assertSame(['DO-1', 'DO-4', 'DO-4-2'], DB::table('delivery_order_receipts')->orderBy('receipt_number')->pluck('receipt_number')->all());
    }

    /** @test */
    public function it_creates_no_side_effects_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 4')->assertSuccessful();

        $this->assertSame(4, DB::table('delivery_orders')->count());
        $this->assertSame(3, DB::table('delivery_order_receipts')->count());
        $this->assertSame('do', DB::table('tallies')->value('status'), 'status tally tidak berubah');
        $this->assertSame('completed', DB::table('sales_orders')->value('status'), 'status SO tidak berubah');
        $this->assertSame(4, DB::table('legacy_import_map')->where('entity', 'delivery_order')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
