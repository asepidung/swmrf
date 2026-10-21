<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-repacks`: repack + bahan + hasil legacy -> repacks, repack_materials, repack_results. Fixture dikarang sendiri.
 */
class ImportLegacyRepacksTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

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

    private function repack(int $id, string $number, int $kunci = 1, int $user = 5): string
    {
        return $this->insert('repack', ['idrepack' => $id, 'norepack' => $number, 'tglrepack' => '2026-10-19', 'note' => 'catatan', 'idusers' => $user, 'is_deleted' => 0, 'dibuat' => '2026-10-19 08:00:00', 'kunci' => $kunci]);
    }

    private function bahan(int $id, int $repack, string $barcode, ?string $grade = '2', string $origin = '1', string $pod = '2026-10-10', int $product = 21): string
    {
        return $this->insert('detailbahan', ['iddetailbahan' => $id, 'idrepack' => $repack, 'barcode' => $barcode, 'idbarang' => $product, 'idgrade' => $grade, 'qty' => '20.50', 'pcs' => '2', 'pod' => $pod, 'origin' => $origin, 'creatime' => '2026-10-19 08:30:00']);
    }

    private function hasil(int $id, int $repack, string $barcode, string $pcs = '3', string $exp = '', string $note = '', int $grade = 1): string
    {
        return $this->insert('detailhasil', ['iddetailhasil' => $id, 'idrepack' => $repack, 'kdbarcode' => $barcode, 'idbarang' => 21, 'idgrade' => $grade, 'qty' => '10.25', 'pcs' => $pcs, 'packdate' => '2026-10-19', 'exp' => $exp, 'creatime' => '2026-10-19 09:00:00', 'note' => $note, 'is_deleted' => 0]);
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

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-rp-').'.sql';
        file_put_contents($this->path, $this->ddl('repack', 'idrepack int, norepack varchar(30), tglrepack date, note varchar(255), idusers int, is_deleted int, dibuat datetime, kunci int')
            .$this->ddl('detailbahan', 'iddetailbahan int, idrepack int, barcode varchar(30), idbarang int, idgrade int, qty decimal(6,2), pcs int, pod date, origin int, creatime datetime')
            .$this->ddl('detailhasil', 'iddetailhasil int, idrepack int, kdbarcode varchar(30), idbarang int, idgrade int, qty decimal(6,2), pcs varchar(10), packdate date, exp date, creatime datetime, note varchar(100), is_deleted int')
            .$this->repack(1, 'RP-1').$this->bahan(1, 1, '1453260912000046713').$this->bahan(2, 1, '3240110000000649', null, '0', '0000-00-00')
            .$this->hasil(1, 1, '1453261019000000001', '3', '2026-12-01', 'sisa').$this->hasil(2, 1, '1453261019000000002', '', '', '', 5)
            .$this->repack(2, 'RP-1', 0, 99).$this->bahan(3, 2, '1453260912000046713').$this->hasil(3, 2, '1453261019000000003')
            .$this->repack(3, 'RP-3'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-repacks', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 3')->expectsOutputToContain('BARU  repacks')->assertSuccessful();

        $this->assertSame(0, DB::table('repacks')->count());
    }

    /** @test */
    public function headers_are_paired_and_a_duplicate_number_gets_a_suffix(): void
    {
        $this->importer(true)->assertSuccessful();

        $one = DB::table('repacks')->where('doc_no', 'RP-1')->first();
        $this->assertSame('2026-10-19', $one->repack_date);
        $this->assertSame('LOCKED', $one->status);
        $this->assertSame(1, (int) $one->kunci);
        $this->assertSame(5, (int) $one->created_by);
        $this->assertSame('2026-10-19 08:00:00', $one->created_at);
        $this->assertStringContainsString('catatan', $one->note);
        $this->assertStringContainsString('1453261019000000001: sisa', $one->note, 'catatan hasil digabung ke note');

        $two = DB::table('repacks')->where('doc_no', 'RP-1-2')->first();
        $this->assertSame('OPEN', $two->status);
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $two->created_by);
        $this->assertNotNull(DB::table('repacks')->where('doc_no', 'RP-3')->first(), 'repack tanpa bahan dan hasil tetap dibawa');
    }

    /** @test */
    public function materials_are_paired_with_grade_warehouse_and_origin_translated(): void
    {
        $this->importer(true)->assertSuccessful();

        $id = DB::table('repacks')->where('doc_no', 'RP-1')->value('id');
        $m = DB::table('repack_materials')->where(['repack_id' => $id, 'barcode' => '1453260912000046713'])->first();
        $this->assertSame(21, (int) $m->product_id);
        $this->assertSame(1, (int) $m->warehouse_id);
        $this->assertSame(2, (int) $m->grade_id, 'J02 -> JGL + FROZEN');
        $this->assertSame(20.5, (float) $m->weight);
        $this->assertSame(2, (int) $m->qty_pcs);
        $this->assertSame('BONING', $m->origin);
        $this->assertSame('IN_STOCK', $m->status);

        $blank = DB::table('repack_materials')->where(['repack_id' => $id, 'barcode' => '3240110000000649'])->first();
        $this->assertSame(1, (int) $blank->grade_id, 'grade kosong -> J01 (CHILL)');
        $this->assertSame('R-STCK', $blank->origin, 'asal 0 -> digit awal barcode (3)');
        $this->assertSame('2026-10-19', $blank->pack_date, 'tanggal kemas 0000 -> tanggal scan');
    }

    /** @test */
    public function results_are_paired_and_the_same_barcode_may_be_material_in_two_repacks(): void
    {
        $this->importer(true)->assertSuccessful();

        $id = DB::table('repacks')->where('doc_no', 'RP-1')->value('id');
        $r = DB::table('repack_results')->where(['repack_id' => $id, 'barcode' => '1453261019000000001'])->first();
        $this->assertSame(10.25, (float) $r->weight);
        $this->assertSame(3, (int) $r->qty_pcs);
        $this->assertSame('2026-12-01', $r->exp_date);

        $blank = DB::table('repack_results')->where('barcode', '1453261019000000002')->first();
        $this->assertSame(0, (int) $blank->qty_pcs, 'pcs kosong -> 0');
        $this->assertSame(3, (int) $blank->grade_id, 'J03 -> JGL + A');
        $this->assertNull($blank->exp_date);

        $this->assertSame(2, DB::table('repack_materials')->where('barcode', '1453260912000046713')->count());
    }

    /** @test */
    public function it_creates_no_stock_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 3')->assertSuccessful();

        $this->assertSame(3, DB::table('repacks')->count());
        $this->assertSame(3, DB::table('repack_materials')->count());
        $this->assertSame(3, DB::table('repack_results')->count());
        $this->assertSame(0, DB::table('beef_stocks')->count());
        $this->assertSame(3, DB::table('legacy_import_map')->where('entity', 'repack')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
