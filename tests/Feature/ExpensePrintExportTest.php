<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CashBookResource\Pages\ListCashBook;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langkah 3 dari issue #453: cetak bukti kas keluar, ekspor tabel Expense,
 * dan label baris Cash Book yang lahir dari Expense.
 */
class ExpensePrintExportTest extends TestCase
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

    /** Cetakan bukti kas keluar harus benar-benar merender, bukan sekadar ada rutenya. */
    public function test_the_print_template_renders_the_expense_number_and_amount(): void
    {
        $expense = Expense::createAdvance([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $this->category->id,
            'recipient_name' => 'budi',
            'advance_amount' => 100000,
        ]);
        $expense->load(['category', 'bankAccount', 'recipientUser', 'createdBy', 'transactions']);

        $html = View::make('print.expense', ['record' => $expense])->render();

        $this->assertStringContainsString($expense->expense_number, $html);
        $this->assertStringContainsString('100.000', $html);
        $this->assertStringContainsString('BUDI', $html);
    }

    /** Ekspor PDF harus benar-benar merender. */
    public function test_the_pdf_export_template_renders(): void
    {
        $expense = Expense::createAdvance([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $this->category->id,
            'recipient_name' => 'budi',
            'advance_amount' => 100000,
        ]);

        $html = View::make('exports.expenses-pdf', [
            'records' => Expense::with('category')->get(),
            'title' => 'Pengeluaran Kas Kecil',
        ])->render();

        $this->assertStringContainsString($expense->expense_number, $html);
        $this->assertStringContainsString('100.000', $html);
    }

    /**
     * Baris kas dari Expense wajib terbaca di Buku Kas -- berisi nomor
     * expense-nya sendiri, bukan sekadar "Advance" polos.
     */
    public function test_the_cash_book_row_from_an_expense_carries_its_expense_number(): void
    {
        $expense = Expense::createAdvance([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $this->category->id,
            'recipient_name' => 'budi',
            'advance_amount' => 100000,
        ]);

        Livewire::actingAs($this->employee(['view_cash_book']))
            ->test(ListCashBook::class)
            ->assertSee($expense->expense_number);
    }

    /** Ketiga tombol ekspor harus jalan tanpa error, dengan atau tanpa filter aktif. */
    public function test_the_export_actions_run_without_errors(): void
    {
        Expense::createAdvance([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $this->category->id,
            'recipient_name' => 'terbuka',
            'advance_amount' => 100000,
        ]);
        Expense::createReimburse([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $this->category->id,
            'recipient_name' => 'selesai',
            'receipt_amount' => 50000,
        ]);

        Livewire::actingAs($this->employee(['view_expenses']))
            ->test(\App\Filament\Admin\Resources\ExpenseResource\Pages\ListExpenses::class)
            ->callTableAction('excel')
            ->assertHasNoTableActionErrors()
            ->callTableAction('pdf')
            ->assertHasNoTableActionErrors()
            ->filterTable('status', Expense::STATUS_SETTLED)
            ->callTableAction('recap_by_category')
            ->assertHasNoTableActionErrors();
    }
}
