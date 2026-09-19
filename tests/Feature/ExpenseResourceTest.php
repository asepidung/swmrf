<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Filament\Admin\Resources\ExpenseResource\Pages\CreateExpense;
use App\Filament\Admin\Resources\ExpenseResource\Pages\EditExpense;
use App\Filament\Admin\Resources\ExpenseResource\Pages\ListExpenses;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langkah 2 dari issue #453: Resource + aksi Settle/Batalkan + kunci.
 * Aturan bisnis (advance/reimburse, settle, cancel) sudah diuji tuntas di
 * `ExpenseTest` (model) -- di sini fokus ke OTORISASI, PENGKABELAN
 * Livewire, dan kunci edit di halaman.
 */
class ExpenseResourceTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ExpenseCategory::create(['name' => 'FIXTURE CATEGORY']);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Expenses', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function openAdvance(): Expense
    {
        return Expense::createAdvance([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $this->category->id,
            'recipient_name' => 'budi',
            'advance_amount' => 100000,
        ]);
    }

    // =========================================================================
    // Otorisasi Resource
    // =========================================================================

    /** @test */
    public function the_list_page_requires_view_expenses(): void
    {
        $this->actingAs($this->employee())
            ->get(ExpenseResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_expenses']))
            ->get(ExpenseResource::getUrl('index'))
            ->assertSuccessful();
    }

    /** @test */
    public function the_create_page_requires_create_expenses(): void
    {
        $this->actingAs($this->employee(['view_expenses']))
            ->get(ExpenseResource::getUrl('create'))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_expenses', 'create_expenses']))
            ->get(ExpenseResource::getUrl('create'))
            ->assertSuccessful();
    }

    // =========================================================================
    // Membuat dokumen lewat form
    // =========================================================================

    /** @test */
    public function creating_an_advance_through_the_form_moves_cash_out(): void
    {
        $this->actingAs($this->employee(['view_expenses', 'create_expenses']));

        Livewire::test(CreateExpense::class)
            ->fillForm([
                'type' => Expense::TYPE_ADVANCE,
                'expense_date' => now()->toDateString(),
                'bank_account_id' => BankAccount::cashAccount()->id,
                'expense_category_id' => $this->category->id,
                'recipient_name' => 'budi',
                'advance_amount' => 100000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::firstOrFail();
        $this->assertSame(Expense::STATUS_OPEN, $expense->status);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Expense::class, 'reference_id' => $expense->id,
            'type' => 'out', 'amount' => 100000,
        ]);
    }

    /** @test */
    public function creating_a_reimburse_through_the_form_is_immediately_settled(): void
    {
        $this->actingAs($this->employee(['view_expenses', 'create_expenses']));

        Livewire::test(CreateExpense::class)
            ->fillForm([
                'type' => Expense::TYPE_REIMBURSE,
                'expense_date' => now()->toDateString(),
                'bank_account_id' => BankAccount::cashAccount()->id,
                'expense_category_id' => $this->category->id,
                'recipient_name' => 'budi',
                'receipt_amount' => 50000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::firstOrFail();
        $this->assertSame(Expense::STATUS_SETTLED, $expense->status);
    }

    // =========================================================================
    // Kunci edit
    // =========================================================================

    /** @test */
    public function an_open_expense_can_be_opened_for_edit(): void
    {
        $expense = $this->openAdvance();

        $this->actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->get(ExpenseResource::getUrl('edit', ['record' => $expense]))
            ->assertSuccessful();
    }

    /** @test */
    public function a_settled_expense_redirects_away_from_the_edit_page(): void
    {
        $expense = $this->openAdvance();
        $expense->settle(100000);

        Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->test(EditExpense::class, ['record' => $expense->getRouteKey()])
            ->assertRedirect(ExpenseResource::getUrl('index'));
    }

    // =========================================================================
    // Aksi baris: Settle & Batalkan
    // =========================================================================

    /** @test */
    public function settle_action_is_hidden_without_edit_expenses(): void
    {
        $expense = $this->openAdvance();

        Livewire::actingAs($this->employee(['view_expenses']))
            ->test(ListExpenses::class)
            ->assertTableActionHidden('settle', $expense)
            ->assertTableActionHidden('cancel_expense', $expense);
    }

    /** @test */
    public function settle_action_settles_the_expense(): void
    {
        $expense = $this->openAdvance();

        Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->test(ListExpenses::class)
            ->callTableAction('settle', $expense, ['receipt_amount' => 85000]);

        $expense->refresh();
        $this->assertSame(Expense::STATUS_SETTLED, $expense->status);
        $this->assertSame(85000.0, (float) $expense->receipt_amount);
    }

    /** @test */
    public function cancel_action_cancels_the_expense_and_returns_the_cash(): void
    {
        $expense = $this->openAdvance();

        Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->test(ListExpenses::class)
            ->callTableAction('cancel_expense', $expense);

        $expense->refresh();
        $this->assertSame(Expense::STATUS_CANCELLED, $expense->status);
        $this->assertSame(0.0, BankAccount::cashAccount()->fresh()->currentBalance());
    }

    /** @test */
    public function settle_and_cancel_are_hidden_once_the_expense_is_no_longer_open(): void
    {
        $expense = $this->openAdvance();
        $expense->settle(100000);

        Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->test(ListExpenses::class)
            ->assertTableActionHidden('settle', $expense)
            ->assertTableActionHidden('cancel_expense', $expense);
    }

    // =========================================================================
    // Trashed filter
    // =========================================================================

    /** @test */
    public function a_user_without_the_permission_does_not_see_a_deleted_expense(): void
    {
        $hidup = $this->openAdvance();
        $mati = $this->openAdvance();
        $mati->delete();

        Livewire::actingAs($this->employee(['view_expenses']))
            ->test(ListExpenses::class)
            ->assertCanSeeTableRecords([$hidup])
            ->assertCanNotSeeTableRecords([$mati]);
    }

    /** @test */
    public function a_user_with_the_permission_can_still_reach_a_deleted_expense_through_the_filter(): void
    {
        $hidup = $this->openAdvance();
        $mati = $this->openAdvance();
        $mati->delete();

        Livewire::actingAs($this->employee(['view_expenses', 'view_deleted_expenses']))
            ->test(ListExpenses::class)
            ->assertCanSeeTableRecords([$hidup])
            ->assertCanNotSeeTableRecords([$mati])
            ->filterTable('trashed', 'with')
            ->assertCanSeeTableRecords([$hidup, $mati]);
    }
}
