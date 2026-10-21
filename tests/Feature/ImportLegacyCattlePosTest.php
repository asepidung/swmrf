<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-cattle-pos`: riwayat PO sapi hidup legacy -> purchase_cattles. Fixture dikarang sendiri.
 */
class ImportLegacyCattlePosTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private function ddl(): string
    {
        $cols = [
            'pocattle' => 'idpo int, nopo varchar(30), podate date, arrival_date date, idsupplier int, note varchar(50), creatime datetime, createby int, updatetime datetime, updateby int, is_deleted int',
            'pocattledetail' => 'idpodetail int, idpo int, class varchar(30), qty decimal(10,2), price decimal(15,2), notes varchar(50), creatime datetime, createby int, updatetime datetime, updateby int, is_deleted int',
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

    private function po(int $id, string $number, int $supplier = 7, int $user = 5, int $deleted = 0, string $note = ''): string
    {
        return $this->insert('pocattle', ['idpo' => $id, 'nopo' => $number, 'podate' => '2026-10-18', 'arrival_date' => '2026-10-20', 'idsupplier' => $supplier, 'note' => $note, 'creatime' => '2026-10-19 19:59:22', 'createby' => $user, 'updatetime' => null, 'updateby' => null, 'is_deleted' => $deleted]);
    }

    private function line(int $id, int $po, string $class, string $qty = '12', string $price = '62000', int $deleted = 0): string
    {
        return $this->insert('pocattledetail', ['idpodetail' => $id, 'idpo' => $po, 'class' => $class, 'qty' => $qty, 'price' => $price, 'notes' => '', 'creatime' => '2026-10-19 19:59:22', 'createby' => 5, 'updatetime' => null, 'updateby' => null, 'is_deleted' => $deleted]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $user = User::create(['name' => 'Pembuat', 'username' => 'pembuat', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);

        foreach ([7 => 'PT SUPPLIER', 26 => 'PT GABUNGAN'] as $id => $name) {
            DB::table('suppliers')->insert(['id' => $id, 'name' => $name, 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([1 => 'STEER', 2 => 'BULL', 6 => 'NONPREMIUM'] as $id => $name) {
            DB::table('cattle_classes')->insert(['id' => $id, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-cpo-').'.sql';
        file_put_contents($this->path, $this->ddl()
            .$this->po(1, 'CPO-1', 7, 5, 0, 'catatan').$this->line(1, 1, 'STEER', '12', '62000').$this->line(2, 1, 'NonPREMIUM', '3', '50000').$this->line(3, 1, 'BULL', '5', '1', 1)
            .$this->po(2, 'CPO-2', 14, 99).$this->line(4, 2, 'BULL', '10', '60000')
            .$this->po(3, 'CPO-3', 7, 5, 1).$this->line(5, 3, 'STEER')
            .$this->po(4, 'CPO-1').$this->line(6, 4, 'STEER', '1', '1000'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-cattle-pos', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 3')->expectsOutputToContain('BARU  purchase_cattles')->assertSuccessful();

        $this->assertSame(0, DB::table('purchase_cattles')->count());
    }

    /** @test */
    public function columns_are_paired_as_agreed_and_deleted_rows_are_ignored(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame(['CPO-1', 'CPO-1-2', 'CPO-2'], DB::table('purchase_cattles')->orderBy('document_number')->pluck('document_number')->all(), 'PO yang dihapus diabaikan; nomor kembar diberi akhiran');

        $po = DB::table('purchase_cattles')->where('document_number', 'CPO-1')->first();
        $this->assertSame(7, (int) $po->supplier_id);
        $this->assertSame('2026-10-20', $po->shipping_date);
        $this->assertSame('catatan', $po->summary_note);
        $this->assertSame(5, (int) $po->created_by);
        $this->assertSame('2026-10-18 19:59:22', $po->created_at, 'tanggal dibuat = podate, jam dari creatime');

        $items = DB::table('purchase_cattle_items')->where('purchase_cattle_id', $po->id)->orderBy('id')->get();
        $this->assertCount(2, $items, 'baris yang dihapus diabaikan');
        $this->assertSame(1, (int) $items[0]->cattle_class_id);
        $this->assertSame(6, (int) $items[1]->cattle_class_id, 'NonPREMIUM = NONPREMIUM');
        $this->assertSame(62000.0, (float) $items[0]->price);
    }

    /** @test */
    public function a_merged_supplier_a_missing_creator_and_a_duplicate_number_are_handled(): void
    {
        $this->importer(true)->assertSuccessful();

        $two = DB::table('purchase_cattles')->where('document_number', 'CPO-2')->first();
        $this->assertSame(26, (int) $two->supplier_id);
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $two->created_by);
        $this->assertStringContainsString('Nomor kembar di legacy (idpo=4)', DB::table('purchase_cattles')->where('document_number', 'CPO-1-2')->value('summary_note'));
    }

    /** @test */
    public function an_unknown_class_is_refused_and_nothing_is_written(): void
    {
        file_put_contents($this->path, $this->ddl().$this->po(1, 'CPO-1').$this->line(1, 1, 'KERBAU'));

        $this->importer(true)->expectsOutputToContain('kelas sapi "KERBAU" tidak dikenal')->assertFailed();

        $this->assertSame(0, DB::table('purchase_cattles')->count());
    }

    /** @test */
    public function running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 3')->assertSuccessful();
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
