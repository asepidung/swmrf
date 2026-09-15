<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReceivableResource\Pages\ListReceivables;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Receivable, 15 September 2026 -- Temuan 3.
 *
 * Modul kembarannya (Payable, hutang supplier) sudah punya tombol Excel dan
 * PDF sejak 31 Agustus; Receivable (piutang customer) tidak punya sama
 * sekali, padahal project.md mewajibkan keduanya di setiap halaman Index.
 */
class ReceivableExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_ekspor_piutang', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_receivables'],
                ['module_name' => 'Receivables', 'description' => 'view_receivables'],
            )->id
        );

        $this->actingAs($user->fresh());

        $group = CustomerGroup::create(['name' => 'BIDADARI']);

        $customer = Customer::create([
            'name' => 'BIDADARI PUSAT', 'address' => 'Bogor', 'top' => 30,
            'customer_group_id' => $group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->toDateString(),
            'created_by' => $user->id, 'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id, 'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => 1000000,
            'charge' => 0, 'down_payment' => 0, 'created_by' => $user->id,
        ]);

        Receivable::create([
            'invoice_id' => $invoice->id, 'customer_id' => $customer->id,
            'customer_group_id' => $group->id,
        ]);
    }

    /** @test */
    public function it_exports_the_receivable_listing_to_excel_without_crashing(): void
    {
        Livewire::test(ListReceivables::class)
            ->callTableAction('excel')
            ->assertHasNoTableActionErrors();
    }

    /** @test */
    public function it_exports_the_receivable_listing_to_pdf_without_crashing(): void
    {
        Livewire::test(ListReceivables::class)
            ->callTableAction('pdf')
            ->assertHasNoTableActionErrors();
    }
}
