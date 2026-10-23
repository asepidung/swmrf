<?php

namespace Tests\Feature;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\EditCustomer;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LooseName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Peringatan nama "mirip" di formulir master data (keputusan Ayah, 19 Oktober 2026): spasi, tanda baca, dan
 * huruf besar/kecil diabaikan. Hanya peringatan; nama yang persis sama tetap ditolak aturan unik.
 */
class SimilarNameWarningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_loose_key_ignores_case_spaces_and_punctuation(): void
    {
        $this->assertSame('CVX', LooseName::key('CV. X'));
        $this->assertSame('CVX', LooseName::key('cv.x'));
        $this->assertSame('ABUBACIPETE', LooseName::key('ABUBA - CIPETE'));
        $this->assertSame('ABUBACIPETE', LooseName::key('ABUBA -CIPETE'));
        $this->assertSame('', LooseName::key('  - . '));
        $this->assertNotSame(LooseName::key('FQ 85 CL'), LooseName::key('FQ 85 CL CUT'), 'Nama berbeda tidak dianggap mirip.');
    }

    /** @test */
    public function similar_names_are_found_but_an_identical_name_and_the_record_itself_are_not(): void
    {
        $segment = CustomerSegment::create(['name' => 'UMUM']);
        Supplier::create(['name' => 'CV. SAMUDERA KARUNIA RIZKY', 'address' => 'X', 'pic' => '-', 'phone' => '-', 'top_days' => 0]);
        $self = Supplier::create(['name' => 'PT LAIN', 'address' => 'X', 'pic' => '-', 'phone' => '-', 'top_days' => 0]);

        $this->assertSame(['CV. SAMUDERA KARUNIA RIZKY'], LooseName::similarTo(Supplier::class, 'CV.SAMUDERA KARUNIA RIZKY'));
        $this->assertSame(['CV. SAMUDERA KARUNIA RIZKY'], LooseName::similarTo(Supplier::class, 'cv samudera karunia rizky'));
        $this->assertSame([], LooseName::similarTo(Supplier::class, 'CV. SAMUDERA KARUNIA RIZKY'), 'Yang persis sama ditangani aturan unik, bukan peringatan.');
        $this->assertSame([], LooseName::similarTo(Supplier::class, 'PT. LAIN', $self->id), 'Rekaman sendiri diabaikan saat mengedit.');
        $this->assertSame(['PT LAIN'], LooseName::similarTo(Supplier::class, 'PT. LAIN'));
        $this->assertSame([], LooseName::similarTo(Supplier::class, ''));
        $this->assertNotNull($segment);
    }

    /** @test */
    public function the_customer_form_warns_about_a_similar_name_and_stays_quiet_otherwise(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $group = CustomerGroup::create(['name' => 'ABUBA']);
        $segment = CustomerSegment::create(['name' => 'UMUM']);
        Customer::create(['name' => 'ABUBA - CIPETE', 'customer_group_id' => $group->id, 'customer_segment_id' => $segment->id, 'address' => '-', 'top' => 14]);

        $page = Livewire::actingAs($user)->test(CreateCustomer::class);

        $page->fillForm(['name' => 'ABUBA -CIPETE'])
            ->assertSee('Similar to an existing record')
            ->assertSee('ABUBA - CIPETE');

        $page->fillForm(['name' => 'ABUBA - TEBET'])
            ->assertDontSee('Similar to an existing record');
    }

    /** @test */
    public function editing_a_record_does_not_warn_about_itself(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $group = CustomerGroup::create(['name' => 'ABUBA']);
        $segment = CustomerSegment::create(['name' => 'UMUM']);
        $customer = Customer::create(['name' => 'ABUBA - CIPETE', 'customer_group_id' => $group->id, 'customer_segment_id' => $segment->id, 'address' => '-', 'top' => 14]);

        Livewire::actingAs($user)
            ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertDontSee('Similar to an existing record');
    }

    /** @test */
    public function every_master_data_name_field_carries_the_warning(): void
    {
        $perluDiperiksa = [
            'Admin/Resources/CattleClassResource.php', 'Admin/Resources/MaterialCategoryResource.php',
            'Admin/Resources/MaterialResource.php', 'Admin/Resources/MaterialUnitResource.php',
            'Admin/Resources/SupplierResource.php', 'Admin/Resources/GradeResource.php',
            'Admin/Resources/WarehouseResource.php', 'Admin/Resources/ExpenseCategoryResource.php',
            'Clusters/CustomersCluster/Resources/CustomerGroupResource.php',
            'Clusters/CustomersCluster/Resources/CustomerResource.php',
            'Clusters/CustomersCluster/Resources/CustomerSegmentResource.php',
            'Clusters/ProductsCluster/Resources/ProductCategoryResource.php',
            'Clusters/ProductsCluster/Resources/ProductResource.php',
        ];

        $tanpa = [];
        foreach ($perluDiperiksa as $file) {
            if (! str_contains(file_get_contents(app_path("Filament/{$file}")), 'warnIfSimilar(')) {
                $tanpa[] = $file;
            }
        }

        $this->assertSame([], $tanpa, 'Kolom nama master data tanpa peringatan nama mirip.');
        $this->assertGreaterThanOrEqual(2, substr_count(file_get_contents(app_path('Filament/Support/QuickCreate.php')), 'warnIfSimilar('), 'Formulir tambah cepat juga.');
        $this->assertTrue(class_exists(Material::class));
    }
}
