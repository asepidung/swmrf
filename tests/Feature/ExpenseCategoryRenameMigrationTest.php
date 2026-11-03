<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nama kategori pengeluaran dijernihkan tanpa menyentuh id atau barang yang memakainya (Owner, 3 November 2026). */
class ExpenseCategoryRenameMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_names_become_clear_names_and_ids_stay(): void
    {
        $kirim = ExpenseCategory::create(['name' => 'PENGIRIMAN & PENGANGKUTAN']);
        $pulsa = ExpenseCategory::create(['name' => 'OPERASIONAL PULSA MARKETING']);
        \Illuminate\Support\Facades\DB::table('expense_categories')->where('id', $pulsa->id)->update(['name' => 'OPERASIONAL PULSA MARKETING  ']);
        $lain = ExpenseCategory::create(['name' => 'LAIN-LAIN']);

        (include database_path('migrations/2026_11_03_100000_clarify_expense_category_names.php'))->up();

        $this->assertSame('ONGKOS KIRIM & EKSPEDISI', $kirim->fresh()->name);
        $this->assertSame('PULSA & KUOTA MARKETING', $pulsa->fresh()->name);
        $this->assertSame('LAIN-LAIN', $lain->fresh()->name);

        // Dijalankan lagi tidak mengubah apa pun.
        (include database_path('migrations/2026_11_03_100000_clarify_expense_category_names.php'))->up();
        $this->assertSame('ONGKOS KIRIM & EKSPEDISI', $kirim->fresh()->name);
    }
}
