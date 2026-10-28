<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** `pricelist:build-from-sales`: harga terbaru per produk per grup dari riwayat SO. */
class BuildPriceListsFromSalesTest extends TestCase
{
    use RefreshDatabase;

    private CustomerSegment $segment;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->segment = CustomerSegment::create(['name' => 'SEG', 'is_active' => true]);
        $this->category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
    }

    private function customer(string $name, CustomerGroup $group): Customer
    {
        return Customer::create([
            'name' => $name, 'customer_segment_id' => $this->segment->id, 'customer_group_id' => $group->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
    }

    private function product(string $name): Product
    {
        return Product::create(['name' => $name, 'code' => 'P'.random_int(10000, 99999), 'category_id' => $this->category->id, 'structure_type' => 'main', 'is_active' => true]);
    }

    private function line(Customer $customer, Product $product, float $price, string $date, string $status = 'completed'): void
    {
        $order = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => $date, 'status' => $status, 'so_number' => 'SO-'.uniqid()]);
        SalesOrderItem::create(['sales_order_id' => $order->id, 'product_id' => $product->id, 'weight' => 10, 'price' => $price]);
    }

    private function priceOf(CustomerGroup $group, Product $product): ?int
    {
        $price = PriceList::where('customer_group_id', $group->id)->first()?->items()->where('product_id', $product->id)->value('price');

        return $price === null ? null : (int) $price;
    }

    /** @test */
    public function the_latest_priced_line_across_the_group_wins_and_zero_and_cancelled_are_skipped(): void
    {
        $group = CustomerGroup::create(['name' => 'KARYAWAN-X']);
        $a = $this->customer('A', $group);
        $b = $this->customer('B', $group);
        $fq = $this->product('FQ 97');

        $this->line($a, $fq, 90000, '2026-09-01');
        $this->line($b, $fq, 97000, '2026-10-01');   // terbaru berharga -> menang
        $this->line($a, $fq, 0, '2026-10-10');        // nol dilewati
        $this->line($a, $fq, 99999, '2026-10-20', 'cancelled'); // batal dilewati

        $this->artisan('pricelist:build-from-sales', ['--apply' => true])->assertSuccessful();

        $this->assertSame(97000, $this->priceOf($group, $fq));
        $this->assertNull(PriceList::first()->items()->first()->note, 'catatan kosong: price list dicetak untuk customer');
    }

    /** @test */
    public function dry_run_writes_nothing(): void
    {
        $group = CustomerGroup::create(['name' => 'G']);
        $this->line($this->customer('A', $group), $p = $this->product('P1'), 5000, '2026-10-01');

        $this->artisan('pricelist:build-from-sales')->assertSuccessful();

        $this->assertNull($this->priceOf($group, $p));
        $this->assertSame(0, PriceList::count());
    }

    /** @test */
    public function the_free_group_keeps_zero_prices(): void
    {
        $free = CustomerGroup::create(['name' => 'GRATISAN']);
        $p = $this->product('P1');
        $this->line($this->customer('A', $free), $p, 0, '2026-10-01');

        $this->artisan('pricelist:build-from-sales', ['--apply' => true])->assertSuccessful();

        $this->assertSame(0, $this->priceOf($free, $p));
    }

    /** @test */
    public function a_product_without_priced_history_stays_empty(): void
    {
        $group = CustomerGroup::create(['name' => 'G']);
        $p = $this->product('P1');
        $this->line($this->customer('A', $group), $p, 0, '2026-10-01');

        $this->artisan('pricelist:build-from-sales', ['--apply' => true])->assertSuccessful();

        $this->assertNull($this->priceOf($group, $p));
    }

    /** @test */
    public function existing_prices_are_kept_unless_overwrite_and_a_rerun_is_safe(): void
    {
        $group = CustomerGroup::create(['name' => 'G']);
        $p = $this->product('P1');
        $this->line($this->customer('A', $group), $p, 5000, '2026-10-01');
        PriceList::create(['customer_group_id' => $group->id])->items()->create(['product_id' => $p->id, 'price' => 4000]);

        $this->artisan('pricelist:build-from-sales', ['--apply' => true])->assertSuccessful();
        $this->assertSame(4000, $this->priceOf($group, $p));

        $this->artisan('pricelist:build-from-sales', ['--apply' => true, '--overwrite' => true])->assertSuccessful();
        $this->assertSame(5000, $this->priceOf($group, $p));

        $this->artisan('pricelist:build-from-sales', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, PriceList::first()->items()->count());
    }

    /** @test */
    public function groups_do_not_share_prices_and_empty_groups_are_untouched(): void
    {
        $g1 = CustomerGroup::create(['name' => 'G1']);
        $g2 = CustomerGroup::create(['name' => 'G2']);
        $empty = CustomerGroup::create(['name' => 'KOSONG']);
        $p = $this->product('P1');
        $this->line($this->customer('A', $g1), $p, 1000, '2026-10-01');
        $this->line($this->customer('B', $g2), $p, 2000, '2026-10-02');

        $this->artisan('pricelist:build-from-sales', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1000, $this->priceOf($g1, $p));
        $this->assertSame(2000, $this->priceOf($g2, $p));
        $this->assertNull(PriceList::where('customer_group_id', $empty->id)->first());
    }

    /** @test */
    public function the_printed_price_list_is_in_alphabetical_order(): void
    {
        $group = CustomerGroup::create(['name' => 'G']);
        $list = PriceList::create(['customer_group_id' => $group->id]);
        foreach (['TOMAHAWK', 'BONE SP', 'FQ 85 CL', 'BACKRIB'] as $name) {
            $list->items()->create(['product_id' => $this->product($name)->id, 'price' => 1000]);
        }

        $user = \App\Models\User::factory()->create();
        $user->permissions()->attach(\App\Models\Permission::firstOrCreate(['name' => 'view_price_lists'], ['module_name' => 'Fixture', 'description' => 'view_price_lists'])->id);

        $this->actingAs($user->fresh())->get(route('print.pricelist', $list))
            ->assertSeeInOrder(['BACKRIB', 'BONE SP', 'FQ 85 CL', 'TOMAHAWK']);
    }

    /** @test */
    public function the_printed_price_list_groups_by_product_category_first(): void
    {
        $group = CustomerGroup::create(['name' => 'G']);
        $list = PriceList::create(['customer_group_id' => $group->id]);
        $first = \App\Models\ProductCategory::create(['name' => 'PRIME CUT 2', 'prefix' => 91, 'is_active' => true]);
        $second = \App\Models\ProductCategory::create(['name' => 'SECONDARY', 'prefix' => 92, 'is_active' => true]);

        // Abjad saja akan memberi ALFA, BRAVO, CHARLIE, ZULU; kategori dulu: ZULU dan BRAVO (kategori 1) mendahului.
        foreach ([['CHARLIE', $second], ['ZULU', $first], ['ALFA', $second], ['BRAVO', $first]] as [$name, $category]) {
            $product = $this->product($name);
            $product->update(['category_id' => $category->id]);
            $list->items()->create(['product_id' => $product->id, 'price' => 1000]);
        }

        $user = \App\Models\User::factory()->create();
        $user->permissions()->attach(\App\Models\Permission::firstOrCreate(['name' => 'view_price_lists'], ['module_name' => 'Fixture', 'description' => 'view_price_lists'])->id);

        $this->actingAs($user->fresh())->get(route('print.pricelist', $list))
            ->assertSeeInOrder(['BRAVO', 'ZULU', 'ALFA', 'CHARLIE']);
    }

    /** @test */
    public function the_detail_exports_are_sorted_by_group_then_product(): void
    {
        $ga = CustomerGroup::create(['name' => 'ALFA']);
        $gb = CustomerGroup::create(['name' => 'BETA']);
        foreach ([[$gb, 'BLADE'], [$ga, 'TOMAHAWK'], [$ga, 'BACKRIB'], [$gb, 'ANGUS']] as [$group, $name]) {
            (PriceList::firstOrCreate(['customer_group_id' => $group->id]))->items()->create(['product_id' => $this->product($name)->id, 'price' => 1]);
        }

        $sorted = \App\Filament\Admin\Resources\PriceListResource\Pages\PriceListDetailList::sortedForExport(
            \App\Models\PriceListItem::with(['priceList.customerGroup', 'product'])->get()
        )->map(fn ($i) => $i->priceList->customerGroup->name.'/'.$i->product->name)->all();

        $this->assertSame(['ALFA/BACKRIB', 'ALFA/TOMAHAWK', 'BETA/ANGUS', 'BETA/BLADE'], $sorted);
    }
}
