<?php

namespace Tests\Feature;

use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:reconcile` untuk dokumen pembelian: dokumen baru dicari lewat NOMOR (dengan akhiran -2 untuk nomor kembar), karena importer pembelian
 * tidak memakai `legacy_import_map`. Fixture dikarang sendiri; jalur hutang dan PO/GR diperiksa pada data gladi sungguhan (30 Oktober 2026).
 */
class ReconcilePurchasingTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

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

    protected function setUp(): void
    {
        parent::setUp();

        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        $user = User::create(['name' => 'Pemohon', 'username' => 'pemohon', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('users')->where('id', $user->id)->update(['id' => 5]);
        DB::table('suppliers')->insert(['id' => 7, 'name' => 'PT SUPPLIER', 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $unit = MaterialUnit::create(['name' => 'BOX']);
        $category = MaterialCategory::create(['name' => 'KARTON']);
        DB::table('materials')->insert(['id' => 23, 'code' => 'MTR23', 'name' => 'KARTON A', 'material_category_id' => $category->id, 'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->dir = sys_get_temp_dir().'/legacy-recon-buy-'.uniqid();
        mkdir($this->dir);

        $head = 'idrequest int, norequest varchar(20), duedate date, iduser int, idsupplier int, note varchar(50), stat varchar(20), is_deleted int, xamount decimal(15,2), taxrp decimal(15,2), creatime datetime, tax int, top int';
        $request = fn (int $id, string $number) => $this->insert('request', ['idrequest' => $id, 'norequest' => $number, 'duedate' => '2026-10-22', 'iduser' => 5, 'idsupplier' => 7, 'note' => '', 'stat' => 'Request', 'is_deleted' => 0, 'xamount' => '0', 'taxrp' => '0', 'creatime' => '2026-10-19 15:04:51', 'tax' => null, 'top' => null]);
        $line = fn (int $id, int $request, string $qty, string $price) => $this->insert('requestdetail', ['iddetail' => $id, 'idrequest' => $request, 'idrawmate' => 23, 'qty' => $qty, 'price' => $price, 'notes' => '']);

        file_put_contents($this->dir.'/SWM-belanja-1.sql',
            $this->ddl('request', $head)
            .$this->ddl('requestdetail', 'iddetail int, idrequest int, idrawmate int, qty decimal(12,2), price decimal(15,2), notes varchar(50)')
            .$this->ddl('rawmate', 'idrawmate int, nmrawmate varchar(50), unit varchar(10)')
            // nomor kembar: yang kedua harus ditemukan sebagai REQ-26/0001-2
            .$request(1, 'REQ-26/0001').$request(2, 'REQ-26/0001')
            .$line(1, 1, '10', '1000').$line(2, 2, '4', '2500'));

        file_put_contents($this->dir.'/SWM-belanjabeef-1.sql',
            $this->ddl('requestbeef', $head)
            .$this->ddl('requestbeefdetail', 'iddetail int, idrequest int, idbarang int, qty decimal(12,2), price decimal(15,2), notes varchar(50)')
            .$this->ddl('barang', 'idbarang int, nmbarang varchar(50)'));

        $this->artisan('legacy:import-requests', ['--source' => $this->dir.'/SWM-belanja-1.sql', '--fallback-user' => 'cadangan', '--apply' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    /** @test */
    public function request_documents_are_found_by_number_including_duplicate_suffixes(): void
    {
        $this->assertSame(['REQ-26/0001', 'REQ-26/0001-2'], DB::table('material_requisitions')->orderBy('id')->pluck('document_number')->all());

        $this->artisan('legacy:reconcile', ['--dir' => $this->dir, '--only' => 'request'])
            ->expectsOutputToContain('Request material: dokumen')
            ->expectsOutputToContain('HASIL: SEMUA COCOK (6 pemeriksaan).')
            ->assertSuccessful();
    }

    /** @test */
    public function a_lost_line_is_reported_as_a_difference(): void
    {
        DB::table('material_requisition_items')->orderByDesc('id')->limit(1)->delete();

        $this->artisan('legacy:reconcile', ['--dir' => $this->dir, '--only' => 'request'])
            ->expectsOutputToContain('SELISIH -1')
            ->assertFailed();
    }
}
