<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-carcasses`: karkas legacy -> carcasses, dihubungkan lewat legacy_import_map. Fixture dikarang sendiri.
 */
class ImportLegacyCarcassesTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private int $weighingId;

    /** @var array<int, int> */
    private array $weighItemIds = [];

    private function ddl(): string
    {
        $cols = [
            'carcase' => 'idcarcase int, killdate date, idsupplier int, idweight int, note varchar(50), idusers int, is_deleted int',
            'carcasedetail' => 'iddetail int, idcarcase int, idweightdetail int, breed varchar(20), berat decimal(10,2), eartag varchar(20), carcase1 decimal(10,2), carcase2 decimal(10,2), hides decimal(10,2), tail decimal(10,2)',
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

    private function carcase(int $id, int $weight, string $date = '2026-10-19', int $deleted = 0, int $user = 5): string
    {
        return $this->insert('carcase', ['idcarcase' => $id, 'killdate' => $date, 'idsupplier' => 7, 'idweight' => $weight, 'note' => '', 'idusers' => $user, 'is_deleted' => $deleted]);
    }

    private function detail(int $id, int $carcase, int $weighDetail, string $c1 = '174.4', string $c2 = '185.1'): string
    {
        return $this->insert('carcasedetail', ['iddetail' => $id, 'idcarcase' => $carcase, 'idweightdetail' => $weighDetail, 'breed' => 'STEER', 'berat' => '652', 'eartag' => '5564', 'carcase1' => $c1, 'carcase2' => $c2, 'hides' => '44.4', 'tail' => '0']);
    }

    private function map(string $entity, int $legacy, int $new): void
    {
        DB::table('legacy_import_map')->insert(['entity' => $entity, 'legacy_id' => $legacy, 'new_id' => $new, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $user = User::create(['name' => 'Pembuat', 'username' => 'pembuat', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);

        DB::table('suppliers')->insert(['id' => 7, 'name' => 'PT SUPPLIER', 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('cattle_classes')->insert(['id' => 1, 'name' => 'STEER', 'created_at' => $now, 'updated_at' => $now]);
        $po = DB::table('purchase_cattles')->insertGetId(['document_number' => 'CPO-1', 'supplier_id' => 7, 'shipping_date' => '2026-10-18', 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);
        $rc = DB::table('cattle_receivings')->insertGetId(['receiving_number' => 'RCV-0001', 'purchase_cattle_id' => $po, 'supplier_id' => 7, 'receive_date' => '2026-10-18', 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);
        $this->weighingId = DB::table('cattle_weighings')->insertGetId(['weighing_number' => 'WGH-1', 'cattle_receiving_id' => $rc, 'weighing_date' => '2026-10-18', 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);

        foreach ([3957 => 'A1', 3958 => 'A2'] as $legacy => $tag) {
            $item = DB::table('cattle_receiving_items')->insertGetId(['cattle_receiving_id' => $rc, 'cattle_class_id' => 1, 'eartag' => $tag, 'initial_weight' => 600, 'created_at' => $now, 'updated_at' => $now]);
            $this->weighItemIds[$legacy] = DB::table('cattle_weighing_items')->insertGetId(['cattle_weighing_id' => $this->weighingId, 'cattle_receiving_item_id' => $item, 'actual_weight' => 599, 'created_at' => $now, 'updated_at' => $now]);
            $this->map('cattle_weighing_item', $legacy, $this->weighItemIds[$legacy]);
        }

        $this->map('cattle_weighing', 109, $this->weighingId);

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-crc-').'.sql';
        file_put_contents($this->path, $this->ddl()
            .$this->carcase(369, 109).$this->detail(14113, 369, 3957).$this->detail(14114, 369, 3958, '170', '180')
            .$this->carcase(100, 0, '2025-03-01').$this->detail(1, 100, 1111).$this->detail(2, 100, 1112)
            .$this->carcase(370, 109, '2026-10-19', 1).$this->detail(3, 370, 3957));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-carcasses', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 1')->expectsOutputToContain('BARU  carcasses')->assertSuccessful();

        $this->assertSame(0, DB::table('carcasses')->count());
    }

    /** @test */
    public function a_linked_carcass_is_paired_and_old_unlinked_ones_are_reported_not_brought(): void
    {
        $this->importer(true)->expectsOutputToContain('TIDAK DIBAWA (tanpa catatan timbang di legacy): 1 karkas dengan 2 ekor')->assertSuccessful();

        $this->assertSame(['CRC-0369'], DB::table('carcasses')->pluck('carcass_number')->all(), 'yang dihapus dan yang tanpa timbang tidak dibawa');

        $c = DB::table('carcasses')->first();
        $this->assertSame($this->weighingId, (int) $c->cattle_weighing_id);
        $this->assertSame('2026-10-19', $c->kill_date);
        $this->assertSame(5, (int) $c->created_by);

        $items = DB::table('carcass_items')->where('carcass_id', $c->id)->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame($this->weighItemIds[3957], (int) $items[0]->cattle_weighing_item_id);
        $this->assertSame(174.4, (float) $items[0]->carcass_1);
        $this->assertSame(185.1, (float) $items[0]->carcass_2);
        $this->assertSame(44.4, (float) $items[0]->hides);
    }

    /** @test */
    public function the_id_map_gets_the_carcass_and_its_items(): void
    {
        $this->importer(true)->assertSuccessful();

        $carcassId = (int) DB::table('carcasses')->value('id');
        $this->assertSame($carcassId, (int) DB::table('legacy_import_map')->where(['entity' => 'carcass', 'legacy_id' => 369])->value('new_id'));
        $this->assertSame(2, DB::table('legacy_import_map')->where('entity', 'carcass_item')->count());
    }

    /** @test */
    public function running_it_twice_creates_nothing_new_and_restores_a_lost_map(): void
    {
        $this->importer(true)->assertSuccessful();
        DB::table('legacy_import_map')->whereIn('entity', ['carcass', 'carcass_item'])->delete();

        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 1')->assertSuccessful();

        $this->assertSame(1, DB::table('carcasses')->count());
        $this->assertSame(1, DB::table('legacy_import_map')->where('entity', 'carcass')->count(), 'penghubung dipulihkan');
        $this->assertSame(2, DB::table('legacy_import_map')->where('entity', 'carcass_item')->count());
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
