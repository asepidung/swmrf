<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesOrderResource\Pages\CreateSalesOrder;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** SO-02: berat dan harga baris Sales Order harus angka bukan-negatif (titik pemisah ribuan boleh). */
class SalesOrderLineNumbersTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $berat, string $harga)
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $segment = CustomerSegment::firstOrCreate(['name' => 'RETAIL'], ['is_active' => true]);
        $customer = Customer::firstOrCreate(['name' => 'SO NUM CUSTOMER'], [
            'customer_segment_id' => $segment->id, 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $category = ProductCategory::firstOrCreate(['name' => 'MEAT'], ['prefix' => 'MT', 'is_active' => true]);
        $product = Product::firstOrCreate(['code' => 'MT001'], [
            'name' => 'SIRLOIN', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true,
        ]);

        return Livewire::actingAs($user)
            ->test(CreateSalesOrder::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'delivery_date' => now()->addDay()->format('Y-m-d'),
                'po_number' => 'PO-NUM',
                'items' => ['item_a' => ['product_id' => $product->id, 'weight' => $berat, 'price' => $harga, 'discount' => 0, 'note' => '']],
            ])
            ->call('create');
    }

    /** @test */
    public function a_negative_or_non_numeric_weight_or_price_is_refused(): void
    {
        $this->buat('-5', '50.000')->assertHasFormErrors();
        $this->buat('10', '-1')->assertHasFormErrors();
        $this->buat('abc', '50.000')->assertHasFormErrors();
        $this->assertSame(0, SalesOrder::count());
    }

    /** @test */
    public function a_thousand_separated_weight_and_price_are_still_accepted(): void
    {
        $this->buat('1.000', '50.000')->assertHasNoFormErrors();
        $this->assertSame(1, SalesOrder::count());
        $this->assertSame(1000, (int) SalesOrder::first()->items()->first()->weight);
    }
}
