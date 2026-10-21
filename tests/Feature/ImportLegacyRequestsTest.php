<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-requests`: request material terbuka legacy -> material_requisitions. Fixture dikarang sendiri.
 */
class ImportLegacyRequestsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private function ddl(): string
    {
        $cols = [
            'request' => 'idrequest int, norequest varchar(20), duedate date, iduser int, idsupplier int, note varchar(50), stat varchar(20), is_deleted int, xamount decimal(15,2), taxrp decimal(15,2), creatime datetime, tax varchar(5), top int',
            'requestdetail' => 'iddetail int, idrequest int, idrawmate int, qty decimal(12,2), price decimal(15,2), notes varchar(50)',
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

    private function request(int $id, string $number, string $stat, int $user = 5, int $deleted = 0, string $note = '', int $supplier = 7, string $created = '2026-10-19 15:04:51'): string
    {
        return $this->insert('request', ['idrequest' => $id, 'norequest' => $number, 'duedate' => '2026-10-22', 'iduser' => $user, 'idsupplier' => $supplier, 'note' => $note, 'stat' => $stat, 'is_deleted' => $deleted, 'xamount' => '99.00', 'taxrp' => '10.00', 'creatime' => $created, 'tax' => '0', 'top' => 14]);
    }

    private function rawmate(): string
    {
        return $this->insert('rawmate', ['idrawmate' => 500, 'nmrawmate' => 'ATK PULPEN', 'unit' => 'Pcs']);
    }

    private function line(int $id, int $request, int $material, string $qty = '10', string $price = '1000'): string
    {
        return $this->insert('requestdetail', ['iddetail' => $id, 'idrequest' => $request, 'idrawmate' => $material, 'qty' => $qty, 'price' => $price, 'notes' => '']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsFallback = User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        $user = User::create(['name' => 'Pemohon', 'username' => 'pemohon', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);

        foreach ([7 => 'PT SUPPLIER', 26 => 'PT GABUNGAN'] as $id => $name) {
            DB::table('suppliers')->insert(['id' => $id, 'name' => $name, 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $unit = MaterialUnit::create(['name' => 'BOX']);
        $category = MaterialCategory::create(['name' => 'KARTON']);
        foreach ([23 => 'KARTON A', 91 => 'KARTON B'] as $id => $name) {
            DB::table('materials')->insert(['id' => $id, 'code' => 'MTR'.$id, 'name' => $name, 'material_category_id' => $category->id, 'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => 1, 'show_in_stock' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-req-').'.sql';
        file_put_contents($this->path, $this->ddl().$this->rawmate()
            .$this->request(1, 'REQ-26/0001', 'Request').$this->line(1, 1, 23)
            .$this->request(2, 'REQ-26/0002', 'Waiting', 99, 0, '0').$this->line(2, 2, 23, '5', '200').$this->line(3, 2, 91, '2', '50').$this->line(4, 2, 500)
            .$this->request(3, 'REQ-26/0003', 'Ordering').$this->line(5, 3, 91)
            .$this->request(4, 'REQ-26/0004', 'PO Created').$this->line(6, 4, 23)
            .$this->request(5, 'REQ-26/0005', 'Waiting', 5, 127).$this->line(7, 5, 23)
            .$this->request(6, 'REQ-26/0006', 'Request').$this->line(8, 6, 500)
            .$this->request(7, 'REQ-26/0007', 'Ordering', 5, 0, '', 14).$this->line(9, 7, 23)
            .$this->request(8, 'REQ-26/0008', 'Ordering', 5, 0, '', 7, '2026-01-21 10:00:00').$this->line(10, 8, 91)
            .$this->request(9, 'REQ-26/0009', 'Completed').$this->line(11, 9, 500)
            .$this->request(10, 'REQ-26/0010', 'Partial').$this->line(12, 10, 23));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private ?User $actingAsFallback = null;

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-requests', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 8')->expectsOutputToContain('BARU  material_requisitions')->assertSuccessful();

        $this->assertSame(0, DB::table('material_requisitions')->count());
    }

    /** @test */
    public function the_whole_history_is_brought_except_deleted_and_open_requests_without_importable_lines(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame(
            ['REQ-26/0001', 'REQ-26/0002', 'REQ-26/0003', 'REQ-26/0004', 'REQ-26/0007', 'REQ-26/0008', 'REQ-26/0009', 'REQ-26/0010'],
            DB::table('material_requisitions')->orderBy('document_number')->pluck('document_number')->all(),
            'Dihapus (127) dan request TERBUKA yang semua barisnya di luar impor tidak dibawa.'
        );

        foreach (['REQ-26/0004', 'REQ-26/0009', 'REQ-26/0010'] as $number) {
            $this->assertSame('PO Created', DB::table('material_requisitions')->where('document_number', $number)->value('status'), 'PO Created/Partial/Completed -> PO Created');
        }

        $completed = DB::table('material_requisitions')->where('document_number', 'REQ-26/0009')->first();
        $this->assertSame(0, DB::table('material_requisition_items')->where('material_requisition_id', $completed->id)->count(), 'Dokumen riwayat dibawa tanpa baris bila semua bahannya di luar impor.');
        $this->assertStringContainsString('1 baris bahan dilewati', $completed->note);
        $this->assertStringContainsString('ATK PULPEN 10 x 1000', $completed->note, 'baris yang dilewati dicatat sebagai teks lengkap dengan nama bahan');
    }

    /** @test */
    public function only_open_limits_it_to_open_requests(): void
    {
        $this->artisan('legacy:import-requests', ['--source' => $this->path, '--fallback-user' => 'cadangan', '--only-open' => true, '--apply' => true])->assertSuccessful();

        $this->assertSame(
            ['REQ-26/0001', 'REQ-26/0002', 'REQ-26/0003', 'REQ-26/0007', 'REQ-26/0008'],
            DB::table('material_requisitions')->orderBy('document_number')->pluck('document_number')->all()
        );
    }

    /** @test */
    public function columns_are_paired_as_agreed(): void
    {
        $this->importer(true)->assertSuccessful();

        $one = DB::table('material_requisitions')->where('document_number', 'REQ-26/0001')->first();
        $this->assertSame('Requested', $one->status);
        $this->assertSame(5, (int) $one->user_id, 'pemohon yang ada memakai id yang sama');
        $this->assertSame(7, (int) $one->supplier_id);
        $this->assertSame('2026-10-22', $one->due_date);
        $this->assertSame('14', (string) $one->terms_of_payment);
        $this->assertSame(10000.0 + 10.0, (float) $one->total_amount, 'total = jumlah subtotal + pajak');

        $two = DB::table('material_requisitions')->where('document_number', 'REQ-26/0002')->first();
        $this->assertSame('Pending Finance', $two->status);
        $this->assertSame($this->actingAsFallback->id, (int) $two->user_id, 'pemohon tidak ada -> akun cadangan');
        $this->assertStringContainsString('Pemohon legacy: iduser 99', $two->note);
        $this->assertSame(2, DB::table('material_requisition_items')->where('material_requisition_id', $two->id)->count(), 'baris bahan di luar impor dilewati');
        $this->assertSame(5 * 200.0 + 2 * 50.0 + 10.0, (float) $two->total_amount);

        $three = DB::table('material_requisitions')->where('document_number', 'REQ-26/0003')->first();
        $this->assertSame('Pending Finance', $three->status, 'Ordering (disetujui, belum PO) -> Pending Finance');
    }

    /** @test */
    public function a_merged_supplier_goes_through_the_merge_map(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame(26, (int) DB::table('material_requisitions')->where('document_number', 'REQ-26/0007')->value('supplier_id'), 'supplier 14 digabung ke 26');
    }

    /** @test */
    public function an_open_request_older_than_the_cutoff_is_brought_as_rejected_with_a_reason(): void
    {
        $this->importer(true)->assertSuccessful();

        $old = DB::table('material_requisitions')->where('document_number', 'REQ-26/0008')->first();
        $this->assertSame('Rejected', $old->status);
        $this->assertStringContainsString('sejak 2026-01-21', $old->reject_note);
        $this->assertSame('Requested', DB::table('material_requisitions')->where('document_number', 'REQ-26/0001')->value('status'), 'yang baru tetap terbuka');
    }

    /** @test */
    public function running_it_twice_creates_nothing_and_does_not_overwrite(): void
    {
        $this->importer(true)->assertSuccessful();
        DB::table('material_requisitions')->where('document_number', 'REQ-26/0001')->update(['status' => 'PO Created']);

        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 8')->assertSuccessful();

        $this->assertSame('PO Created', DB::table('material_requisitions')->where('document_number', 'REQ-26/0001')->value('status'), 'Tidak ditimpa.');
    }

    /** @test */
    public function a_missing_requester_without_a_fallback_user_is_refused(): void
    {
        $this->importer(true, withFallback: false)->expectsOutputToContain('DITOLAK')->assertFailed();

        $this->assertSame(0, DB::table('material_requisitions')->count(), 'Satu penolakan membatalkan semuanya.');
    }
}
