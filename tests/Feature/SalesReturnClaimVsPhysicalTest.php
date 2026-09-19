<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesReturnResource\Pages\InputReturnItems;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\FinancialLoss;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesReturnPlan;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langkah 4 dari issue #451: klaim vs fisik dibandingkan per PRODUK
 * (agregat, dikonfirmasi Owner 19 September -- lihat PR #463), FinancialLoss
 * untuk selisihnya, dan aturan approve baru.
 */
class SalesReturnClaimVsPhysicalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Penagih', 'username' => 'klaim_fisik_user',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $group = CustomerGroup::create(['name' => 'BIDADARI']);
        $this->customer = Customer::create([
            'name' => 'BIDADARI PUSAT',
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
            'customer_group_id' => $group->id,
            'address' => 'Bogor', 'top' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT00100',
            'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    /**
     * Satu rangkaian SO -> Tally -> DO -> Bukti Terima -> Invoice untuk
     * SATU produk, dan mengembalikan semuanya sekaligus.
     *
     * @return array{tally: Tally, do: DeliveryOrder, invoice: Invoice}
     */
    private function kirimDanTagih(float $berat, float $harga): array
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'status' => 'ready',
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so->id, 'product_id' => $this->product->id,
            'weight' => $berat, 'price' => $harga, 'discount' => 0,
        ]);

        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'locked']);

        // Karton yang benar-benar "dikirim" -- ini yang dipakai
        // DeliveryOrder::syncItemsFromTally() (menentukan
        // deliveredWeightFor(), batas klaim plan) DAN InputReturnItems
        // saat scan (menentukan asal + invoice kartonnya).
        TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => 'SHIP-'.$so->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => $berat, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $so->id, 'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'Joko'])->id,
            'status' => 'Delivered',
        ]);

        // TallyItem yang mewakili SO ini sudah ada di sini; TallyItem lain
        // untuk skenario "produk tak diklaim" ditambahkan SESUDAH ini oleh
        // pemanggil, dan sinkronisasi kedua kali di sana yang menangkapnya.
        $do->syncItemsFromTally();

        DeliveryOrderReceipt::create([
            'delivery_order_id' => $do->id, 'sales_order_id' => $so->id, 'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(), 'receipt_number' => 'POD-'.$do->id,
            'total_box' => 0, 'total_weight' => 0, 'status' => 'Approved', 'created_by' => $this->user->id,
        ]);

        $invoice = Invoice::create([
            'delivery_order_receipt_id' => $do->receipt->id, 'customer_id' => $this->customer->id,
            'sales_order_id' => $so->id, 'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => $berat * $harga, 'charge' => 0, 'down_payment' => 0,
            'created_by' => $this->user->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => $this->product->id, 'box' => 1,
            'weight' => $berat, 'price' => $harga, 'discount_percent' => 0, 'discount_rp' => 0,
            'amount' => $berat * $harga,
        ]);
        Receivable::create(['invoice_id' => $invoice->id, 'customer_id' => $this->customer->id, 'customer_group_id' => $this->customer->customer_group_id]);

        return ['tally' => $tally, 'do' => $do, 'invoice' => $invoice->fresh()];
    }

    private function planDenganKlaim(DeliveryOrder $do, float $klaim): SalesReturnPlan
    {
        $plan = SalesReturnPlan::create([
            'plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id, 'delivery_order_id' => $do->id,
        ]);
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => $klaim]);
        $plan->submit();

        return $plan;
    }

    private function tarikPlan(SalesReturnPlan $plan): SalesReturn
    {
        return SalesReturn::create([
            'return_date' => now()->toDateString(),
            'sales_return_plan_id' => $plan->id,
            'customer_id' => $this->customer->id,
            'delivery_order_id' => $plan->delivery_order_id,
        ]);
    }

    private function fisik(SalesReturn $retur, string $barcode, float $berat): SalesReturnItem
    {
        return SalesReturnItem::create([
            'sales_return_id' => $retur->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'barcode' => $barcode, 'weight' => $berat, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);
    }

    // =========================================================================
    // Kredit ikut klaim, selisih jadi FinancialLoss
    // =========================================================================

    /** @test */
    public function claim_exceeding_physical_is_still_credited_in_full_and_the_gap_becomes_a_loss(): void
    {
        ['do' => $do, 'invoice' => $invoice] = $this->kirimDanTagih(100, 100000);
        $plan = $this->planDenganKlaim($do, 100);
        $retur = $this->tarikPlan($plan);
        $this->fisik($retur, 'BC-001', 98);

        $retur->refresh()->approve();

        $item = $retur->fresh()->items()->first();
        $this->assertSame(100.0, (float) $item->credited_weight);
        $this->assertSame(10000000.0, (float) $retur->fresh()->credit_amount);

        $loss = FinancialLoss::where('transaction_type', FinancialLoss::SUMBER_RETUR)
            ->where('reference_number', $retur->return_number)
            ->first();
        $this->assertNotNull($loss, 'FinancialLoss selisih klaim-fisik tidak dibuat.');
        $this->assertSame(2.0, (float) $loss->quantity);
        $this->assertSame(200000.0, (float) $loss->amount);

        // Unlock membalik kerugian selisihnya juga, bukan cuma kreditnya.
        $retur->fresh()->unlock();
        $this->assertNull(FinancialLoss::where('transaction_type', FinancialLoss::SUMBER_RETUR)
            ->where('reference_number', $retur->return_number)->first());
        $this->assertSame(0.0, (float) $retur->fresh()->credit_amount);

        // Plan TETAP Received sesudah unlock -- retur masih ada.
        $this->assertSame(SalesReturnPlan::STATUS_RECEIVED, $plan->fresh()->status);
    }

    /** @test */
    public function a_claimed_product_that_never_physically_arrived_blocks_approval(): void
    {
        // Plan TANPA surat jalan (unidentified) -- tes ini soal fisik nol,
        // bukan batas klaim-vs-terkirim, jadi tidak perlu DO sama sekali.
        $produkLain = Product::create([
            'name' => 'RIBEYE', 'code' => 'MT00200',
            'category_id' => $this->product->category_id, 'structure_type' => 'main', 'is_active' => true,
        ]);

        $plan = SalesReturnPlan::create([
            'plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id,
        ]);
        // Produk KEDUA supaya returnya tidak kosong -- diklaim DAN difisik,
        // supaya bukan alasan "This return has no item yet" yang menahan.
        // Keduanya ditambahkan SEBELUM submit() -- item baru tidak boleh
        // ditambahkan lagi begitu plan sudah Submitted.
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 50]);
        $plan->items()->create(['product_id' => $produkLain->id, 'claimed_weight' => 10]);
        $plan->submit();

        $retur = $this->tarikPlan($plan);
        SalesReturnItem::create([
            'sales_return_id' => $retur->id, 'product_id' => $produkLain->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'barcode' => 'BC-002', 'weight' => 10, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);

        $this->expectException(\RuntimeException::class);

        $retur->refresh()->approve();
    }

    /** @test */
    public function physical_exceeding_claim_is_credited_only_up_to_the_claim_with_no_loss(): void
    {
        ['do' => $do] = $this->kirimDanTagih(100, 100000);
        $plan = $this->planDenganKlaim($do, 100);
        $retur = $this->tarikPlan($plan);
        $this->fisik($retur, 'BC-003', 102);

        $retur->refresh()->approve();

        $item = $retur->fresh()->items()->first();
        $this->assertSame(100.0, (float) $item->credited_weight);

        $this->assertNull(FinancialLoss::where('transaction_type', FinancialLoss::SUMBER_RETUR)
            ->where('reference_number', $retur->return_number)->first());
    }

    // =========================================================================
    // Susulan issue #476: fisik ikut yang datang, uang ikut klaim
    // =========================================================================

    /**
     * Sebelum issue #476 scan produk di luar plan DITOLAK sama sekali.
     * Owner membalik keputusan itu 20 September: kondisi lapangan (retur
     * campur, kadang ada produk yang lupa diklaim) membuat penolakan itu
     * menghalangi barang fisik masuk stok. Sekarang produk begini
     * DITERIMA -- masuk stok seperti biasa, tapi kreditnya 0 dan TANPA
     * FinancialLoss (bukan loss, bukan kredit -- murni stok bertambah
     * tanpa efek uang).
     *
     * @test
     */
    public function scanning_a_product_absent_from_the_plan_is_received_with_zero_credit_and_no_loss(): void
    {
        ['tally' => $tally, 'do' => $do] = $this->kirimDanTagih(50, 100000);

        $produkTakDiklaim = Product::create([
            'name' => 'RIBEYE', 'code' => 'MT00300',
            'category_id' => $this->product->category_id, 'structure_type' => 'main', 'is_active' => true,
        ]);
        TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => 'BC-UNCLAIMED', 'product_id' => $produkTakDiklaim->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);
        $do->syncItemsFromTally();

        // Plan cuma mengklaim $this->product, bukan $produkTakDiklaim.
        $plan = $this->planDenganKlaim($do, 50);
        $retur = $this->tarikPlan($plan);
        $this->fisik($retur, 'BC-004', 50);

        $this->user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_sales_returns'], ['module_name' => 'x', 'description' => 'x'])->id
        );
        $this->user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'edit_sales_returns'], ['module_name' => 'x', 'description' => 'x'])->id
        );
        $this->actingAs($this->user->fresh());

        Livewire::test(InputReturnItems::class, ['record' => $retur])
            ->set('dataScan.barcode', 'BC-UNCLAIMED')
            ->call('processScan');

        // Diterima -- bukan ditolak.
        $this->assertSame(2, $retur->fresh()->items()->count());
        $this->assertDatabaseHas('sales_return_items', [
            'sales_return_id' => $retur->id, 'product_id' => $produkTakDiklaim->id, 'barcode' => 'BC-UNCLAIMED',
        ]);

        $retur->refresh()->approve();

        $itemTakDiklaim = $retur->fresh()->items()->where('product_id', $produkTakDiklaim->id)->first();
        $this->assertSame(0.0, (float) $itemTakDiklaim->credited_weight);
        $this->assertSame(0.0, (float) $itemTakDiklaim->line_amount);

        // Produk yang diklaim tetap dikredit penuh seperti biasa.
        $itemDiklaim = $retur->fresh()->items()->where('product_id', $this->product->id)->first();
        $this->assertSame(50.0, (float) $itemDiklaim->credited_weight);

        // Tidak ada FinancialLoss sama sekali -- klaim=fisik untuk produk
        // yang diklaim (50=50), dan produk tanpa klaim tidak ikut hitungan.
        $this->assertNull(FinancialLoss::where('transaction_type', FinancialLoss::SUMBER_RETUR)
            ->where('reference_number', $retur->return_number)->first());

        $ringkasan = $retur->fresh()->claimVsPhysicalSummary();
        $barisTakDiklaim = $ringkasan->firstWhere('product_id', $produkTakDiklaim->id);
        $this->assertNotNull($barisTakDiklaim);
        $this->assertTrue($barisTakDiklaim['received_without_claim']);
        $this->assertSame(0.0, $barisTakDiklaim['claimed']);
        $this->assertSame(10.0, $barisTakDiklaim['physical']);

        $barisDiklaim = $ringkasan->firstWhere('product_id', $this->product->id);
        $this->assertFalse($barisDiklaim['received_without_claim']);
    }

    /**
     * Retur lama TANPA plan sama sekali (dari sebelum issue #451) tidak
     * punya konsep klaim -- ringkasannya harus kosong, bukan menandai
     * SEMUA produknya "DITERIMA TANPA KLAIM" (itu akan membingungkan,
     * bukan menerangkan apa pun).
     *
     * @test
     */
    public function a_return_with_no_plan_at_all_has_an_empty_claim_summary(): void
    {
        // `withoutEvents()` melewati guard `creating()` yang sekarang
        // mewajibkan plan untuk retur BARU -- di sini sengaja meniru
        // baris LAMA dari sebelum guard itu ada.
        $retur = \App\Models\SalesReturn::withoutEvents(fn () => SalesReturn::create([
            'return_number' => 'SR-LEGACY-001',
            'return_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
        ]));
        $this->fisik($retur, 'BC-LEGACY', 20);

        $this->assertTrue($retur->fresh()->claimVsPhysicalSummary()->isEmpty());
    }

    /**
     * Campuran realistis: produk A dan B diklaim (dan datang penuh),
     * produk D datang tapi tidak pernah disebut plan-nya sama sekali.
     *
     * @test
     */
    public function a_mixed_return_credits_only_the_claimed_products_and_flags_the_rest(): void
    {
        $produkB = Product::create([
            'name' => 'CHUCK', 'code' => 'MT00400',
            'category_id' => $this->product->category_id, 'structure_type' => 'main', 'is_active' => true,
        ]);
        $produkD = Product::create([
            'name' => 'BRISKET', 'code' => 'MT00500',
            'category_id' => $this->product->category_id, 'structure_type' => 'main', 'is_active' => true,
        ]);

        $plan = SalesReturnPlan::create([
            'plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id,
        ]);
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 30]);
        $plan->items()->create(['product_id' => $produkB->id, 'claimed_weight' => 20]);
        $plan->submit();

        $retur = $this->tarikPlan($plan);
        $this->fisik($retur, 'BC-A', 30);
        SalesReturnItem::create([
            'sales_return_id' => $retur->id, 'product_id' => $produkB->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'barcode' => 'BC-B', 'weight' => 20, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);
        SalesReturnItem::create([
            'sales_return_id' => $retur->id, 'product_id' => $produkD->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'barcode' => 'BC-D', 'weight' => 15, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);

        $retur->refresh()->approve();

        $itemA = $retur->fresh()->items()->where('product_id', $this->product->id)->first();
        $itemB = $retur->fresh()->items()->where('product_id', $produkB->id)->first();
        $itemD = $retur->fresh()->items()->where('product_id', $produkD->id)->first();

        $this->assertSame(30.0, (float) $itemA->credited_weight);
        $this->assertSame(20.0, (float) $itemB->credited_weight);
        $this->assertSame(0.0, (float) $itemD->credited_weight);
        $this->assertSame(0.0, (float) $itemD->line_amount);

        $this->assertNull(FinancialLoss::where('transaction_type', FinancialLoss::SUMBER_RETUR)
            ->where('reference_number', $retur->return_number)->first());

        $ringkasan = $retur->fresh()->claimVsPhysicalSummary();
        $this->assertFalse($ringkasan->firstWhere('product_id', $this->product->id)['received_without_claim']);
        $this->assertFalse($ringkasan->firstWhere('product_id', $produkB->id)['received_without_claim']);
        $this->assertTrue($ringkasan->firstWhere('product_id', $produkD->id)['received_without_claim']);
    }
}
