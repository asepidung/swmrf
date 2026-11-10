<?php

namespace Tests\Feature;

use App\Models\BarcodeCost;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\FinancialLoss;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryShrinkValuator;
use App\Support\BarcodeCosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Susut kirim dinilai dengan harga pokok karton yang dikirim (Owner, 10 November 2026), tidak ditebak bila ada yang belum bernilai. */
class DeliveryShrinkValuationTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryOrder $deliveryOrder;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create(['name' => 'Admin', 'username' => 'admin_susut', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        $this->actingAs($user);

        $customer = Customer::create([
            'name' => 'PELANGGAN', 'address' => 'Bogor', 'top' => 30,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
            'customer_group_id' => CustomerGroup::create(['name' => 'GRUP'])->id,
        ]);
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->produk = Product::create(['name' => 'STRIPLOIN', 'code' => 'MT00100', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $warehouse = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $so = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => now()->toDateString(), 'created_by' => $user->id, 'status' => 'ready']);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'locked']);

        foreach ([['K1', 10.0], ['K2', 10.0]] as [$barcode, $kg]) {
            TallyItem::create([
                'tally_id' => $tally->id, 'barcode' => $barcode, 'product_id' => $this->produk->id, 'warehouse_id' => $warehouse->id,
                'grade_id' => $grade->id, 'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1',
            ]);
        }

        $this->deliveryOrder = DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $so->id, 'customer_id' => $customer->id, 'delivery_date' => now()->toDateString(),
            'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'JOKO'])->id, 'status' => 'Approved',
        ]);

        $this->deliveryOrder->syncItemsFromTally();

        // Dikirim 20 kg, diterima 18,5 kg: susut 1,5 kg.
        $receipt = DeliveryOrderReceipt::create([
            'delivery_order_id' => $this->deliveryOrder->id, 'sales_order_id' => $so->id, 'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(), 'receipt_number' => 'REC-1', 'total_box' => 2, 'total_weight' => 18.5,
            'status' => 'Approved', 'created_by' => $user->id,
        ]);
        $receipt->items()->create(['product_id' => $this->produk->id, 'box' => 2, 'weight' => 18.5]);

        $this->deliveryOrder->financialLoss()->create([
            'transaction_type' => FinancialLoss::SUMBER_SURAT_JALAN, 'reference_number' => 'DO-1', 'date' => now()->toDateString(),
            'amount' => 0, 'quantity' => 1.5, 'unit' => 'Kg', 'note' => 'susut',
        ]);
    }

    private function nilai(string $barcode, float $perKg): void
    {
        BarcodeCosts::set($barcode, $perKg, BarcodeCost::SOURCE_OPENING, null, null, $this->produk->id);
    }

    public function test_the_shortfall_is_shipped_minus_received_per_product(): void
    {
        $this->assertSame([$this->produk->id => 1.5], DeliveryShrinkValuator::shortfalls($this->deliveryOrder));
    }

    public function test_it_is_valued_at_the_weighted_average_cost_of_the_shipped_cartons(): void
    {
        $this->nilai('K1', 100000);
        $this->nilai('K2', 120000);

        // rata-rata 110.000/kg x 1,5 kg
        $this->assertSame(165000.0, DeliveryShrinkValuator::amountFor($this->deliveryOrder));
        $this->assertTrue(DeliveryShrinkValuator::revalue($this->deliveryOrder));
        $this->assertSame(165000.0, (float) $this->deliveryOrder->financialLoss()->first()->amount);
    }

    public function test_it_stays_unvalued_when_any_carton_has_no_cost(): void
    {
        $this->nilai('K1', 100000);

        $this->assertNull(DeliveryShrinkValuator::amountFor($this->deliveryOrder));
        $this->assertFalse(DeliveryShrinkValuator::revalue($this->deliveryOrder));
        $this->assertTrue($this->deliveryOrder->financialLoss()->first()->isNotPricedYet());
    }

    public function test_pending_losses_are_filled_when_the_cost_arrives_later(): void
    {
        $this->assertSame(0, DeliveryShrinkValuator::revalueAllPending());

        $this->nilai('K1', 100000);
        $this->nilai('K2', 100000);

        $this->assertSame(1, DeliveryShrinkValuator::revalueAllPending());
        $this->assertSame(150000.0, (float) $this->deliveryOrder->financialLoss()->first()->amount);
    }

    public function test_the_command_only_reports_without_apply(): void
    {
        $this->nilai('K1', 100000);
        $this->nilai('K2', 100000);

        $this->artisan('costing:nilai-susut-kirim')->assertSuccessful();
        $this->assertSame(0.0, (float) $this->deliveryOrder->financialLoss()->first()->amount);

        $this->artisan('costing:nilai-susut-kirim', ['--apply' => true])->assertSuccessful();
        $this->assertSame(150000.0, (float) $this->deliveryOrder->financialLoss()->first()->amount);
    }
}
