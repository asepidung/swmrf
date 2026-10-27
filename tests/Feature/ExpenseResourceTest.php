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

    /** Satu barang bernilai $amount. */
    private function items(float $amount): array
    {
        return [['expense_category_id' => $this->category->id, 'description' => 'barang uji', 'amount' => $amount]];
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
            'recipient_name' => 'budi',
        ], $this->items(100000));
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
                'recipient_name' => 'budi',
                'items' => [
                    ['expense_category_id' => $this->category->id, 'description' => 'plastik', 'amount' => 60000],
                    ['expense_category_id' => $this->category->id, 'description' => 'tali', 'amount' => 40000],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::firstOrFail();
        $this->assertSame(Expense::STATUS_OPEN, $expense->status);
        $this->assertSame(2, $expense->items()->count());
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
                'recipient_name' => 'budi',
                'items' => [['expense_category_id' => $this->category->id, 'description' => 'lakban', 'amount' => 50000]],
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
        $expense->settle($this->items(100000));

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

        $page = Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->test(ListExpenses::class)
            ->mountTableAction('settle', $expense);

        // Form sudah terisi barang uang muka; ubah nilainya sesuai nota (baris yang sama, kuncinya dari form).
        $key = array_key_first($page->instance()->mountedTableActionsData[0]['items']);
        $page->setTableActionData(['total_used' => 85000, 'receipt_note' => 'Nota 123 Toko Maju', 'items' => [$key => $this->items(85000)[0]]])->callMountedTableAction();

        $expense->refresh();
        $this->assertSame(Expense::STATUS_OPEN, $expense->status, 'nota lebih kecil: tetap Open sampai sisanya kembali');
        $this->assertSame(85000.0, (float) $expense->receipt_amount);
        $this->assertSame(15000.0, $expense->outstandingReturn());
        $this->assertSame('Nota 123 Toko Maju', $expense->receipt_note);
    }

    /** @test */
    public function the_return_action_takes_back_the_difference_in_parts(): void
    {
        $expense = $this->openAdvance();
        $expense->settle($this->items(85000));

        $page = Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))->test(ListExpenses::class);

        $page->callTableAction('receive_return', $expense, ['amount' => 5000]);
        $this->assertSame(10000.0, $expense->fresh()->outstandingReturn());

        $page->callTableAction('receive_return', $expense, ['amount' => 10000]);
        $this->assertSame(Expense::STATUS_SETTLED, $expense->fresh()->status);
    }

    /** @test */
    public function the_return_action_is_hidden_while_nothing_is_outstanding_and_without_permission(): void
    {
        $expense = $this->openAdvance();

        Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->test(ListExpenses::class)
            ->assertTableActionHidden('receive_return', $expense);

        $expense->settle($this->items(85000));

        Livewire::actingAs($this->employee(['view_expenses']))
            ->test(ListExpenses::class)
            ->assertTableActionHidden('receive_return', $expense);
    }

    /** @test */
    public function the_open_advances_filter_also_shows_old_advances_beyond_the_current_month(): void
    {
        $old = Expense::createAdvance(['expense_date' => now()->subMonths(2)->toDateString(), 'recipient_name' => 'lama'], $this->items(100000));
        $settled = Expense::createReimburse(['expense_date' => now()->toDateString(), 'recipient_name' => 'beres'], $this->items(50000));

        Livewire::actingAs($this->employee(['view_expenses']))
            ->test(ListExpenses::class)
            ->assertCanNotSeeTableRecords([$old])
            ->filterTable('open', true)
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$settled]);
    }

    /** @test */
    public function a_single_total_without_touching_the_items_is_enough_to_report_back(): void
    {
        $expense = $this->openAdvance();

        $page = Livewire::actingAs($this->employee(['view_expenses', 'edit_expenses']))
            ->test(ListExpenses::class)
            ->mountTableAction('settle', $expense);

        $page->setTableActionData(['total_used' => 450000, 'returned_now' => 0])->callMountedTableAction();

        $expense->refresh();
        $this->assertSame(450000.0, (float) $expense->receipt_amount);
        $this->assertSame(450000.0, (float) $expense->items()->sum('amount'), 'baris barang mengikuti total');
    }

    /** @test */
    public function the_requester_suggestions_include_employees_and_earlier_names(): void
    {
        $segment = \App\Models\CustomerSegment::firstOrCreate(['name' => 'RETAIL']);
        $group = \App\Models\CustomerGroup::create(['name' => 'KARYAWAN']);
        \App\Models\Customer::create(['name' => 'furqon', 'address' => 'Bogor', 'top_days' => 0, 'customer_segment_id' => $segment->id, 'customer_group_id' => $group->id]);
        foreach (['asep idung (bpk)', 'widi (ibu)', 'nunu bpk'] as $name) {
            \App\Models\Customer::create(['name' => $name, 'address' => 'Bogor', 'top_days' => 0, 'customer_segment_id' => $segment->id, 'customer_group_id' => $group->id]);
        }
        $other = \App\Models\CustomerGroup::create(['name' => 'HOTEL']);
        \App\Models\Customer::create(['name' => 'novotel', 'address' => 'Bogor', 'top_days' => 30, 'customer_segment_id' => $segment->id, 'customer_group_id' => $other->id]);
        $this->openAdvance();

        $suggestions = ExpenseResource::requesterSuggestions();

        $this->assertContains('FURQON', $suggestions);
        $this->assertContains('ASEP IDUNG', $suggestions);
        $this->assertContains('WIDI', $suggestions);
        $this->assertContains('NUNU', $suggestions);
        $this->assertNotContains('ASEP IDUNG (BPK)', $suggestions);
        $this->assertContains('BUDI', $suggestions);
        $this->assertNotContains('NOVOTEL', $suggestions);
    }

    /** @test */
    public function the_dashboard_widget_lists_what_each_requester_still_holds(): void
    {
        $this->openAdvance();
        $reported = Expense::createAdvance(['expense_date' => now()->toDateString(), 'recipient_name' => 'sari'], $this->items(50000));
        $reported->settle($this->items(30000));

        $this->actingAs($this->employee(['create_expenses']));
        $this->assertTrue(\App\Filament\Admin\Widgets\ExpenseLiabilityWidget::canView());

        Livewire::test(\App\Filament\Admin\Widgets\ExpenseLiabilityWidget::class)
            ->assertSee('BUDI')
            ->assertSee('SARI')
            ->assertSee('100.000')
            ->assertSee('20.000');

        $this->actingAs($this->employee(['view_expenses']));
        $this->assertFalse(\App\Filament\Admin\Widgets\ExpenseLiabilityWidget::canView());
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
        $expense->settle($this->items(100000));

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
