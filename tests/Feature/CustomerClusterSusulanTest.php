<?php

namespace Tests\Feature;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages\EditCustomerGroup;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource\Pages\ListCustomerGroups;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource\Pages\EditCustomerSegment;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource\Pages\ListCustomerSegments;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\PriceList;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Susulan penyisiran cluster Customers, 15 September 2026.
 *
 * Temuan 1 (izin), Temuan 9 (race condition ensureCustomerGroup) tidak
 * bertambah test khusus di sini -- izin diuji lewat guard yang sudah ada
 * (`test_every_permission_the_code_asks_for_actually_exists`), dan race
 * condition di baca-lalu-tulis tidak bisa dibuktikan bermakna lewat test
 * PHPUnit satu proses.
 */
class CustomerClusterSusulanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);
    }

    // =====================================================================
    // Temuan 2 -- Customer/CustomerGroup/CustomerSegment tidak punya jejak audit
    // =====================================================================

    /** @test */
    public function creating_a_customer_group_is_logged(): void
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);

        $this->assertTrue(
            Activity::where('subject_type', CustomerGroup::class)
                ->where('subject_id', $group->id)
                ->where('event', 'created')
                ->exists(),
        );
    }

    /** @test */
    public function creating_a_customer_segment_is_logged(): void
    {
        $segment = CustomerSegment::create(['name' => 'RETAIL']);

        $this->assertTrue(
            Activity::where('subject_type', CustomerSegment::class)
                ->where('subject_id', $segment->id)
                ->where('event', 'created')
                ->exists(),
        );
    }

    /** @test */
    public function creating_a_customer_is_logged(): void
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);
        $segment = CustomerSegment::create(['name' => 'RETAIL']);

        $customer = Customer::create([
            'name' => 'BIDADARI PUSAT',
            'customer_group_id' => $group->id,
            'customer_segment_id' => $segment->id,
            'address' => 'Bogor',
            'top' => 30,
        ]);

        $this->assertTrue(
            Activity::where('subject_type', Customer::class)
                ->where('subject_id', $customer->id)
                ->where('event', 'created')
                ->exists(),
        );
    }

    // =====================================================================
    // Temuan 3 -- CustomerGroup yang masih dipakai tidak boleh terhapus
    // =====================================================================

    /**
     * `->mountAction()`, bukan `->callAction()`.
     *
     * Grup yang masih dipakai membuat tombolnya SEMBUNYI (`->hidden()`) --
     * `callAction()` menuntut aksinya terlihat lebih dulu, jadi akan gagal
     * duluan dengan alasan yang salah. Yang mau dibuktikan di sini justru
     * penolakan di SERVER: `mountAction()` (dipakai `mountAction()` di sini)
     * tidak memeriksa `isHidden()` sama sekali -- persis seperti yang
     * ditemukan pada BankAccount (`->visible()` cuma kosmetik render tombol,
     * bukan gerbang otorisasi) -- sehingga pemeriksaan ulang di dalam
     * `->action()` itulah yang sungguh menahannya.
     */
    /** @test */
    public function deleting_a_customer_group_with_a_price_list_is_refused(): void
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);
        PriceList::create(['customer_group_id' => $group->id, 'created_by' => $this->user->id]);

        Livewire::actingAs($this->user)
            ->test(EditCustomerGroup::class, ['record' => $group->getRouteKey()])
            ->mountAction('delete')
            ->callMountedAction()
            ->assertNotified();

        $this->assertDatabaseHas('customer_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('price_lists', ['customer_group_id' => $group->id]);
    }

    /** @test */
    public function deleting_a_customer_group_with_a_customer_is_refused(): void
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);
        $segment = CustomerSegment::create(['name' => 'RETAIL']);
        Customer::create([
            'name' => 'BIDADARI PUSAT', 'customer_group_id' => $group->id,
            'customer_segment_id' => $segment->id, 'address' => 'Bogor', 'top' => 30,
        ]);

        Livewire::actingAs($this->user)
            ->test(EditCustomerGroup::class, ['record' => $group->getRouteKey()])
            ->mountAction('delete')
            ->callMountedAction()
            ->assertNotified();

        $this->assertDatabaseHas('customer_groups', ['id' => $group->id]);
    }

    /** @test */
    public function deleting_an_empty_customer_group_still_works(): void
    {
        $group = CustomerGroup::create(['name' => 'GRUP KOSONG', 'top' => 30]);

        Livewire::actingAs($this->user)
            ->test(EditCustomerGroup::class, ['record' => $group->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('customer_groups', ['id' => $group->id]);
    }

    // =====================================================================
    // Temuan 4, 6 -- ekspor CustomerGroup & CustomerSegment
    // =====================================================================

    /** @test */
    public function it_exports_customer_groups_to_excel_without_crashing(): void
    {
        CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);

        Livewire::actingAs($this->user)
            ->test(ListCustomerGroups::class)
            ->callTableAction('excel')
            ->assertHasNoTableActionErrors();
    }

    /** @test */
    public function it_exports_customer_segments_to_excel_without_crashing(): void
    {
        CustomerSegment::create(['name' => 'RETAIL']);

        Livewire::actingAs($this->user)
            ->test(ListCustomerSegments::class)
            ->callTableAction('excel')
            ->assertHasNoTableActionErrors();
    }

    // =====================================================================
    // Temuan 5 -- hapus CustomerSegment yang masih dipakai: pesan ramah
    // =====================================================================

    /** @test */
    public function deleting_a_customer_segment_still_in_use_shows_a_friendly_notification(): void
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);
        $segment = CustomerSegment::create(['name' => 'RETAIL']);
        Customer::create([
            'name' => 'BIDADARI PUSAT', 'customer_group_id' => $group->id,
            'customer_segment_id' => $segment->id, 'address' => 'Bogor', 'top' => 30,
        ]);

        Livewire::actingAs($this->user)
            ->test(EditCustomerSegment::class, ['record' => $segment->getRouteKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertDatabaseHas('customer_segments', ['id' => $segment->id]);
    }

    // =====================================================================
    // Temuan 7 -- bulk delete Customer melewati baris yang sudah bertransaksi
    // =====================================================================

    /** @test */
    public function bulk_deleting_customers_skips_rows_with_sales_orders(): void
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);
        $segment = CustomerSegment::create(['name' => 'RETAIL']);

        $bersih = Customer::create([
            'name' => 'TOKO BERSIH', 'customer_group_id' => $group->id,
            'customer_segment_id' => $segment->id, 'address' => 'Bogor', 'top' => 30,
        ]);
        $sudahTransaksi = Customer::create([
            'name' => 'TOKO SUDAH TRANSAKSI', 'customer_group_id' => $group->id,
            'customer_segment_id' => $segment->id, 'address' => 'Bogor', 'top' => 30,
        ]);

        SalesOrder::create([
            'customer_id' => $sudahTransaksi->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'completed',
        ]);

        Livewire::actingAs($this->user)
            ->test(ListCustomers::class)
            ->callTableBulkAction('delete', [$bersih->id, $sudahTransaksi->id]);

        $this->assertDatabaseMissing('customers', ['id' => $bersih->id]);
        $this->assertDatabaseHas('customers', ['id' => $sudahTransaksi->id]);
    }

    // =====================================================================
    // customer_group_id wajib -- keputusan Ayah, 15 September 2026
    // =====================================================================

    /** @test */
    public function customer_group_id_is_required_to_create_a_customer(): void
    {
        $segment = CustomerSegment::create(['name' => 'RETAIL']);

        Livewire::actingAs($this->user)
            ->test(CreateCustomer::class)
            ->fillForm([
                'name' => 'TOKO TANPA GRUP',
                'customer_segment_id' => $segment->id,
                'top' => 30,
                'invoice_exchange' => '0',
                'address' => 'Bogor',
            ])
            ->call('create')
            ->assertHasFormErrors(['customer_group_id' => 'required']);

        $this->assertDatabaseMissing('customers', ['name' => 'TOKO TANPA GRUP']);
    }

    /** @test */
    public function a_customer_can_still_be_created_with_an_existing_group(): void
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);
        $segment = CustomerSegment::create(['name' => 'RETAIL']);

        Livewire::actingAs($this->user)
            ->test(CreateCustomer::class)
            ->fillForm([
                'name' => 'BIDADARI CABANG',
                'customer_group_id' => $group->id,
                'customer_segment_id' => $segment->id,
                'top' => 30,
                'invoice_exchange' => '0',
                'address' => 'Bogor',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'BIDADARI CABANG',
            'customer_group_id' => $group->id,
        ]);
    }

    /** @test */
    public function a_customer_can_still_be_edited_and_moved_to_another_group(): void
    {
        $groupLama = CustomerGroup::create(['name' => 'GRUP LAMA', 'top' => 30]);
        $groupBaru = CustomerGroup::create(['name' => 'GRUP BARU', 'top' => 14]);
        $segment = CustomerSegment::create(['name' => 'RETAIL']);

        $customer = Customer::create([
            'name' => 'TOKO PINDAH GRUP', 'customer_group_id' => $groupLama->id,
            'customer_segment_id' => $segment->id, 'address' => 'Bogor', 'top' => 30,
            'invoice_exchange' => false,
        ]);

        Livewire::actingAs($this->user)
            ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['customer_group_id' => $groupBaru->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($groupBaru->id, $customer->fresh()->customer_group_id);
    }
}
