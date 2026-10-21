<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-beef-grs`: riwayat GR daging legacy (satu baris = satu barcode) -> goods_receipt_products. Fixture dikarang sendiri.
 */
class ImportLegacyBeefGrsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private function ddl(): string
    {
        $cols = [
            'pobeef' => 'idpo int, nopo varchar(30), idrequest int, idsupplier int, xamount decimal(15,2), taxrp decimal(15,2), tax varchar(5), duedate date, note varchar(50), is_deleted int, creatime datetime, top int, stat varchar(2)',
            'pobeefdetail' => 'idpodetail int, idpo int, idbarang int, qty decimal(12,2), price decimal(15,2), subtotal decimal(15,2), notes varchar(50)',
            'grbeef' => 'idgr int, idpo int, grnumber varchar(20), receivedate date, idsupplier int, note varchar(50), suppcode varchar(20), idusers int, creatime datetime, is_deleted int',
            'grbeefdetail' => 'idgrbeefdetail int, idgr int, idbarang int, idgrade int, kdbarcode varchar(30), orderqty decimal(12,2), qty decimal(12,2), pcs varchar(5), pod date, creatime datetime, is_deleted int',
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

    private function po(int $id, string $number): string
    {
        return $this->insert('pobeef', ['idpo' => $id, 'nopo' => $number, 'idrequest' => 1, 'idsupplier' => 7, 'xamount' => '0', 'taxrp' => '0', 'tax' => '0', 'duedate' => '2026-10-30', 'note' => '', 'is_deleted' => 0, 'creatime' => '2026-10-01 10:00:00', 'top' => 0, 'stat' => '1'])
            .$this->insert('pobeefdetail', ['idpodetail' => $id, 'idpo' => $id, 'idbarang' => 80, 'qty' => '30', 'price' => '10000', 'subtotal' => '300000', 'notes' => '']);
    }

    private function gr(int $id, int $po, string $number, int $supplier = 7, int $user = 5, int $deleted = 0): string
    {
        return $this->insert('grbeef', ['idgr' => $id, 'idpo' => $po, 'grnumber' => $number, 'receivedate' => '2026-10-12', 'idsupplier' => $supplier, 'note' => '0', 'suppcode' => 'SJ-1', 'idusers' => $user, 'creatime' => '2026-10-12 10:00:00', 'is_deleted' => $deleted]);
    }

    private function line(int $id, int $gr, string $barcode, int $grade = 2, string $qty = '12.5', string $pcs = '3', int $deleted = 0, int $product = 80): string
    {
        return $this->insert('grbeefdetail', ['idgrbeefdetail' => $id, 'idgr' => $gr, 'idbarang' => $product, 'idgrade' => $grade, 'kdbarcode' => $barcode, 'orderqty' => null, 'qty' => $qty, 'pcs' => $pcs, 'pod' => '2026-10-11', 'creatime' => '2026-10-12 10:00:00', 'is_deleted' => $deleted]);
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

        foreach ([7 => 'PT SUPPLIER', 26 => 'PT GABUNGAN'] as $id => $name) {
            DB::table('suppliers')->insert(['id' => $id, 'name' => $name, 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }

        $category = DB::table('product_categories')->insertGetId(['name' => 'PRIME CUT', 'prefix' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('products')->insert(['id' => 80, 'code' => '700900', 'name' => 'DAGING A', 'category_id' => $category, 'structure_type' => 'main', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $pr = DB::table('product_requisitions')->insertGetId(['document_number' => 'RED-26/0001', 'user_id' => 5, 'supplier_id' => 7, 'due_date' => '2026-10-22', 'status' => 'PO Created', 'total_amount' => 0, 'created_at' => $now, 'updated_at' => $now]);
        foreach (['POV-26/001', 'POV-26/002'] as $number) {
            DB::table('purchase_products')->insert(['product_requisition_id' => $pr, 'po_number' => $number, 'po_date' => '2026-10-01', 'supplier_id' => 7, 'approved_by' => 5, 'total_amount' => 0, 'status' => 'completed', 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-bgr-').'.sql';
        file_put_contents($this->path, $this->ddl()
            .$this->insert('pobeef', ['idpo' => 99, 'nopo' => 'POV-26/099', 'idrequest' => 1, 'idsupplier' => 7, 'xamount' => '0', 'taxrp' => '0', 'tax' => '0', 'duedate' => '2026-10-30', 'note' => '', 'is_deleted' => 1, 'creatime' => '2026-10-01 10:00:00', 'top' => 0, 'stat' => '0'])
            .$this->po(1, 'POV-26/001').$this->po(2, 'POV-26/002')
            .$this->gr(1, 1, 'GRM-1').$this->line(1, 1, '2100000000000000001', 2, '12.5', '3').$this->line(2, 1, '5100000000000000002', 1, '7.25', '').$this->line(3, 1, '1100000000000000003', 2, '4', '2', 1)
            .$this->gr(2, 2, 'GRM-2', 14, 99).$this->line(4, 2, '2100000000000000004', 2, '9')
            .$this->gr(3, 1, 'GRM-3', 7, 5, 1).$this->line(5, 3, '2100000000000000005')
            .$this->gr(4, 99, 'GRM-4').$this->line(6, 4, '2100000000000000006')
            .$this->gr(5, 1, 'GRM-1').$this->line(7, 5, '1100000000000000007', 1, '3', '1'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-beef-grs', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 3')->expectsOutputToContain('BARU  goods_receipt_products')->assertSuccessful();

        $this->assertSame(0, DB::table('goods_receipt_products')->count());
    }

    /** @test */
    public function deleted_lines_deleted_grs_and_grs_without_a_parent_po_are_ignored(): void
    {
        $this->importer(true)->expectsOutputToContain('PO induk idpo=99 tidak ada')->assertSuccessful();

        $this->assertSame(['GRM-1', 'GRM-1-2', 'GRM-2'], DB::table('goods_receipt_products')->orderBy('gr_number')->pluck('gr_number')->all());
        $this->assertNull(DB::table('goods_receipt_product_items')->where('barcode', '1100000000000000003')->first(), 'baris detail yang dihapus diabaikan');
    }

    /** @test */
    public function columns_are_paired_as_agreed(): void
    {
        $this->importer(true)->assertSuccessful();

        $gr = DB::table('goods_receipt_products')->where('gr_number', 'GRM-1')->first();
        $this->assertSame(DB::table('purchase_products')->where('po_number', 'POV-26/001')->value('id'), (int) $gr->purchase_product_id);
        $this->assertSame('SJ-1', $gr->sj_number);
        $this->assertSame(1, (int) $gr->is_locked);
        $this->assertSame(5, (int) $gr->created_by);

        $item = DB::table('goods_receipt_product_items')->where('barcode', '2100000000000000001')->first();
        $this->assertSame(80, (int) $item->product_id);
        $this->assertSame(2, (int) $item->grade_id, 'J02 -> grade FROZEN (id 2)');
        $this->assertSame(12.5, (float) $item->weight);
        $this->assertSame(3, (int) $item->qty_pcs);
        $this->assertSame('2026-10-11', $item->pack_date);
        $this->assertSame('GRM-1', $item->batch_no);
        $this->assertSame('TRD-LC', $item->origin, 'barcode berawalan 2 -> TRD-LC');
        $this->assertSame(10000.0, (float) $item->price, 'harga per kg dari PO');
        $this->assertSame(125000.0, (float) $item->subtotal);

        $import = DB::table('goods_receipt_product_items')->where('barcode', '5100000000000000002')->first();
        $this->assertSame('TRD-IM', $import->origin, 'barcode berawalan 5 -> TRD-IM');
        $this->assertSame(1, (int) $import->grade_id, 'J01 -> grade CHILL (id 1)');
        $this->assertSame(0, (int) $import->qty_pcs, 'pcs kosong -> 0');
    }

    /** @test */
    public function a_merged_supplier_a_missing_creator_and_a_duplicate_number_are_handled(): void
    {
        $this->importer(true)->assertSuccessful();

        $two = DB::table('goods_receipt_products')->where('gr_number', 'GRM-2')->first();
        $this->assertSame(26, (int) $two->supplier_id);
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $two->created_by);
        $this->assertStringContainsString('Nomor kembar di legacy (idgr=5)', DB::table('goods_receipt_products')->where('gr_number', 'GRM-1-2')->value('note'));
    }

    /** @test */
    public function it_creates_no_stock_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 3')->assertSuccessful();

        $this->assertSame(0, DB::table('beef_stocks')->count(), 'GR riwayat tidak membuat stok');
        $this->assertSame(0, DB::table('beef_stock_movements')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
