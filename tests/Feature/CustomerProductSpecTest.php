<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Filament\Admin\Resources\SalesOrderResource\Pages\CreateSalesOrder;
use App\Filament\Admin\Resources\SalesOrderResource\Pages\EditSalesOrder;
use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Filament\Admin\Resources\CustomerProductSpecResource;
use App\Models\Customer;
use App\Models\CustomerProductSpec;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Tally;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Spek customer per produk di Sales Order (#579, konsep disepakati Owner
 * 20 Oktober 2026).
 *
 * Yang dijaga:
 *  - spek tetap terisi otomatis ke baris SO untuk customer + produk yang sama;
 *  - "Hanya untuk SO ini" tidak menyentuh spek tetap;
 *  - baris SO menyimpan SALINAN, jadi SO lama tidak berubah saat spek tetap
 *    diubah;
 *  - spek tampil di Tally dan di cetak SO (cetak surat jalan TIDAK memuatnya, permintaan Owner 21 Oktober 2026);
 *  - tanpa izin Sales Order, spek tetap tidak bisa diubah, termasuk lewat
 *    permintaan langsung.
 */
class CustomerProductSpecTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Customer $otherCustomer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $this->customer = $this->makeCustomer('LION SUPERINDO', $segment);
        $this->otherCustomer = $this->makeCustomer('BLACK OWL', $segment);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function makeCustomer(string $name, CustomerSegment $segment): Customer
    {
        return Customer::create([
            'name' => $name, 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
    }

    private function fixedSpec(?Customer $customer = null): CustomerProductSpec
    {
        return CustomerProductSpec::create([
            'customer_id' => ($customer ?? $this->customer)->id,
            'product_id' => $this->product->id,
            'weight_per_pack' => '1 kg an',
            'cut' => 'soup 4x4 cm',
            'condition' => 'chill',
            'packaging' => 'vacuum',
        ]);
    }

    private function employee(string ...$permissions): User
    {
        $user = User::create([
            'name' => 'Staf', 'username' => 'staf_'.uniqid(),
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $permission],
                    ['module_name' => 'Test', 'description' => $permission],
                )->id
            );
        }

        return $user->fresh();
    }

    private function salesOrderWithSpec(?array $spec): SalesOrder
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => 'PO-'.uniqid(), 'status' => SalesOrder::STATUS_WAITING, 'created_by' => $this->admin->id,
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so->id, 'product_id' => $this->product->id,
            'weight' => 20, 'price' => 150000, 'spec' => $spec,
        ]);

        return $so;
    }

    // =========================================================================
    // Bentuk dan ringkasan spek
    // =========================================================================

    public function test_a_spec_is_tidied_and_summarised_in_one_line(): void
    {
        $this->assertNull(CustomerProductSpec::normalize(['cut' => '  ', 'condition' => 'bogus']));

        $this->assertSame(
            ['weight_per_pack' => '1 kg/pack', 'cut' => 'soup 4x4 cm', 'condition' => 'chill', 'packaging' => 'vacuum'],
            CustomerProductSpec::normalize([
                'weight_per_pack' => ' 1 kg/pack ', 'cut' => 'soup 4x4 cm', 'condition' => 'chill',
                'packaging' => 'vacuum', 'note' => '',
            ]),
        );

        $this->assertSame(
            '1 kg/pack · soup 4x4 cm · Chill aging · vacuum',
            CustomerProductSpec::summarize([
                'weight_per_pack' => '1 kg/pack', 'cut' => 'soup 4x4 cm',
                'condition' => 'chill_aging', 'packaging' => 'vacuum',
            ]),
        );
        $this->assertSame('', CustomerProductSpec::summarize(null));
        $this->assertSame('', CustomerProductSpec::summarize('not json'));
    }

    public function test_a_customer_has_one_spec_per_product(): void
    {
        $this->fixedSpec();

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->fixedSpec();
    }

    // =========================================================================
    // Terisi otomatis
    // =========================================================================

    public function test_the_fixed_spec_fills_in_when_customer_and_product_are_chosen(): void
    {
        $this->fixedSpec();
        $key = 'item_test';

        $page = Livewire::actingAs($this->admin)
            ->test(CreateSalesOrder::class)
            ->fillForm(['customer_id' => $this->customer->id])
            ->set("data.items.{$key}", ['product_id' => null, 'weight' => null, 'price' => '0', 'discount' => 0, 'note' => ''])
            ->set("data.items.{$key}.product_id", $this->product->id);

        $row = $page->get('data')['items'][$key];

        $this->assertSame('1 kg an', CustomerProductSpec::decode($row['spec'])['weight_per_pack']);
        $this->assertSame('soup 4x4 cm', CustomerProductSpec::decode($row['spec'])['cut']);
    }

    public function test_a_product_without_a_fixed_spec_starts_empty(): void
    {
        $this->fixedSpec($this->otherCustomer);
        $key = 'item_test';

        $page = Livewire::actingAs($this->admin)
            ->test(CreateSalesOrder::class)
            ->fillForm(['customer_id' => $this->customer->id])
            ->set("data.items.{$key}", ['product_id' => null, 'weight' => null, 'price' => '0', 'discount' => 0, 'note' => ''])
            ->set("data.items.{$key}.product_id", $this->product->id);

        $this->assertNull(CustomerProductSpec::decode($page->get('data')['items'][$key]['spec'] ?? null));
    }

    public function test_changing_the_customer_swaps_in_that_customers_spec(): void
    {
        $this->fixedSpec();
        CustomerProductSpec::create([
            'customer_id' => $this->otherCustomer->id, 'product_id' => $this->product->id, 'cut' => 'steak 2,5 cm',
        ]);
        $key = 'item_test';

        $page = Livewire::actingAs($this->admin)
            ->test(CreateSalesOrder::class)
            ->fillForm(['customer_id' => $this->customer->id])
            ->set("data.items.{$key}", ['product_id' => null, 'weight' => null, 'price' => '0', 'discount' => 0, 'note' => ''])
            ->set("data.items.{$key}.product_id", $this->product->id)
            ->set('data.customer_id', $this->otherCustomer->id);

        $this->assertSame('steak 2,5 cm', CustomerProductSpec::decode($page->get('data')['items'][$key]['spec'])['cut']);
    }

    public function test_the_row_header_shows_the_summary_and_the_spec_button(): void
    {
        app()->setLocale('en');
        $this->fixedSpec();
        $key = 'item_test';

        $page = Livewire::actingAs($this->admin)
            ->test(CreateSalesOrder::class)
            ->fillForm(['customer_id' => $this->customer->id])
            ->set("data.items.{$key}", ['product_id' => null, 'weight' => null, 'price' => '0', 'discount' => 0, 'note' => '']);

        // Belum ada spek: tombolnya mengajak mengisi.
        $page->set("data.items.{$key}.product_id", $this->otherProduct()->id)
            ->assertSee('+ Spec')
            ->assertDontSee('Spec ✓');

        // Ada spek tetap: ringkasannya tampil di bar atas, tombolnya bertanda centang.
        $page->set("data.items.{$key}.product_id", $this->product->id)
            ->assertSee('Spec: 1 kg an · soup 4x4 cm · Chill · vacuum')
            ->assertSee('Spec ✓');
    }

    private function otherProduct(): Product
    {
        return Product::create([
            'name' => 'TENDERLOIN', 'code' => 'MT002', 'category_id' => $this->product->category_id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    // =========================================================================
    // Modal: spek tetap atau hanya SO ini
    // =========================================================================

    private function pageWithOneRow(): array
    {
        $key = 'item_test';

        $page = Livewire::actingAs($this->admin)
            ->test(CreateSalesOrder::class)
            ->fillForm(['customer_id' => $this->customer->id])
            ->set("data.items.{$key}", ['product_id' => null, 'weight' => null, 'price' => '0', 'discount' => 0, 'note' => ''])
            ->set("data.items.{$key}.product_id", $this->product->id);

        return [$page, $key];
    }

    public function test_saving_as_fixed_writes_the_master_and_the_row(): void
    {
        [$page, $key] = $this->pageWithOneRow();

        $page->call('mountFormComponentAction', 'data.items', 'spec', ['item' => $key])
            ->setFormComponentActionData(['cut' => 'slice 1,5 mm', 'save_scope' => 'fixed'])
            ->call('callMountedFormComponentAction')
            ->assertHasNoFormComponentActionErrors();

        $this->assertSame(
            'slice 1,5 mm',
            CustomerProductSpec::where(['customer_id' => $this->customer->id, 'product_id' => $this->product->id])->value('cut'),
        );
        $this->assertSame('slice 1,5 mm', CustomerProductSpec::decode($page->get('data')['items'][$key]['spec'])['cut']);
    }

    public function test_only_for_this_so_leaves_the_master_alone(): void
    {
        $this->fixedSpec();
        [$page, $key] = $this->pageWithOneRow();

        $page->call('mountFormComponentAction', 'data.items', 'spec', ['item' => $key])
            ->setFormComponentActionData(['cut' => 'steak 2,5 cm', 'save_scope' => 'once'])
            ->call('callMountedFormComponentAction');

        $this->assertSame('soup 4x4 cm', CustomerProductSpec::first()->cut);
        $this->assertSame(1, CustomerProductSpec::count());
        $this->assertSame('steak 2,5 cm', CustomerProductSpec::decode($page->get('data')['items'][$key]['spec'])['cut']);
    }

    // =========================================================================
    // Salinan: SO lama tidak berubah
    // =========================================================================

    public function test_a_saved_sales_order_keeps_its_own_copy_when_the_fixed_spec_changes(): void
    {
        $fixed = $this->fixedSpec();
        $key = 'item_test';

        Livewire::actingAs($this->admin)
            ->test(CreateSalesOrder::class)
            ->fillForm(['customer_id' => $this->customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d')])
            ->set("data.items.{$key}", ['product_id' => null, 'weight' => '20', 'price' => '150.000', 'discount' => 0, 'note' => ''])
            ->set("data.items.{$key}.product_id", $this->product->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $item = SalesOrderItem::firstOrFail();
        $this->assertSame('soup 4x4 cm', $item->spec['cut']);

        $fixed->update(['cut' => 'steak 2,5 cm', 'packaging' => 'tray']);

        $this->assertSame('soup 4x4 cm', $item->fresh()->spec['cut']);
        $this->assertSame('vacuum', $item->fresh()->spec['packaging']);

        // Dibuka dan disimpan ulang pun tetap salinan lamanya.
        Livewire::actingAs($this->admin)
            ->test(EditSalesOrder::class, ['record' => $item->salesOrder->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('soup 4x4 cm', SalesOrderItem::firstOrFail()->spec['cut']);
    }

    public function test_an_old_sales_order_without_a_spec_is_not_filled_in_on_open(): void
    {
        $this->fixedSpec();
        $so = $this->salesOrderWithSpec(null);

        Livewire::actingAs($this->admin)
            ->test(EditSalesOrder::class, ['record' => $so->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(SalesOrderItem::firstOrFail()->spec);
    }

    // =========================================================================
    // Tampil di Tally dan cetakan
    // =========================================================================

    public function test_the_spec_shows_on_the_tally_scan_screen(): void
    {
        $so = $this->salesOrderWithSpec(['cut' => 'soup 4x4 cm', 'packaging' => 'vacuum']);
        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'T-'.uniqid(), 'status' => Tally::STATUS_PROCESSING, 'created_by' => $this->admin->id,
        ]);

        $page = Livewire::actingAs($this->admin)->test(ScanTally::class, ['record' => $tally]);

        $this->assertSame('soup 4x4 cm · vacuum', $page->instance()->getSummaryData()[0]['spec']);
        $page->assertSee('soup 4x4 cm · vacuum');
    }

    public function test_the_spec_prints_on_the_sales_order_but_not_on_the_delivery_order(): void
    {
        $so = $this->salesOrderWithSpec(['weight_per_pack' => '1 kg an', 'condition' => 'frozen_aging']);

        $this->actingAs($this->admin)
            ->get(route('print.salesorder', $so))
            ->assertOk()
            ->assertSee('1 kg an · Frozen aging');

        $delivery = DeliveryOrder::create([
            'sales_order_id' => $so->id, 'customer_id' => $this->customer->id,
            'delivery_order_number' => 'DO-'.uniqid(), 'delivery_date' => now()->format('Y-m-d'),
            'status' => 'draft', 'created_by' => $this->admin->id,
        ]);
        $delivery->items()->create(['product_id' => $this->product->id, 'box' => 2, 'weight' => 20]);

        $this->actingAs($this->admin)
            ->get(route('print.delivery-order', $delivery))
            ->assertOk()
            ->assertDontSee('1 kg an')
            ->assertSee('Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103', false);
    }

    // =========================================================================
    // Izin
    // =========================================================================

    public function test_a_user_without_sales_order_permission_cannot_change_the_master(): void
    {
        $outsider = $this->employee('view_customers');

        $this->actingAs($outsider);

        $this->assertFalse($outsider->can('create', CustomerProductSpec::class));
        $this->assertFalse($outsider->can('update', $this->fixedSpec()));
        $this->assertFalse($outsider->can('delete', CustomerProductSpec::first()));
        $this->assertFalse(CustomerProductSpecResource::canViewAny());
        $this->assertFalse(CustomerProductSpecResource::canCreate(), 'spek lahir dari baris SO, bukan dari menu ini');
    }

    public function test_a_direct_request_cannot_write_the_master_without_permission(): void
    {
        $outsider = $this->employee('view_customers');

        try {
            CustomerProductSpec::saveFixed($this->customer->id, $this->product->id, ['cut' => 'diam-diam'], $outsider);
            $this->fail('Spek tetap tertulis tanpa izin Sales Order.');
        } catch (AuthorizationException) {
            $this->assertSame(0, CustomerProductSpec::count());
        }
    }

    public function test_the_pages_are_closed_without_permission(): void
    {
        $this->fixedSpec();
        $outsider = $this->employee('view_customers');

        Livewire::actingAs($outsider)
            ->test(CustomerProductSpecResource\Pages\ListCustomerProductSpecs::class)
            ->assertForbidden();

        Livewire::actingAs($outsider)
            ->test(CustomerProductSpecResource\Pages\ViewCustomerSpecs::class, ['record' => $this->customer->getRouteKey()])
            ->assertForbidden();

        Livewire::actingAs($this->employee('view_customer_specs'))
            ->test(CustomerProductSpecResource\Pages\ListCustomerProductSpecs::class)
            ->assertSuccessful();
    }

    public function test_the_own_permissions_decide_who_writes_and_who_reads(): void
    {
        $this->assertTrue($this->employee('manage_customer_specs')->can('create', CustomerProductSpec::class));
        $this->assertTrue($this->employee('manage_customer_specs')->can('update', $this->fixedSpec()));
        $this->assertTrue($this->employee('view_customer_specs')->can('viewAny', CustomerProductSpec::class));

        // Hanya melihat bukan izin menulis, dan izin Sales Order/Tally tidak lagi cukup.
        $this->assertFalse($this->employee('view_customer_specs')->can('update', CustomerProductSpec::first()));
        $this->assertFalse($this->employee('view_customer_specs')->can('create', CustomerProductSpec::class));
        $this->assertFalse($this->employee('create_sales_orders', 'edit_sales_orders', 'view_sales_orders', 'view_tallies')->can('viewAny', CustomerProductSpec::class));

        $writer = $this->employee('manage_customer_specs');
        $saved = CustomerProductSpec::saveFixed($this->otherCustomer->id, $this->product->id, ['cut' => 'rib'], $writer);
        $this->assertSame('rib', $saved->cut);
    }

    public function test_the_list_has_one_row_per_customer_with_specs_and_the_detail_shows_the_products(): void
    {
        $second = Product::create(['code' => '100999', 'name' => 'RIBEYE', 'category_id' => $this->product->category_id, 'structure_type' => 'main', 'is_active' => true]);
        $this->fixedSpec();
        CustomerProductSpec::create(['customer_id' => $this->customer->id, 'product_id' => $second->id, 'cut' => 'slice 2 mm']);

        $this->assertSame('QC', CustomerProductSpecResource::getNavigationGroup());

        $viewer = $this->employee('view_customer_specs');

        Livewire::actingAs($viewer)
            ->test(CustomerProductSpecResource\Pages\ListCustomerProductSpecs::class)
            ->assertCanSeeTableRecords(collect([$this->customer]))
            ->assertCanNotSeeTableRecords(collect([$this->otherCustomer]));

        Livewire::actingAs($viewer)
            ->test(CustomerProductSpecResource\RelationManagers\SpecsRelationManager::class, [
                'ownerRecord' => $this->customer,
                'pageClass' => CustomerProductSpecResource\Pages\ViewCustomerSpecs::class,
            ])
            ->assertCanSeeTableRecords($this->customer->productSpecs)
            ->assertSee('RIBEYE')
            ->assertSee('slice 2 mm');
    }

    public function test_the_customer_page_prints_and_the_detail_list_has_every_customer_and_exports(): void
    {
        $this->fixedSpec();
        CustomerProductSpec::create(['customer_id' => $this->otherCustomer->id, 'product_id' => $this->product->id, 'cut' => 'rib 3 cm']);
        $viewer = $this->employee('view_customer_specs');

        $this->actingAs($viewer)
            ->get(route('print.customer-specs', $this->customer))
            ->assertOk()
            ->assertSee($this->customer->name)
            ->assertSee('soup 4x4 cm')
            ->assertDontSee('rib 3 cm', false);

        $this->actingAs($this->employee('view_customers'))->get(route('print.customer-specs', $this->customer))->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(CustomerProductSpecResource\Pages\CustomerSpecDetailList::class)
            ->assertCanSeeTableRecords(CustomerProductSpec::all())
            ->assertTableActionExists('excel')
            ->callTableAction('excel')
            ->assertHasNoTableActionErrors();

        Livewire::actingAs($viewer)
            ->test(CustomerProductSpecResource\RelationManagers\SpecsRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => CustomerProductSpecResource\Pages\ViewCustomerSpecs::class])
            ->assertTableActionExists('print')
            ->assertTableActionDoesNotExist('excel');
    }

    public function test_only_a_manager_can_edit_or_delete_a_spec_from_the_detail(): void
    {
        $spec = $this->fixedSpec();
        $args = ['ownerRecord' => $this->customer, 'pageClass' => CustomerProductSpecResource\Pages\ViewCustomerSpecs::class];

        Livewire::actingAs($this->employee('view_customer_specs'))
            ->test(CustomerProductSpecResource\RelationManagers\SpecsRelationManager::class, $args)
            ->assertTableActionHidden('edit', $spec)
            ->assertTableActionHidden('delete', $spec);

        Livewire::actingAs($this->employee('manage_customer_specs'))
            ->test(CustomerProductSpecResource\RelationManagers\SpecsRelationManager::class, $args)
            ->callTableAction('edit', $spec, ['cut' => 'rib 3 cm'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('rib 3 cm', $spec->fresh()->cut);
    }

    public function test_clearing_every_field_removes_the_fixed_spec(): void
    {
        $this->fixedSpec();

        CustomerProductSpec::saveFixed($this->customer->id, $this->product->id, ['cut' => ''], $this->admin);

        $this->assertSame(0, CustomerProductSpec::count());
    }
}
