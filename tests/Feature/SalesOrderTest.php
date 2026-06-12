<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'programmer',
            'is_active' => true
        ]);

        $segment = CustomerSegment::create([
            'name' => 'RETAIL',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'BLACK OWL',
            'customer_segment_id' => $segment->id,
            'address' => 'Ruko PIK',
            'pic' => 'John Doe',
            'phone' => '0812345678',
            'top' => 30,
        ]);

        $category = ProductCategory::create([
            'name' => 'MEAT',
            'prefix' => 'MT',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'TENDERLOIN BEEF',
            'code' => 'MT00100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_creates_a_sales_order_and_generates_so_number()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
        ]);

        $yearStr = date('y');
        $expectedSoNumber = 'SO#' . $yearStr . '0001';
        $this->assertEquals($expectedSoNumber, $so->so_number);
    }

    /** @test */
    public function it_renders_sales_order_print_view_with_toggle_controls()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'product_id' => $this->product->id,
            'weight' => 15.5,
            'price' => 150000,
            'discount' => 5,
            'note' => 'Extra clean cut',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('print.salesorder', $so));

        $response->assertStatus(200);
        $response->assertSee('PT. SANTI WIJAYA MEAT');
        $response->assertSee('SALES ORDER');
        $response->assertSee('BLACK OWL');
        $response->assertSee('PO12345');
        $response->assertSee('TENDERLOIN BEEF');
        $response->assertSee('15,50'); // Weight formatting
        $response->assertSee('Rp 150.000'); // Price formatting
        $response->assertSee('5 %'); // Discount
        
        // Check presence of printing control buttons
        $response->assertSee('Hide Price');
        $response->assertSee('Close Tab');
        $response->assertSee('Print Document');
        $response->assertSee('function togglePrice()');
    }
}
