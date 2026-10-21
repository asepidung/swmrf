<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-invoices`: invoice legacy -> invoices + invoice_items. Fixture dikarang sendiri.
 */
class ImportLegacyInvoicesTest extends TestCase
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

    private function invoice(int $id, int $receipt, string $status = '-', string $amount = '1000', string $balance = '1000', string $charge = '0', string $dp = '0', int $customer = 6, string $do = 'DO-1', string $tgltf = ''): string
    {
        return $this->insert('invoice', ['idinvoice' => $id, 'noinvoice' => 'INV-'.$id, 'iddoreceipt' => $receipt, 'top' => 14, 'duedate' => '2026-11-01', 'status' => $status, 'tgltf' => $tgltf ?: null, 'idsegment' => 1, 'invoice_date' => '2026-10-18', 'idcustomer' => $customer, 'pocustomer' => 'PO'.$id, 'donumber' => $do, 'note' => '', 'xweight' => '10', 'xamount' => $amount, 'xdiscount' => '0', 'tax' => '0', 'charge' => $charge, 'downpayment' => $dp, 'balance' => $balance, 'creatime' => '2026-10-19 09:00:00', 'is_deleted' => 0]);
    }

    private function line(int $id, int $invoice, string $amount = '1000', int $product = 21): string
    {
        return $this->insert('invoicedetail', ['idinvoicedetail' => $id, 'idinvoice' => $invoice, 'idbarang' => $product, 'weight' => '10', 'price' => '100', 'discount' => '2', 'discountrp' => '20', 'amount' => $amount]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        $segment = DB::table('customer_segments')->insertGetId(['name' => 'RETAIL', 'created_at' => $now, 'updated_at' => $now]);
        $group = DB::table('customer_groups')->insertGetId(['name' => 'REGULER', 'created_at' => $now, 'updated_at' => $now]);

        foreach ([6 => 'LION DCA', 143 => 'SHERLINDO'] as $id => $name) {
            DB::table('customers')->insert(['id' => $id, 'name' => $name, 'customer_group_id' => $group, 'customer_segment_id' => $segment, 'address' => '-', 'top' => 0, 'default_discount' => 0, 'invoice_exchange' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $category = DB::table('product_categories')->insertGetId(['name' => 'PRIME CUT', 'prefix' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('products')->insert(['id' => 21, 'code' => '100207', 'name' => 'OPERIB WHOLE', 'category_id' => $category, 'structure_type' => 'main', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // Hasil impor sebelumnya: SO 1, DO 1 dengan tanda terima 1 (bersama SO), dan tanda terima 2 milik DO lama tanpa SO.
        $so = DB::table('sales_orders')->insertGetId(['so_number' => 'SO-1', 'customer_id' => 6, 'delivery_date' => '2026-10-17', 'status' => 'completed', 'down_payment' => 0, 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $do1 = DB::table('delivery_orders')->insertGetId(['sales_order_id' => $so, 'customer_id' => 6, 'delivery_order_number' => 'DO-1', 'delivery_date' => '2026-10-17', 'status' => 'Invoiced', 'created_at' => $now, 'updated_at' => $now]);
        $do2 = DB::table('delivery_orders')->insertGetId(['customer_id' => 6, 'delivery_order_number' => 'DO-2', 'delivery_date' => '2023-08-01', 'status' => 'Invoiced', 'created_at' => $now, 'updated_at' => $now]);
        $r1 = DB::table('delivery_order_receipts')->insertGetId(['delivery_order_id' => $do1, 'customer_id' => 6, 'receipt_number' => 'DO-1', 'delivery_date' => '2026-10-17', 'total_box' => 1, 'total_weight' => 10, 'status' => 'Invoiced', 'created_at' => $now, 'updated_at' => $now]);
        $r2 = DB::table('delivery_order_receipts')->insertGetId(['delivery_order_id' => $do2, 'customer_id' => 6, 'receipt_number' => 'DO-2', 'delivery_date' => '2023-08-01', 'total_box' => 1, 'total_weight' => 10, 'status' => 'Invoiced', 'created_at' => $now, 'updated_at' => $now]);

        foreach ([1 => $r1, 2 => $r2] as $legacy => $new) {
            DB::table('legacy_import_map')->insert(['entity' => 'delivery_order_receipt', 'legacy_id' => $legacy, 'new_id' => $new, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-inv-').'.sql';
        $this->master = tempnam(sys_get_temp_dir(), 'legacy-master-').'.sql';

        file_put_contents($this->master, $this->ddl('customers', 'idcustomer int, nama_customer varchar(100), tanggal_update datetime')
            .$this->insert('customers', ['idcustomer' => 6, 'nama_customer' => 'LION DCA', 'tanggal_update' => '2023-01-01 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 18, 'nama_customer' => 'SHERLINDO MAKSIMA SUKSES PT', 'tanggal_update' => '2023-08-04 00:00:00'])
            .$this->insert('customers', ['idcustomer' => 143, 'nama_customer' => 'SHERLINDO', 'tanggal_update' => '2024-03-29 00:00:00']));

        file_put_contents($this->path, $this->ddl('invoice', 'idinvoice int, noinvoice varchar(30), iddoreceipt int, top int, duedate date, status varchar(30), tgltf date, idsegment int, invoice_date date, idcustomer int, pocustomer varchar(50), donumber varchar(30), note varchar(255), xweight decimal(12,2), xamount decimal(12,2), xdiscount decimal(12,2), tax decimal(12,2), charge decimal(12,2), downpayment decimal(12,2), balance decimal(12,2), creatime datetime, is_deleted int')
            .$this->ddl('invoicedetail', 'idinvoicedetail int, idinvoice int, idbarang int, weight decimal(12,2), price decimal(12,2), discount int, discountrp decimal(12,2), amount decimal(12,2)')
            .$this->invoice(1, 1).$this->line(1, 1, '600').$this->line(2, 1, '400').$this->line(3, 1, '5', 777)
            .$this->invoice(2, 2, 'Sudah TF', '2000', '2000', '100', '300', 18, 'DO-2', '2026-10-25').$this->line(4, 2, '2000')
            .$this->invoice(3, 1, 'Belum TF', '500', '0').$this->line(5, 3, '500')
            .$this->invoice(4, 1, '-', '900', '900').$this->line(6, 4, '1000'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->master);

        parent::tearDown();
    }

    private function importer(bool $apply = false, string $payment = 'open', bool $withFallback = true)
    {
        return $this->artisan('legacy:import-invoices', ['--source' => $this->path, '--master' => $this->master] + ($payment ? ['--payment' => $payment] : []) + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 4')->expectsOutputToContain('BARU  invoices')->assertSuccessful();

        $this->assertSame(0, DB::table('invoices')->count());
    }

    /** @test */
    public function the_payment_choice_is_required(): void
    {
        $this->importer(true, '')->assertFailed();
        $this->importer(true, 'bogus')->assertFailed();
        $this->assertSame(0, DB::table('invoices')->count());
    }

    /** @test */
    public function an_invoice_is_paired_with_its_receipt_and_so(): void
    {
        $this->importer(true)->assertSuccessful();

        $inv = DB::table('invoices')->where('invoice_number', 'INV-1')->first();
        $this->assertSame((int) DB::table('legacy_import_map')->where(['entity' => 'delivery_order_receipt', 'legacy_id' => 1])->value('new_id'), (int) $inv->delivery_order_receipt_id);
        $this->assertSame((int) DB::table('sales_orders')->where('so_number', 'SO-1')->value('id'), (int) $inv->sales_order_id, 'SO lewat tanda terima -> DO');
        $this->assertSame('2026-10-18', $inv->invoice_date);
        $this->assertSame(14, (int) $inv->term_of_payment);
        $this->assertSame('PO1', $inv->po_number);
        $this->assertSame('DO-1', $inv->delivery_order_number);
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $inv->created_by);
        $this->assertSame('2026-10-19 09:00:00', $inv->created_at);
        $this->assertSame(1, DB::table('receivables')->where('invoice_id', $inv->id)->count());
    }

    /** @test */
    public function items_are_paired_and_the_subtotal_is_the_sum_of_item_amounts(): void
    {
        $this->importer(true)->assertSuccessful();

        $inv = DB::table('invoices')->where('invoice_number', 'INV-1')->first();
        $this->assertSame(2, DB::table('invoice_items')->where('invoice_id', $inv->id)->count(), 'produk bukan impor dilewati');
        $this->assertSame(1000.0, (float) $inv->subtotal);
        $item = DB::table('invoice_items')->where('invoice_id', $inv->id)->orderBy('id')->first();
        $this->assertSame(21, (int) $item->product_id);
        $this->assertSame(2.0, (float) $item->discount_percent);
        $this->assertSame(20.0, (float) $item->discount_rp);
        $this->assertSame(0, (int) $item->box);
    }

    /** @test */
    public function open_payment_keeps_the_exchange_status_and_the_full_balance(): void
    {
        $this->importer(true, 'open')->assertSuccessful();

        $one = DB::table('invoices')->where('invoice_number', 'INV-1')->first();
        $this->assertSame('Belum Dibayar', $one->status, '"-" -> Belum Dibayar');
        $this->assertSame(0.0, (float) $one->paid_amount);
        $this->assertSame(1000.0, (float) $one->balance);

        $two = DB::table('invoices')->where('invoice_number', 'INV-2')->first();
        $this->assertSame('Sudah TF', $two->status);
        $this->assertSame('2026-10-25', $two->invoice_exchange_date);
        $this->assertSame(143, (int) $two->customer_id, 'pelanggan 18 digabung ke 143');
        $this->assertSame(2000.0 + 100 - 300, (float) $two->balance, 'subtotal + biaya - uang muka');

        $this->assertSame('Lunas', DB::table('invoices')->where('invoice_number', 'INV-3')->value('status'), 'saldo legacy 0 = Lunas');
    }

    /** @test */
    public function settled_payment_marks_everything_paid_without_payment_records(): void
    {
        $this->importer(true, 'settled')->assertSuccessful();

        $this->assertSame(0, DB::table('invoices')->where('status', '<>', 'Lunas')->count());
        $two = DB::table('invoices')->where('invoice_number', 'INV-2')->first();
        $this->assertSame(1800.0, (float) $two->paid_amount);
        $this->assertSame(0.0, (float) $two->balance);
        $this->assertSame(0, DB::table('payment_allocations')->count());
    }

    /** @test */
    public function additional_charge_substitute_so_and_legacy_mismatch_are_handled(): void
    {
        $this->importer(true)->assertSuccessful();

        $two = DB::table('invoices')->where('invoice_number', 'INV-2')->first();
        $this->assertSame(100.0, (float) $two->charge);
        $this->assertSame(1, DB::table('invoice_additional_charges')->where('invoice_id', $two->id)->count());

        $so = DB::table('sales_orders')->where('id', $two->sales_order_id)->first();
        $this->assertSame('SO-LGC-2', $so->so_number, 'DO lama tanpa SO -> SO pengganti');
        $this->assertSame('completed', $so->status);
        $this->assertSame(1, DB::table('sales_order_items')->where('sales_order_id', $so->id)->count());

        $four = DB::table('invoices')->where('invoice_number', 'INV-4')->first();
        $this->assertStringContainsString('Selisih legacy', $four->note);
        $this->assertSame(1000.0, (float) $four->subtotal);
    }

    /** @test */
    public function it_creates_no_side_effects_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 4')->assertSuccessful();

        $this->assertSame(4, DB::table('invoices')->count());
        $this->assertSame(1, DB::table('sales_orders')->where('so_number', 'SO-LGC-2')->count());
        $this->assertSame('Invoiced', DB::table('delivery_order_receipts')->where('receipt_number', 'DO-1')->value('status'));
        $this->assertSame(4, DB::table('legacy_import_map')->where('entity', 'invoice')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, 'open', false)->assertFailed();
    }
}
