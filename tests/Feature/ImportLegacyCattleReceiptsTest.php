<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-cattle-receipts`: receive + weighing sapi legacy. Fixture dikarang sendiri.
 */
class ImportLegacyCattleReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private function ddl(): string
    {
        $cols = [
            'pocattle' => 'idpo int, nopo varchar(30), podate date, arrival_date date, idsupplier int, note varchar(50), creatime datetime, createby int, updatetime datetime, updateby int, is_deleted int',
            'cattle_receive' => 'idreceive int, idpo int, receipt_date date, doc_no varchar(30), sv_ok int, skkh_ok int, note varchar(50), is_deleted int, creatime datetime, createby int, updatetime datetime, updateby int',
            'cattle_receive_detail' => 'idreceivedetail int, idreceive int, eartag varchar(20), weight decimal(10,2), class varchar(30), rfid varchar(30), notes varchar(50), creatime datetime, createby int, updatetime datetime, updateby int',
            'weight_cattle' => 'idweigh int, idreceive int, weigh_no varchar(30), weigh_date date, idweigher int, note varchar(50), is_deleted int, creatime datetime, createby int, updatetime datetime, updateby int',
            'weight_cattle_detail' => 'idweighdetail int, idweigh int, idreceivedetail int, eartag varchar(20), weight decimal(10,2), notes varchar(50), creatime datetime, createby int, updatetime datetime, updateby int',
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

    private function receive(int $id, int $po, string $date, int $deleted = 0, int $user = 5): string
    {
        return $this->insert('cattle_receive', ['idreceive' => $id, 'idpo' => $po, 'receipt_date' => $date, 'doc_no' => 'SJ-'.$id, 'sv_ok' => 1, 'skkh_ok' => 0, 'note' => '', 'is_deleted' => $deleted, 'creatime' => $date.' 08:00:00', 'createby' => $user, 'updatetime' => null, 'updateby' => null]);
    }

    private function cow(int $id, int $receive, string $tag, string $weight = '650', string $class = 'STEER'): string
    {
        return $this->insert('cattle_receive_detail', ['idreceivedetail' => $id, 'idreceive' => $receive, 'eartag' => $tag, 'weight' => $weight, 'class' => $class, 'rfid' => null, 'notes' => '', 'creatime' => '2026-10-19 08:00:00', 'createby' => 5, 'updatetime' => null, 'updateby' => null]);
    }

    private function weigh(int $id, int $receive, string $number, string $date, int $deleted = 0): string
    {
        return $this->insert('weight_cattle', ['idweigh' => $id, 'idreceive' => $receive, 'weigh_no' => $number, 'weigh_date' => $date, 'idweigher' => 5, 'note' => '', 'is_deleted' => $deleted, 'creatime' => $date.' 09:00:00', 'createby' => 5, 'updatetime' => null, 'updateby' => null]);
    }

    private function weighLine(int $id, int $weigh, int $head, string $weight): string
    {
        return $this->insert('weight_cattle_detail', ['idweighdetail' => $id, 'idweigh' => $weigh, 'idreceivedetail' => $head, 'eartag' => 'x', 'weight' => $weight, 'notes' => '', 'creatime' => '2026-10-19 09:00:00', 'createby' => 5, 'updatetime' => null, 'updateby' => null]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $user = User::create(['name' => 'Pembuat', 'username' => 'pembuat', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);

        DB::table('suppliers')->insert(['id' => 7, 'name' => 'PT SUPPLIER', 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('cattle_classes')->insert([['id' => 1, 'name' => 'STEER', 'created_at' => $now, 'updated_at' => $now], ['id' => 6, 'name' => 'NONPREMIUM', 'created_at' => $now, 'updated_at' => $now]]);

        foreach ([['CPO-1', 1], ['CPO-2', 2]] as [$number]) {
            DB::table('purchase_cattles')->insert(['document_number' => $number, 'supplier_id' => 7, 'shipping_date' => '2026-10-18', 'created_by' => 5, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-cr-').'.sql';
        file_put_contents($this->path, $this->ddl()
            .$this->insert('pocattle', ['idpo' => 1, 'nopo' => 'CPO-1', 'podate' => '2026-10-18', 'arrival_date' => '2026-10-18', 'idsupplier' => 7, 'note' => '', 'creatime' => '2026-10-18 10:00:00', 'createby' => 5, 'updatetime' => null, 'updateby' => null, 'is_deleted' => 0])
            .$this->insert('pocattle', ['idpo' => 2, 'nopo' => 'CPO-2', 'podate' => '2026-10-19', 'arrival_date' => '2026-10-19', 'idsupplier' => 7, 'note' => '', 'creatime' => '2026-10-19 10:00:00', 'createby' => 5, 'updatetime' => null, 'updateby' => null, 'is_deleted' => 0])
            // receive 1 (lama) dan 2 (baru) memakai eartag 5564 yang sama; receive 3 dihapus; receive 4 tanpa PO
            .$this->receive(1, 1, '2026-10-18').$this->cow(1, 1, '5564').$this->cow(2, 1, ' a77 ', '0', 'NonPREMIUM')
            .$this->receive(2, 2, '2026-10-19', 0, 99).$this->cow(3, 2, '5564', '600')
            .$this->receive(3, 2, '2026-10-19', 1).$this->cow(4, 3, '9999')
            .$this->receive(4, 77, '2026-10-19').$this->cow(5, 4, '8888')
            .$this->weigh(1, 1, 'WGH-1', '2026-10-18').$this->weighLine(1, 1, 1, '610.5').$this->weighLine(2, 1, 2, '0')
            .$this->weigh(2, 2, 'WGH-2', '2026-10-19').$this->weighLine(3, 2, 3, '598')
            .$this->weigh(3, 3, 'WGH-3', '2026-10-19', 1).$this->weighLine(4, 3, 4, '500'));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-cattle-receipts', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 2')->expectsOutputToContain('BARU  cattle_receivings')->assertSuccessful();

        $this->assertSame(0, DB::table('cattle_receivings')->count());
    }

    /** @test */
    public function deleted_documents_and_documents_without_a_parent_are_ignored(): void
    {
        $this->importer(true)->expectsOutputToContain('PO sapi induk idpo=77 tidak ada')->assertSuccessful();

        $this->assertSame(['RCV-0001', 'RCV-0002'], DB::table('cattle_receivings')->orderBy('receiving_number')->pluck('receiving_number')->all());
        $this->assertSame(['WGH-1', 'WGH-2'], DB::table('cattle_weighings')->orderBy('weighing_number')->pluck('weighing_number')->all());
    }

    /** @test */
    public function receiving_columns_are_paired_as_agreed(): void
    {
        $this->importer(true)->assertSuccessful();

        $r = DB::table('cattle_receivings')->where('receiving_number', 'RCV-0001')->first();
        $this->assertSame(DB::table('purchase_cattles')->where('document_number', 'CPO-1')->value('id'), (int) $r->purchase_cattle_id);
        $this->assertSame(7, (int) $r->supplier_id);
        $this->assertSame('2026-10-18', $r->receive_date);
        $this->assertSame('SJ-1', $r->doc_no);
        $this->assertSame(1, (int) $r->sv_ok);
        $this->assertSame(0, (int) $r->skkh_ok);
        $this->assertSame(5, (int) $r->created_by);

        $classes = DB::table('cattle_receiving_items')->where('cattle_receiving_id', $r->id)->orderBy('id')->pluck('cattle_class_id')->all();
        $this->assertSame([1, 6], $classes, 'NonPREMIUM = NONPREMIUM');
        $this->assertSame('A77', DB::table('cattle_receiving_items')->where('cattle_class_id', 6)->value('eartag'), 'eartag dinormalkan: huruf besar, tanpa spasi tepi');
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) DB::table('cattle_receivings')->where('receiving_number', 'RCV-0002')->value('created_by'));
    }

    /** @test */
    public function a_duplicate_eartag_keeps_the_original_on_the_newest_and_gets_a_date_suffix_on_the_older(): void
    {
        $this->importer(true)->assertSuccessful();

        $tags = DB::table('cattle_receiving_items')->orderBy('id')->pluck('eartag')->all();
        $this->assertContains('5564', $tags);
        $this->assertContains('5564-261018', $tags, 'yang lebih lama (18 Okt) diberi akhiran tanggal terima');
        $this->assertSame(
            '5564',
            DB::table('cattle_receiving_items')->where('cattle_receiving_id', DB::table('cattle_receivings')->where('receiving_number', 'RCV-0002')->value('id'))->value('eartag'),
            'yang terbaru (19 Okt) memakai eartag aslinya'
        );
    }

    /** @test */
    public function an_unweighed_cattle_is_empty_not_zero_and_weighing_items_link_to_the_receiving_items(): void
    {
        $this->importer(true)->assertSuccessful();

        $w = DB::table('cattle_weighings')->where('weighing_number', 'WGH-1')->first();
        $items = DB::table('cattle_weighing_items')->where('cattle_weighing_id', $w->id)->orderBy('id')->get();
        $this->assertSame(610.5, (float) $items[0]->actual_weight);
        $this->assertNull($items[1]->actual_weight, 'berat 0 di legacy -> kosong');
        $this->assertSame(DB::table('cattle_receiving_items')->where('eartag', 'A77')->value('id'), (int) $items[1]->cattle_receiving_item_id);
    }

    /** @test */
    public function running_it_twice_creates_nothing_new_and_keeps_the_same_eartags(): void
    {
        $this->importer(true)->assertSuccessful();
        $before = DB::table('cattle_receiving_items')->orderBy('id')->pluck('eartag')->all();

        $this->importer(true)->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 2')->assertSuccessful();

        $this->assertSame($before, DB::table('cattle_receiving_items')->orderBy('id')->pluck('eartag')->all());
        $this->assertSame(2, DB::table('cattle_weighings')->count());
    }

    /** @test */
    public function the_legacy_to_new_id_map_is_written_and_restored_on_a_rerun(): void
    {
        $this->importer(true)->assertSuccessful();

        $count = fn (string $entity) => DB::table('legacy_import_map')->where('entity', $entity)->count();
        $this->assertSame(2, $count('cattle_receiving'));
        $this->assertSame(3, $count('cattle_receiving_item'));
        $this->assertSame(2, $count('cattle_weighing'));
        $this->assertSame(3, $count('cattle_weighing_item'));

        DB::table('legacy_import_map')->delete();
        $this->importer(true)->assertSuccessful();

        $this->assertSame(2, $count('cattle_receiving'), 'penghubung dipulihkan pada pengulangan');
        $this->assertSame(3, $count('cattle_receiving_item'));
        $this->assertSame(2, $count('cattle_weighing'));
        $this->assertSame(3, $count('cattle_weighing_item'));
        $this->assertSame(
            DB::table('cattle_receiving_items')->where('eartag', 'A77')->value('id'),
            (int) DB::table('legacy_import_map')->where(['entity' => 'cattle_receiving_item', 'legacy_id' => 2])->value('new_id')
        );
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
