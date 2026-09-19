<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExpenseCategoryResource;
use App\Filament\Admin\Resources\ExpenseCategoryResource\Pages\EditExpenseCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master kecil, satu izin (`manage_expense_categories`) untuk semuanya --
 * lihat ExpenseCategoryPolicy. Aturan "masih dipakai tidak bisa dihapus"
 * sudah diuji di `ExpenseTest` (model); di sini fokus ke otorisasi Resource
 * dan pengkabelan `MasterDataDeletion::attempt()`.
 */
class ExpenseCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    private function employee(bool $withPermission): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        if ($withPermission) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => 'manage_expense_categories'],
                    ['module_name' => 'Expenses', 'description' => 'Manage expense categories'],
                )->id
            );
        }

        return $user->fresh();
    }

    /** @test */
    public function the_list_page_requires_manage_expense_categories(): void
    {
        $this->actingAs($this->employee(false))
            ->get(ExpenseCategoryResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($this->employee(true))
            ->get(ExpenseCategoryResource::getUrl('index'))
            ->assertSuccessful();
    }

    /** @test */
    public function deleting_a_category_still_in_use_shows_a_friendly_notification(): void
    {
        $category = ExpenseCategory::create(['name' => 'ONGKIR']);
        Expense::createAdvance([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $category->id,
            'recipient_name' => 'budi',
            'advance_amount' => 10000,
        ]);

        Livewire::actingAs($this->employee(true))
            ->test(EditExpenseCategory::class, ['record' => $category->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseHas('expense_categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    /** @test */
    public function deleting_an_unused_category_succeeds(): void
    {
        $category = ExpenseCategory::create(['name' => 'LAIN-LAIN']);

        Livewire::actingAs($this->employee(true))
            ->test(EditExpenseCategory::class, ['record' => $category->getRouteKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('expense_categories', ['id' => $category->id]);
    }
}
