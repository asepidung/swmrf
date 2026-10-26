<?php

namespace Tests\Feature;

use App\Console\Commands\SeedExpenseCategories;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedExpenseCategoriesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dry_run_writes_nothing_and_apply_is_safe_to_repeat(): void
    {
        $before = ExpenseCategory::count();

        $this->artisan('expenses:seed-categories')->assertSuccessful();
        $this->assertSame($before, ExpenseCategory::count());

        $this->artisan('expenses:seed-categories', ['--apply' => true])->assertSuccessful();
        $afterFirst = ExpenseCategory::count();
        $this->assertSame($before + count(SeedExpenseCategories::CATEGORIES), $afterFirst);

        $this->artisan('expenses:seed-categories', ['--apply' => true])->assertSuccessful();
        $this->assertSame($afterFirst, ExpenseCategory::count());
    }

    /** @test */
    public function a_similar_existing_name_is_not_duplicated(): void
    {
        ExpenseCategory::create(['name' => 'Pengobatan', 'is_active' => true]);
        $before = ExpenseCategory::count();

        $this->artisan('expenses:seed-categories', ['--apply' => true])->assertSuccessful();

        $this->assertSame($before + count(SeedExpenseCategories::CATEGORIES) - 1, ExpenseCategory::count());
    }
}
