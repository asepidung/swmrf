<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\StockTakeResource\Pages\CreateStockTake;
use App\Models\Permission;
use App\Models\StockTake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nomor opname: `ST#` + tahun (2 digit) + urutan 3 digit, mis. `ST#26001`.
 * Keputusan Owner 5 Oktober 2026 (issue #497): opname sebulan sekali, jadi
 * bulan di dalam nomor mubazir. Bentuk lamanya `ST#2610001`.
 */
class StockTakeNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function creator(): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (['view_stock_takes', 'create_stock_takes'] as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Stock Takes', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function createStockTake(User $user, string $date): StockTake
    {
        Livewire::actingAs($user)
            ->test(CreateStockTake::class)
            ->fillForm(['period' => substr($date, 0, 7), 'date' => $date])
            ->call('create')
            ->assertHasNoFormErrors();

        return StockTake::latest('id')->firstOrFail();
    }

    public function test_the_first_stock_take_of_the_year_is_numbered_with_the_year_and_three_digits(): void
    {
        $stockTake = $this->createStockTake($this->creator(), '2026-10-05');

        $this->assertSame('ST#26001', $stockTake->document_number);
    }

    public function test_the_number_follows_the_year_of_the_document_date_and_keeps_counting(): void
    {
        $user = $this->creator();

        $first = $this->createStockTake($user, '2026-10-05');
        $first->update(['status' => StockTake::STATUS_COMPLETED]);

        $second = $this->createStockTake($user, '2026-11-03');
        $second->update(['status' => StockTake::STATUS_COMPLETED]);

        $nextYear = $this->createStockTake($user, '2027-01-04');

        $this->assertSame('ST#26001', $first->document_number);
        $this->assertSame('ST#26002', $second->document_number);
        $this->assertSame('ST#27001', $nextYear->document_number);
    }
}
