<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-tallies`: tally + karton legacy -> tallies + tally_items. Fixture dikarang sendiri.
 */
class ImportLegacyTalliesTest extends TestCase
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

    private function tally(int $id, int $so, string $number, string $stat = 'DO'): string
    {
        return $this->insert('tally', ['idtally' => $id, 'idso' => $so, 'sonumber' => 'SO-'.$so, 'notally' => $number, 'deliverydate' => '2026-10-21', 'idcustomer' => 6, 'po' => '', 'stat' => $stat, 'sealnumb' => 'S123', 'creatime' => '2026-10-20 09:00:00', 'is_deleted' => 0]);
    }

    private function carton(int $id, int $tally, string $barcode, ?string $grade = '2', string $origin = '1', int $product = 21, string $ph = '5.6', string $exp = '', string $pod = '2026-10-17'): string
    {
        return $this->insert('tallydetail', ['idtallydetail' => $id, 'idtally' => $tally, 'barcode' => $barcode, 'idgrade' => $grade, 'ph' => $ph, 'idbarang' => $product, 'weight' => '17.40', 'pcs' => '3', 'pod' => $pod, 'origin' => $origin, 'creatime' => '2026-10-20 09:05:00', 'exp' => $exp]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        DB::table('warehouses')->insert([['id' => 1, 'code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now], ['id' => 2, 'code' => 'PRM', 'name' => 'PERUM', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]]);
        DB::table('grades')->insert(array_map(fn ($i, $n) => ['id' => $i, 'name' => $n, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now], [1, 2, 3, 4, 5], ['CHILL', 'FROZEN', 'A', 'B', 'R']));
        $category = DB::table('product_categories')->insertGetId(['name' => 'PRIME CUT', 'prefix' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('products')->insert(['id' => 21, 'code' => '100207', 'name' => 'OPERIB WHOLE', 'category_id' => $category, 'structure_type' => 'main', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $segment = DB::table('customer_segments')->insertGetId(['name' => 'RETAIL', 'created_at' => $now, 'updated_at' => $now]);
        $group = DB::table('customer_groups')->insertGetId(['name' => 'REGULER', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => 6, 'name' => 'LION DCA', 'customer_group_id' => $group, 'customer_segment_id' => $segment, 'address' => '-', 'top' => 0, 'default_discount' => 0, 'invoice_exchange' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // SO hasil impor: legacy idso 1..5 -> sales_orders, dihubungkan lewat legacy_import_map.
        foreach (range(1, 5) as $i) {
            $id = DB::table('sales_orders')->insertGetId(['so_number' => 'SO-'.$i, 'customer_id' => 6, 'delivery_date' => '2026-10-21', 'status' => 'completed', 'down_payment' => 0, 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('legacy_import_map')->insert(['entity' => 'sales_order', 'legacy_id' => $i, 'new_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-tl-').'.sql';
        file_put_contents($this->path, $this->ddl('tally', 'idtally int, idso int, sonumber varchar(30), notally varchar(30), deliverydate date, idcustomer int, po varchar(30), stat varchar(10), sealnumb varchar(7), creatime datetime, is_deleted int')
            .$this->ddl('tallydetail', 'idtallydetail int, idtally int, barcode varchar(30), idgrade int, ph varchar(5), idbarang int, weight decimal(6,2), pcs int, pod date, origin varchar(2), creatime datetime, exp date')
            .$this->tally(1, 1, 'TS00001').$this->carton(1, 1, '10036231101000009054').$this->carton(2, 1, '10036231101000009055', null, '', 21, '')
            .$this->tally(2, 2, 'TS00001', 'Rejected').$this->carton(3, 2, '3240110000000649', '1', '3', 21, '5.5', '2026-12-01')
            .$this->tally(3, 3, 'TS00003', '').$this->carton(4, 3, '10036231101000009054', '4')
            .$this->tally(4, 4, 'TS00004').$this->tally(5, 4, 'TS00005').$this->carton(5, 5, '10036231101000009099', '2', '1', 21, '5.6', '', '0000-00-00')
            .$this->tally(6, 99, 'TS00006').$this->carton(6, 6, '10036231101000009100', '2', '1', 777));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = false, bool $withFallback = true)
    {
        return $this->artisan('legacy:import-tallies', ['--source' => $this->path] + ($withFallback ? ['--fallback-user' => 'cadangan'] : []) + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_shows_pairs_and_writes_nothing(): void
    {
        $this->importer()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat: 4')->expectsOutputToContain('BARU  tallies')->assertSuccessful();

        $this->assertSame(0, DB::table('tallies')->count());
        $this->assertSame(0, DB::table('tally_items')->count());
    }

    /** @test */
    public function headers_are_paired_and_duplicate_numbers_get_a_suffix(): void
    {
        $this->importer(true)->assertSuccessful();

        $soId = (int) DB::table('legacy_import_map')->where(['entity' => 'sales_order', 'legacy_id' => 1])->value('new_id');
        $first = DB::table('tallies')->where('tally_number', 'TS00001')->first();
        $this->assertSame($soId, (int) $first->sales_order_id);
        $this->assertSame('do', $first->status, 'DO -> do');
        $this->assertSame('S123', $first->seal_number);
        $this->assertSame(User::where('username', 'cadangan')->value('id'), (int) $first->created_by);
        $this->assertSame('2026-10-20 09:00:00', $first->created_at);

        $this->assertSame('locked', DB::table('tallies')->where('tally_number', 'TS00001-2')->value('status'), 'Rejected -> locked, nomor kembar -> -2');
        $this->assertSame('processing', DB::table('tallies')->where('tally_number', 'TS00003')->value('status'), 'status kosong -> processing');
    }

    /** @test */
    public function cartons_are_paired_with_grade_warehouse_and_origin_translated(): void
    {
        $this->importer(true)->assertSuccessful();

        $item = DB::table('tally_items')->where('barcode', '3240110000000649')->first();
        $this->assertSame(1, (int) $item->warehouse_id);
        $this->assertSame(1, (int) $item->grade_id, 'J01 -> JGL + CHILL');
        $this->assertSame(21, (int) $item->product_id);
        $this->assertSame(17.4, (float) $item->weight);
        $this->assertSame(3, (int) $item->qty_pcs);
        $this->assertSame(5.5, (float) $item->ph_level);
        $this->assertSame('2026-10-17', $item->pack_date);
        $this->assertSame('2026-12-01', $item->exp_date);
        $this->assertSame('R-STCK', $item->origin, 'digit asal 3 diterjemahkan');
        $this->assertNull($item->original_barcode);

        $prm = DB::table('tally_items')->where(['barcode' => '10036231101000009054', 'tally_id' => DB::table('tallies')->where('tally_number', 'TS00003')->value('id')])->first();
        $this->assertSame(2, (int) $prm->warehouse_id, 'P02 -> PRM + FROZEN');
        $this->assertSame(2, (int) $prm->grade_id);
    }

    /** @test */
    public function blank_grade_origin_ph_and_exp_are_handled(): void
    {
        $this->importer(true)->assertSuccessful();

        $item = DB::table('tally_items')->where('barcode', '10036231101000009055')->first();
        $this->assertSame(1, (int) $item->warehouse_id, 'grade kosong -> J01');
        $this->assertSame(1, (int) $item->grade_id);
        $this->assertSame('BONING', $item->origin, 'asal kosong -> digit awal barcode');
        $this->assertNull($item->ph_level);
        $this->assertNull($item->exp_date);
    }

    /** @test */
    public function the_same_barcode_in_another_tally_is_kept_as_is(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame(2, DB::table('tally_items')->where('barcode', '10036231101000009054')->count(), 'retur lalu kirim ulang: sah');
        $this->assertSame(0, DB::table('tally_items')->whereNotNull('original_barcode')->count());
    }

    /** @test */
    public function an_empty_second_tally_on_the_same_so_and_a_missing_so_are_skipped(): void
    {
        $this->importer(true)->expectsOutputToContain('tally kosong pada SO yang punya tally lain 1')->assertSuccessful();

        $this->assertNull(DB::table('tallies')->where('tally_number', 'TS00004')->first(), 'tally kosong dilewati walau lebih dulu');
        $this->assertNotNull(DB::table('tallies')->where('tally_number', 'TS00005')->first());
        $this->assertNull(DB::table('tallies')->where('tally_number', 'TS00006')->first(), 'SO 99 tidak ada');
    }

    /** @test */
    public function a_zero_pack_date_falls_back_to_the_scan_date(): void
    {
        $this->importer(true)->assertSuccessful();

        $this->assertSame('2026-10-20', DB::table('tally_items')->where('barcode', '10036231101000009099')->value('pack_date'));
    }

    /** @test */
    public function the_id_map_is_written_it_creates_no_side_effects_and_running_it_twice_creates_nothing_new(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->expectsOutputToContain('sudah ada (dilewati): 4')->assertSuccessful();

        $this->assertSame(4, DB::table('legacy_import_map')->where('entity', 'tally')->count());
        $this->assertSame(4, DB::table('tallies')->count());
        $this->assertSame(0, DB::table('beef_stocks')->count());
    }

    /** @test */
    public function an_approved_legacy_tally_that_is_not_yet_a_delivery_order_is_locked(): void
    {
        $this->assertSame('locked', \App\Console\Commands\ImportLegacyTallies::STATUS_MAP['Approved']);
    }

    /** @test */
    public function the_fallback_user_is_required(): void
    {
        $this->importer(true, false)->assertFailed();
    }
}
