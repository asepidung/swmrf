<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-bonings`: boning + label legacy -> bonings + boning_items. Fixture dikarang sendiri.
 */
class ImportLegacyBoningsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private string $labels1;

    private string $labels2;

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

    private function boning(int $id, string $batch, int $deleted = 0, int $kunci = 1, int $user = 5, string $qty = '14'): string
    {
        return $this->insert('boning', ['idboning' => $id, 'batchboning' => $batch, 'idsupplier' => 3, 'tglboning' => '2026-10-20', 'qtysapi' => $qty, 'dibuat' => '2026-10-19 11:51:23', 'iduser' => $user, 'keterangan' => 'STEER & BULL', 'kunci' => $kunci, 'is_deleted' => $deleted]);
    }

    private function label(int $id, int $boning, string $barcode, int $grade = 2, string $qty = '8.16', string $pcs = '1', int $deleted = 0, int $product = 21, string $ph = '5.6'): string
    {
        return $this->insert('labelboning', ['idlabelboning' => $id, 'idboning' => $boning, 'idbarang' => $product, 'qty' => $qty, 'pcs' => $pcs, 'ph' => $ph, 'packdate' => '2026-10-17', 'exp' => null, 'kdbarcode' => $barcode, 'creatime' => '2026-10-17 11:36:15', 'iduser' => 16, 'idgrade' => $grade, 'is_deleted' => $deleted]);
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

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-bn-').'.sql';
        $this->labels1 = tempnam(sys_get_temp_dir(), 'legacy-lb1-').'.sql';
        $this->labels2 = tempnam(sys_get_temp_dir(), 'legacy-lb2-').'.sql';

        file_put_contents($this->path, $this->ddl('boning', 'idboning int, batchboning varchar(20), idsupplier int, tglboning date, qtysapi decimal(10,2), dibuat datetime, iduser int, keterangan varchar(50), kunci int, is_deleted int')
            .$this->boning(1, 'BN0001').$this->boning(2, 'BN0002', 0, 0, 99).$this->boning(3, 'BN0003', 1));

        $lbl = 'idlabelboning int, idboning int, idbarang int, qty decimal(10,2), pcs varchar(5), ph varchar(5), packdate date, exp date, kdbarcode varchar(30), creatime datetime, iduser int, idgrade int, is_deleted int';
        file_put_contents($this->labels1, $this->ddl('labelboning', $lbl)
            .$this->label(1, 1, '1453260912000046713', 2, '17.40', '3').$this->label(2, 1, '1453260912000046714', 1, '8.16', '').$this->label(3, 1, '1453260912000046715', 2, '4', '2', 1));
        file_put_contents($this->labels2, $this->ddl('labelboning', $lbl)
            .$this->label(4, 2, '1453261001000000001', 2, '5.5', '1', 0, 21, '').$this->label(5, 3, '1453261001000000002').$this->label(6, 1, '1453260912000046716', 2, '3'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->labels1);
        @unlink($this->labels2);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-bonings', ['--source' => $this->path, '--labels' => $this->labels1.','.$this->labels2] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 2')->expectsOutputToContain('BARU  bonings')->assertSuccessful();

        $this->assertSame(0, DB::table('bonings')->count());
        $this->assertSame(0, DB::table('boning_items')->count());
    }

    /** @test */
    public function deleted_bonings_and_deleted_labels_are_ignored_and_labels_come_from_every_file(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame(['BN0001', 'BN0002'], DB::table('bonings')->orderBy('doc_no')->pluck('doc_no')->all());
        $this->assertSame(4, DB::table('boning_items')->count(), 'label yang dihapus dan label pada boning yang dihapus diabaikan; label dibaca dari kedua berkas');
        $this->assertNull(DB::table('boning_items')->where('barcode', '1453260912000046715')->first());
    }

    /** @test */
    public function boning_columns_are_paired_as_agreed(): void
    {
        $this->importer(true)->assertSuccessful();

        $b = DB::table('bonings')->where('doc_no', 'BN0001')->first();
        $this->assertSame('2026-10-20', $b->boning_date);
        $this->assertSame('LOCKED', $b->status);
        $this->assertSame(1, (int) $b->kunci);
        $this->assertSame(1, (int) $b->legacy_imported, 'boning impor tidak ikut Draft Costing');
        $this->assertSame(5, (int) $b->created_by);
        $this->assertSame('STEER & BULL | Jumlah sapi: 14', $b->note);

        $open = DB::table('bonings')->where('doc_no', 'BN0002')->first();
        $this->assertSame('OPEN', $open->status);
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $open->created_by);
        $this->assertSame(0, DB::table('boning_carcasses')->count(), 'hubungan ke karkas tidak dibuat');
    }

    /** @test */
    public function item_columns_are_paired_as_agreed(): void
    {
        $this->importer(true)->assertSuccessful();

        $b = (int) DB::table('bonings')->where('doc_no', 'BN0001')->value('id');
        $item = DB::table('boning_items')->where('barcode', '1453260912000046713')->first();
        $this->assertSame($b, (int) $item->boning_id);
        $this->assertSame(21, (int) $item->product_id);
        $this->assertSame(1, (int) $item->warehouse_id);
        $this->assertSame(2, (int) $item->grade_id, 'J02 -> JGL + FROZEN');
        $this->assertSame(17.4, (float) $item->weight);
        $this->assertSame(3, (int) $item->qty_pcs);
        $this->assertSame(5.6, (float) $item->ph_level);
        $this->assertSame('2026-10-17', $item->pack_date);
        $this->assertSame('BN0001', $item->batch_no);
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $item->created_by, 'pembuat label 16 tidak ada -> akun pengganti');

        $blank = DB::table('boning_items')->where('barcode', '1453260912000046714')->first();
        $this->assertSame(0, (int) $blank->qty_pcs, 'pcs kosong -> 0');
        $this->assertSame(1, (int) $blank->grade_id, 'J01 -> CHILL');
        $this->assertNull(DB::table('boning_items')->where('barcode', '1453261001000000001')->value('ph_level'), 'pH kosong tetap kosong');
    }

    /** @test */
    public function it_creates_no_stock_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 2')->assertSuccessful();

        $this->assertSame(2, DB::table('bonings')->count());
        $this->assertSame(4, DB::table('boning_items')->count());
        $this->assertSame(0, DB::table('beef_stocks')->count());
        $this->assertSame(2, DB::table('legacy_import_map')->where('entity', 'boning')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
