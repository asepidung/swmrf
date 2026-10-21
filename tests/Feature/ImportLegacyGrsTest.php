<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-grs`: riwayat GR material legacy -> goods_receipt_materials. Fixture dikarang sendiri.
 */
class ImportLegacyGrsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private function ddl(): string
    {
        $cols = [
            'po' => 'idpo int, nopo varchar(30), idrequest int, idsupplier int, xamount decimal(15,2), taxrp decimal(15,2), tax varchar(5), duedate date, note varchar(50), top int, is_deleted int, creatime datetime, stat varchar(2)',
            'podetail' => 'idpodetail int, idpo int, idrawmate int, qty decimal(12,2), price decimal(15,2), subtotal decimal(15,2), notes varchar(50)',
            'grraw' => 'idgr int, idpo int, grnumber varchar(20), receivedate date, idsupplier int, suppcode varchar(20), note varchar(50), idusers int, creatime datetime, is_deleted int',
            'grrawdetail' => 'idgrrawdetail int, idgr int, idrawmate int, orderqty decimal(12,2), qty decimal(12,2), creatime datetime, idtransaksi int, is_deleted int',
            'rawmate' => 'idrawmate int, nmrawmate varchar(50), unit varchar(10)',
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
        return $this->insert('po', ['idpo' => $id, 'nopo' => $number, 'idrequest' => 1, 'idsupplier' => 7, 'xamount' => '0', 'taxrp' => '0', 'tax' => '0', 'duedate' => '2026-10-30', 'note' => '', 'top' => 0, 'is_deleted' => 0, 'creatime' => '2026-10-01 10:00:00', 'stat' => '3'])
            .$this->insert('podetail', ['idpodetail' => $id, 'idpo' => $id, 'idrawmate' => 23, 'qty' => '10', 'price' => '2500', 'subtotal' => '25000', 'notes' => '']);
    }

    private function gr(int $id, int $po, string $number, int $supplier = 7, int $user = 5, int $deleted = 0, string $suppcode = 'SJ-1'): string
    {
        return $this->insert('grraw', ['idgr' => $id, 'idpo' => $po, 'grnumber' => $number, 'receivedate' => '2026-10-12', 'idsupplier' => $supplier, 'suppcode' => $suppcode, 'note' => '0', 'idusers' => $user, 'creatime' => '2026-10-12 10:00:00', 'is_deleted' => $deleted]);
    }

    private function line(int $id, int $gr, int $material, string $qty = '4'): string
    {
        return $this->insert('grrawdetail', ['idgrrawdetail' => $id, 'idgr' => $gr, 'idrawmate' => $material, 'orderqty' => '10', 'qty' => $qty, 'creatime' => '2026-10-12 10:00:00', 'idtransaksi' => 0, 'is_deleted' => 0]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create(['name' => 'Pembuat', 'username' => 'pembuat', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);

        foreach ([7 => 'PT SUPPLIER', 26 => 'PT GABUNGAN'] as $id => $name) {
            DB::table('suppliers')->insert(['id' => $id, 'name' => $name, 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        $unit = DB::table('material_units')->insertGetId(['name' => 'BOX', 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('material_categories')->insertGetId(['name' => 'KARTON', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('materials')->insert(['id' => 23, 'code' => 'MTR23', 'name' => 'KARTON A', 'material_category_id' => $category, 'material_unit_id' => $unit, 'min_stock' => 0, 'is_active' => 1, 'show_in_stock' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $mr = DB::table('material_requisitions')->insertGetId(['document_number' => 'REQ-26/0001', 'user_id' => 5, 'supplier_id' => 7, 'due_date' => '2026-10-22', 'status' => 'PO Created', 'total_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['PO-26/001', 'PO-26/002'] as $number) {
            DB::table('purchase_materials')->insert(['po_number' => $number, 'material_requisition_id' => $mr, 'supplier_id' => 7, 'approved_by' => 5, 'po_date' => '2026-10-01', 'total_amount' => 0, 'status' => 'partial', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-gr-').'.sql';
        file_put_contents($this->path, $this->ddl()
            .$this->insert('rawmate', ['idrawmate' => 500, 'nmrawmate' => 'PLAT BESI', 'unit' => 'Pcs'])
            .$this->po(1, 'PO-26/001').$this->po(2, 'PO-26/002')
            .$this->gr(1, 1, 'GRM-1').$this->line(1, 1, 23).$this->line(2, 1, 500, '2')
            .$this->gr(2, 2, 'GRM-2', 14, 99).$this->line(3, 2, 23, '10')
            .$this->gr(3, 1, 'GRM-3', 7, 5, 1).$this->line(4, 3, 23)
            .$this->gr(4, 77, 'GRM-4').$this->line(5, 4, 23)
            .$this->gr(5, 1, 'GRM-1').$this->line(6, 5, 23, '1'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-grs', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 3')->expectsOutputToContain('BARU  goods_receipt_materials')->assertSuccessful();

        $this->assertSame(0, DB::table('goods_receipt_materials')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }

    /** @test */
    public function deleted_grs_and_grs_without_a_parent_po_are_not_brought(): void
    {
        $this->importer(true)->expectsOutputToContain('PO induk idpo=77 tidak ada')->assertSuccessful();

        $this->assertSame(['GRM-1', 'GRM-1-2', 'GRM-2'], DB::table('goods_receipt_materials')->orderBy('gr_number')->pluck('gr_number')->all());
    }

    /** @test */
    public function columns_are_paired_as_agreed(): void
    {
        $this->importer(true)->assertSuccessful();

        $gr = DB::table('goods_receipt_materials')->where('gr_number', 'GRM-1')->first();
        $this->assertSame(DB::table('purchase_materials')->where('po_number', 'PO-26/001')->value('id'), (int) $gr->purchase_material_id);
        $this->assertSame('2026-10-12', $gr->receive_date);
        $this->assertSame('SJ-1', $gr->sj_number);
        $this->assertSame(1, (int) $gr->is_locked);
        $this->assertSame(5, (int) $gr->created_by, 'pembuat yang ada memakai id yang sama');
        $this->assertStringContainsString('PLAT BESI 2', $gr->note, 'baris yang dilewati dicatat sebagai teks');

        $item = DB::table('goods_receipt_material_items')->where('goods_receipt_material_id', $gr->id)->first();
        $this->assertSame(23, (int) $item->material_id);
        $this->assertSame(4.0, (float) $item->qty_received);
        $this->assertSame(2500.0, (float) $item->price, 'harga dari baris PO');
        $this->assertSame(10000.0, (float) $item->subtotal);
        $this->assertSame(1, DB::table('goods_receipt_material_items')->where('goods_receipt_material_id', $gr->id)->count(), 'baris di luar impor dilewati');
    }

    /** @test */
    public function a_merged_supplier_a_missing_creator_and_a_duplicate_number_are_handled(): void
    {
        $this->importer(true)->assertSuccessful();

        $two = DB::table('goods_receipt_materials')->where('gr_number', 'GRM-2')->first();
        $this->assertSame(26, (int) $two->supplier_id, 'supplier 14 digabung ke 26');
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $two->created_by, 'pembuat tidak ada -> akun pengganti');

        $dup = DB::table('goods_receipt_materials')->where('gr_number', 'GRM-1-2')->first();
        $this->assertStringContainsString('Nomor kembar di legacy (idgr=5)', $dup->note);
    }

    /** @test */
    public function it_does_not_touch_stock_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 3')->assertSuccessful();

        $this->assertSame(0, DB::table('material_stocks')->count(), 'GR riwayat tidak menambah stok material');
    }
}
