<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-pos`: riwayat PO material legacy -> purchase_materials. Fixture dikarang sendiri.
 */
class ImportLegacyPosTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private function ddl(): string
    {
        $cols = [
            'request' => 'idrequest int, norequest varchar(20), duedate date, iduser int, idsupplier int, note varchar(50), stat varchar(20), is_deleted int, xamount decimal(15,2), taxrp decimal(15,2), creatime datetime, tax varchar(5), top int',
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

    private function request(int $id, string $number): string
    {
        return $this->insert('request', ['idrequest' => $id, 'norequest' => $number, 'duedate' => '2026-10-22', 'iduser' => 5, 'idsupplier' => 7, 'note' => '', 'stat' => 'PO Created', 'is_deleted' => 0, 'xamount' => '0', 'taxrp' => '0', 'creatime' => '2026-10-01 10:00:00', 'tax' => '0', 'top' => 0]);
    }

    private function po(int $id, string $number, int $request, string $stat, string $created = '2026-10-10 09:00:00', int $supplier = 7, int $deleted = 0): string
    {
        return $this->insert('po', ['idpo' => $id, 'nopo' => $number, 'idrequest' => $request, 'idsupplier' => $supplier, 'xamount' => '999', 'taxrp' => '100', 'tax' => '11', 'duedate' => '2026-10-30', 'note' => '0', 'top' => 14, 'is_deleted' => $deleted, 'creatime' => $created, 'stat' => $stat]);
    }

    private function line(int $id, int $po, int $material, string $qty = '10', string $price = '1000'): string
    {
        return $this->insert('podetail', ['idpodetail' => $id, 'idpo' => $po, 'idrawmate' => $material, 'qty' => $qty, 'price' => $price, 'subtotal' => (string) ((float) $qty * (float) $price), 'notes' => '']);
    }

    private function gr(int $id, int $po, string $qty): string
    {
        return $this->insert('grraw', ['idgr' => $id, 'idpo' => $po, 'grnumber' => "GRM-{$id}", 'receivedate' => '2026-10-12', 'idsupplier' => 7, 'suppcode' => '', 'note' => '', 'idusers' => 5, 'creatime' => '2026-10-12 10:00:00', 'is_deleted' => 0])
            .$this->insert('grrawdetail', ['idgrrawdetail' => $id, 'idgr' => $id, 'idrawmate' => 23, 'orderqty' => '10', 'qty' => $qty, 'creatime' => '2026-10-12 10:00:00', 'idtransaksi' => 0, 'is_deleted' => 0]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        User::create(['name' => 'Penyetuju', 'username' => 'penyetuju', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        foreach ([7 => 'PT SUPPLIER', 26 => 'PT GABUNGAN'] as $id => $name) {
            DB::table('suppliers')->insert(['id' => $id, 'name' => $name, 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        $unit = DB::table('material_units')->insertGetId(['name' => 'BOX', 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('material_categories')->insertGetId(['name' => 'KARTON', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([23 => 'KARTON A', 91 => 'KARTON B'] as $id => $name) {
            DB::table('materials')->insert(['id' => $id, 'code' => 'MTR'.$id, 'name' => $name, 'material_category_id' => $category, 'material_unit_id' => $unit, 'min_stock' => 0, 'is_active' => 1, 'show_in_stock' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Request induk yang sudah diimpor (REQ-26/0002 kembar -> -2).
        $user = User::where('username', 'penyetuju')->value('id');
        foreach (['REQ-26/0001', 'REQ-26/0002', 'REQ-26/0002-2'] as $number) {
            DB::table('material_requisitions')->insert(['document_number' => $number, 'user_id' => $user, 'supplier_id' => 7, 'due_date' => '2026-10-22', 'status' => 'PO Created', 'total_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-po-').'.sql';
        file_put_contents($this->path, $this->ddl()
            .$this->insert('rawmate', ['idrawmate' => 500, 'nmrawmate' => 'PLAT BESI', 'unit' => 'Pcs'])
            .$this->request(1, 'REQ-26/0001').$this->request(2, 'REQ-26/0002').$this->request(3, 'REQ-26/0002')
            .$this->po(1, 'PO-26/001', 1, '0').$this->line(1, 1, 23)
            .$this->po(2, 'PO-26/002', 1, '0', '2026-03-05 09:00:00').$this->line(2, 2, 91)
            .$this->po(3, 'PO-26/003', 1, '1').$this->line(3, 3, 23)
            .$this->po(4, 'PO-26/004', 1, '2').$this->line(4, 4, 23)
            .$this->po(5, 'PO-26/005', 1, '3').$this->line(5, 5, 23, '10').$this->gr(1, 5, '4')
            .$this->po(6, 'PO-26/006', 1, '3').$this->line(6, 6, 23, '10').$this->gr(2, 6, '10')
            .$this->po(7, 'PO-26/007', 2, '0', '2026-10-11 09:00:00', 14).$this->line(7, 7, 23).$this->line(8, 7, 500, '3', '600')
            .$this->po(8, 'PO-26/008', 3, '1').$this->line(9, 8, 91)
            .$this->po(9, 'PO-26/009', 1, '1', '2026-10-10 09:00:00', 7, 1).$this->line(10, 9, 23)
            .$this->po(10, 'PO-26/010', 99, '0').$this->line(11, 10, 23));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withApprover = true)
    {
        return $this->artisan('legacy:import-pos', ['--source' => $this->path] + ($withApprover ? ['--approver' => 'penyetuju'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 8')->expectsOutputToContain('BARU  purchase_materials')->assertSuccessful();

        $this->assertSame(0, DB::table('purchase_materials')->count());
    }

    /** @test */
    public function the_approver_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }

    /** @test */
    public function statuses_follow_the_legacy_meaning(): void
    {
        $this->importer(true)->assertSuccessful();

        $status = fn (string $n) => DB::table('purchase_materials')->where('po_number', $n)->value('status');

        $this->assertSame('pending', $status('PO-26/001'), 'stat 0 baru -> pending');
        $this->assertSame('canceled', $status('PO-26/002'), 'stat 0 sebelum cutoff -> canceled');
        $this->assertSame('completed', $status('PO-26/003'), 'stat 1 (ditutup) -> completed');
        $this->assertSame('canceled', $status('PO-26/004'), 'stat 2 -> canceled');
        $this->assertSame('partial', $status('PO-26/005'), 'stat 3 dengan GR kurang -> partial');
        $this->assertSame('completed', $status('PO-26/006'), 'stat 3 dengan GR penuh -> completed');
    }

    /** @test */
    public function deleted_pos_and_pos_without_a_parent_request_are_not_brought(): void
    {
        $this->importer(true)->expectsOutputToContain('request induk idrequest=99 tidak ada')->assertSuccessful();

        $this->assertNull(DB::table('purchase_materials')->where('po_number', 'PO-26/009')->first(), 'dihapus');
        $this->assertNull(DB::table('purchase_materials')->where('po_number', 'PO-26/010')->first(), 'tanpa induk');
        $this->assertSame(8, DB::table('purchase_materials')->count());
    }

    /** @test */
    public function columns_are_paired_as_agreed_and_the_parent_follows_the_duplicate_suffix(): void
    {
        $this->importer(true)->assertSuccessful();

        $po = DB::table('purchase_materials')->where('po_number', 'PO-26/001')->first();
        $this->assertSame(DB::table('material_requisitions')->where('document_number', 'REQ-26/0001')->value('id'), (int) $po->material_requisition_id);
        $this->assertSame(User::where('username', 'penyetuju')->value('id'), (int) $po->approved_by);
        $this->assertSame('2026-10-10', $po->po_date);
        $this->assertSame(10000.0 + 100.0, (float) $po->total_amount, 'total = jumlah subtotal + pajak');
        $this->assertStringContainsString('TOP 14 hari', $po->note);

        $second = DB::table('purchase_materials')->where('po_number', 'PO-26/008')->first();
        $this->assertSame(DB::table('material_requisitions')->where('document_number', 'REQ-26/0002-2')->value('id'), (int) $second->material_requisition_id, 'request kembar kedua memakai nomor dengan akhiran -2');
    }

    /** @test */
    public function a_merged_supplier_and_skipped_lines_are_handled(): void
    {
        $this->importer(true)->assertSuccessful();

        $po = DB::table('purchase_materials')->where('po_number', 'PO-26/007')->first();
        $this->assertSame(26, (int) $po->supplier_id, 'supplier 14 digabung ke 26');
        $this->assertSame(1, DB::table('purchase_material_items')->where('purchase_material_id', $po->id)->count());
        $this->assertStringContainsString('PLAT BESI 3 x 600', $po->note);
    }

    /** @test */
    public function running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 8')->assertSuccessful();
    }
}
