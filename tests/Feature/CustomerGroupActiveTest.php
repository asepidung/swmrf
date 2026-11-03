<?php

namespace Tests\Feature;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages\EditCustomerGroup;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages\ListCustomerGroups;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\EditCustomer;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\ListCustomers;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Grup customer bisa dinonaktifkan (Owner, 3 November 2026): hilang dari pilihan di form customer dan dari daftar
 * bawaan, tanpa dihapus.
 */
class CustomerGroupActiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->admin);
    }

    public function test_a_new_group_is_active_and_can_be_switched_off_from_the_edit_page(): void
    {
        $group = CustomerGroup::create(['name' => 'REGULER', 'top' => 30]);

        $this->assertTrue($group->fresh()->is_active);

        Livewire::test(EditCustomerGroup::class, ['record' => $group->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($group->fresh()->is_active);
    }

    public function test_the_list_shows_every_group_and_the_active_filter_narrows_it(): void
    {
        $aktif = CustomerGroup::create(['name' => 'GRUP AKTIF', 'top' => 30]);
        $mati = CustomerGroup::create(['name' => 'UNGROUP', 'top' => 30, 'is_active' => false]);

        Livewire::test(ListCustomerGroups::class)
            ->assertCanSeeTableRecords([$aktif, $mati])
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$aktif])
            ->assertCanNotSeeTableRecords([$mati]);
    }

    public function test_an_inactive_group_is_not_offered_for_new_customers_but_stays_on_its_own_customers(): void
    {
        $aktif = CustomerGroup::create(['name' => 'GRUP AKTIF', 'top' => 30]);
        $mati = CustomerGroup::create(['name' => 'UNGROUP', 'top' => 30, 'is_active' => false]);

        $pilihan = Livewire::test(CreateCustomer::class)
            ->instance()->form->getComponent('data.customer_group_id')->getOptions();

        $this->assertContains('GRUP AKTIF', $pilihan);
        $this->assertNotContains('UNGROUP', $pilihan);

        $customer = Customer::create([
            'name' => 'PELANGGAN LAMA', 'address' => 'Bogor', 'top' => 30,
            'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id,
            'customer_group_id' => $mati->id,
        ]);

        $edit = Livewire::test(EditCustomer::class, ['record' => $customer->id]);

        $this->assertContains('UNGROUP', $edit->instance()->form->getComponent('data.customer_group_id')->getOptions());
        $this->assertNotNull($aktif);
    }

    public function test_the_customer_list_group_filter_only_offers_active_groups(): void
    {
        CustomerGroup::create(['name' => 'GRUP AKTIF', 'top' => 30]);
        CustomerGroup::create(['name' => 'UNGROUP', 'top' => 30, 'is_active' => false]);

        $opsi = Livewire::test(ListCustomers::class)->instance()
            ->getTableFiltersForm()->getComponent('tableFilters.customer_group_id.value')->getOptions();

        $this->assertContains('GRUP AKTIF', $opsi);
        $this->assertNotContains('UNGROUP', $opsi);
    }
}
