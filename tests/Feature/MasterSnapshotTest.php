<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Support\MasterSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `master:export` + `master:import-snapshot`: salinan master data coba. -> VPS dengan id yang sama.
 */
class MasterSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function seedMaster(): void
    {
        $segment = CustomerSegment::create(['name' => 'HOREKA']);
        $group = CustomerGroup::create(['name' => 'ABUBA']);
        Customer::create([
            'name' => 'ABUBA - CIPETE', 'customer_group_id' => $group->id, 'customer_segment_id' => $segment->id, 'address' => 'JAKARTA',
            'top' => 14, 'default_discount' => 0, 'invoice_exchange' => false, 'is_active' => true,
            'required_documents' => ['Invoice', 'NKV'],
        ]);

        $category = ProductCategory::create(['name' => 'PRIME CUT', 'prefix' => 1]);
        $parent = Product::create(['code' => '100100', 'name' => 'TENDERLOIN', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $child = Product::create(['code' => '100101', 'name' => 'TENDERLOIN BUTT', 'category_id' => $category->id, 'structure_type' => 'sub', 'parent_id' => $parent->id, 'is_active' => true]);

        $unit = MaterialUnit::create(['name' => 'BOX']);
        $mcat = MaterialCategory::create(['name' => 'KARTON']);
        $material = Material::create(['name' => 'KARTON TOP', 'material_category_id' => $mcat->id, 'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => true]);
        ProductMaterial::create(['product_id' => $child->id, 'material_id' => $material->id, 'quantity' => 1, 'basis' => 'box']);
    }

    /** Mengosongkan tabel master (urutan terbalik) seolah basis data tujuan masih kosong. */
    private function emptyMaster(): void
    {
        foreach (array_reverse(MasterSnapshot::TABLES) as $table) {
            DB::table($table)->delete();
        }
    }

    /** @test */
    public function the_snapshot_copies_master_data_with_the_same_ids_and_links(): void
    {
        $this->seedMaster();
        $snapshot = MasterSnapshot::export();
        $this->emptyMaster();

        $result = MasterSnapshot::import($snapshot, apply: true);

        $this->assertFalse($result['refused']);
        $this->assertSame(1, Customer::count());
        $this->assertSame(['Invoice', 'NKV'], Customer::first()->required_documents);
        $this->assertSame(Product::where('code', '100100')->value('id'), Product::where('code', '100101')->value('parent_id'));
        $this->assertSame(1, ProductMaterial::count());
        $this->assertSame($snapshot['tables']['products'][0]['id'], Product::orderBy('id')->value('id'));
    }

    /** @test */
    public function a_dry_run_writes_nothing(): void
    {
        $this->seedMaster();
        $snapshot = MasterSnapshot::export();
        $this->emptyMaster();

        $result = MasterSnapshot::import($snapshot, apply: false);

        $this->assertFalse($result['refused']);
        $this->assertSame(2, $result['report']['products']['created']);
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Customer::count());
    }

    /** @test */
    public function running_it_twice_changes_nothing_the_second_time(): void
    {
        $this->seedMaster();
        $snapshot = MasterSnapshot::export();

        $result = MasterSnapshot::import($snapshot, apply: true);

        $this->assertFalse($result['refused']);
        $this->assertSame(0, $result['report']['customers']['created']);
        $this->assertSame(1, $result['report']['customers']['same']);
    }

    /** @test */
    public function a_row_that_differs_in_the_target_is_refused_and_nothing_is_written(): void
    {
        $this->seedMaster();
        $snapshot = MasterSnapshot::export();
        $this->emptyMaster();
        $this->seedMaster();
        Customer::query()->update(['address' => 'ALAMAT LAIN']);
        DB::table('suppliers')->insert(['name' => 'BARU', 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => now(), 'updated_at' => now()]);

        // Tambah satu baris baru ke snapshot supaya terlihat tidak ikut tertulis.
        $snapshot['tables']['drivers'][] = ['id' => 77, 'name' => 'SUPIR BARU', 'is_active' => 1, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()];
        $snapshot['meta']['checksum'] = hash('sha256', json_encode($snapshot['tables']));

        $result = MasterSnapshot::import($snapshot, apply: true);

        $this->assertTrue($result['refused']);
        $this->assertCount(1, $result['report']['customers']['refused']);
        $this->assertSame('ALAMAT LAIN', Customer::first()->address, 'Tidak ditimpa.');
        $this->assertNull(DB::table('drivers')->where('id', 77)->first(), 'Penolakan membatalkan seluruh penulisan.');
    }

    /** @test */
    public function a_tampered_snapshot_is_refused(): void
    {
        $this->seedMaster();
        $snapshot = MasterSnapshot::export();
        $snapshot['tables']['customers'][0]['name'] = 'DIUBAH';

        $result = MasterSnapshot::import($snapshot, apply: true);

        $this->assertTrue($result['refused']);
        $this->assertStringContainsString('Checksum', $result['report']['snapshot']['refused'][0]);
    }

    /** @test */
    public function the_commands_round_trip_through_a_file(): void
    {
        $this->seedMaster();
        $path = tempnam(sys_get_temp_dir(), 'snap-').'.json';

        $this->artisan('master:export', ['--path' => $path])->assertSuccessful();
        $this->emptyMaster();
        $this->artisan('master:import-snapshot', ['file' => $path])->expectsOutputToContain('DRY-RUN')->assertSuccessful();
        $this->assertSame(0, Customer::count());
        $this->artisan('master:import-snapshot', ['file' => $path, '--apply' => true])->expectsOutputToContain('DITERAPKAN')->assertSuccessful();
        $this->assertSame(1, Customer::count());

        unlink($path);
    }
}
