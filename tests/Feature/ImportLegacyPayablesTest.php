<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `legacy:import-payables`: hutang riwayat dari GR hasil impor, semuanya LUNAS. Fixture dikarang sendiri.
 */
class ImportLegacyPayablesTest extends TestCase
{
    use RefreshDatabase;

    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        User::create(['name' => 'Cadangan', 'username' => 'cadangan', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        $this->supplier = DB::table('suppliers')->insertGetId(['name' => 'PT SUPPLIER', 'address' => '-', 'pic' => '-', 'top_days' => 14, 'created_at' => $now, 'updated_at' => $now]);
        $unit = DB::table('material_units')->insertGetId(['name' => 'PCS', 'created_at' => $now, 'updated_at' => $now]);
        $category = DB::table('material_categories')->insertGetId(['name' => 'KEMASAN', 'created_at' => $now, 'updated_at' => $now]);
        $material = DB::table('materials')->insertGetId(['code' => 'MTR-1', 'name' => 'KARTON', 'material_category_id' => $category, 'material_unit_id' => $unit, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $user = User::where('username', 'cadangan')->value('id');
        $requisition = DB::table('material_requisitions')->insertGetId(['document_number' => 'MR-1', 'user_id' => $user, 'supplier_id' => $this->supplier, 'due_date' => '2026-09-01', 'created_at' => $now, 'updated_at' => $now]);
        $po = DB::table('purchase_materials')->insertGetId(['po_number' => 'PO-1', 'material_requisition_id' => $requisition, 'supplier_id' => $this->supplier, 'po_date' => '2026-09-01', 'total_amount' => 0, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        $gr = DB::table('goods_receipt_materials')->insertGetId(['gr_number' => 'GRM-1', 'purchase_material_id' => $po, 'supplier_id' => $this->supplier, 'receive_date' => '2026-09-01', 'created_by' => $user, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('goods_receipt_material_items')->insert(['goods_receipt_material_id' => $gr, 'material_id' => $material, 'qty_received' => 10, 'price' => 1000, 'subtotal' => 10000, 'created_at' => $now, 'updated_at' => $now]);

        // GR sesudah go-live: tidak boleh disentuh.
        $late = DB::table('goods_receipt_materials')->insertGetId(['gr_number' => 'GRM-2', 'purchase_material_id' => $po, 'supplier_id' => $this->supplier, 'receive_date' => '2026-11-05', 'created_by' => $user, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('goods_receipt_material_items')->insert(['goods_receipt_material_id' => $late, 'material_id' => $material, 'qty_received' => 1, 'price' => 500, 'subtotal' => 500, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function run_(bool $apply = false, array $extra = ['--before' => '2026-11-01'])
    {
        return $this->artisan('legacy:import-payables', $extra + ['--fallback-user' => 'cadangan'] + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_writes_nothing(): void
    {
        $this->run_()->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('dibuat (LUNAS): 1')->assertSuccessful();

        $this->assertSame(0, DB::table('payables')->count());
    }

    /** @test */
    public function a_historical_receipt_gets_a_paid_payable_and_later_ones_are_untouched(): void
    {
        $this->run_(true)->assertSuccessful();

        $p = DB::table('payables')->where('document_number', 'GRM-1')->first();
        $this->assertSame(10000.0, (float) $p->amount);
        $this->assertSame(10000.0, (float) $p->paid_amount);
        $this->assertSame(0.0, (float) $p->balance);
        $this->assertSame('paid', $p->status);
        $this->assertSame('2026-09-15', $p->due_date, 'terima + TOP 14 hari');
        $this->assertSame('Riwayat impor legacy: dianggap lunas', $p->note);
        $this->assertSame($this->supplier, (int) $p->supplier_id);
        $this->assertNull(DB::table('payables')->where('document_number', 'GRM-2')->first());
    }

    /** @test */
    public function it_creates_no_payment_and_running_it_twice_creates_nothing_new(): void
    {
        $this->run_(true)->assertSuccessful();
        $this->run_(true)->expectsOutputToContain('dibuat (LUNAS): 0, sudah ada (dilewati): 1')->assertSuccessful();

        $this->assertSame(1, DB::table('payables')->count());
        $this->assertSame(0, DB::table('supplier_payments')->count());
    }

    /** @test */
    public function the_cutoff_date_is_required(): void
    {
        $this->run_(true, [])->assertFailed();
        $this->run_(true, ['--before' => 'besok'])->assertFailed();
        $this->assertSame(0, DB::table('payables')->count());
    }
}
