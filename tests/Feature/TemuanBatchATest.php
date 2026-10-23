<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource;
use App\Filament\Admin\Resources\MaterialUsageResource;
use App\Filament\Admin\Resources\RepackResource\Pages\CreateRepack;
use App\Filament\Admin\Resources\RepackResource\Pages\ListRepacks;
use App\Filament\Admin\Resources\SalesReturnResource;
use App\Filament\Admin\Resources\TallyResource;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Temuan uji coba Ayah, 23 Oktober 2026 (kelompok A): urutan menu Gudang, label invoice, Create Repack menunggu batas susut QC.
 */
class TemuanBatchATest extends TestCase
{
    use RefreshDatabase;

    private function user(string ...$permissions): User
    {
        $user = User::create(['name' => 'Staf', 'username' => 'staf_'.uniqid(), 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        foreach ($permissions as $name) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['module_name' => 'Test', 'description' => $name])->id);
        }

        return $user;
    }

    public function test_tally_is_the_first_item_of_the_warehouse_menu(): void
    {
        $sorts = [
            'tally' => TallyResource::getNavigationSort(),
            'retur' => SalesReturnResource::getNavigationSort(),
            'penggunaan' => MaterialUsageResource::getNavigationSort(),
        ];

        $this->assertSame(1, $sorts['tally']);
        $this->assertLessThan($sorts['retur'], $sorts['tally']);
        $this->assertLessThan($sorts['penggunaan'], $sorts['retur']);
    }

    public function test_the_invoice_amount_column_says_receivable_not_payable(): void
    {
        $this->assertSame('Total Receivable', trans('Total Receivable', [], 'en'));
        $this->assertSame('Total Piutang', trans('Total Receivable', [], 'id'));

        $source = file_get_contents(app_path('Filament/Admin/Resources/InvoiceResource.php'));
        $column = substr($source, strpos($source, "TextColumn::make('balance')"), 400);
        $this->assertStringContainsString("__('Total Receivable')", $column);
        $this->assertStringNotContainsString("__('Total Amount')", $column);
    }

    public function test_create_repack_is_disabled_until_qc_sets_the_shrinkage_limit(): void
    {
        $user = $this->user('view_repacks', 'create_repacks');

        Livewire::actingAs($user)->test(ListRepacks::class)->assertActionDisabled('create');

        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, '5');

        Livewire::actingAs($user)->test(ListRepacks::class)->assertActionEnabled('create');
    }

    public function test_the_create_page_refuses_a_direct_visit_while_the_limit_is_unset(): void
    {
        $user = $this->user('view_repacks', 'create_repacks');

        Livewire::actingAs($user)->test(CreateRepack::class)->assertRedirect();

        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, '5');

        Livewire::actingAs($user)->test(CreateRepack::class)->assertSuccessful()->assertNoRedirect();
    }
}
