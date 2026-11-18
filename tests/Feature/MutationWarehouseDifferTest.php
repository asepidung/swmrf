<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MutationResource\Pages\CreateMutation;
use App\Models\Mutation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** MT-01: gudang asal dan tujuan mutasi tidak boleh sama. */
class MutationWarehouseDifferTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_mutation_to_the_same_warehouse_is_refused_but_to_another_one_is_accepted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'programmer', 'is_active' => true]));
        $a = Warehouse::create(['code' => 'AAA', 'name' => 'GUDANG A', 'is_active' => true]);
        $b = Warehouse::create(['code' => 'BBB', 'name' => 'GUDANG B', 'is_active' => true]);

        Livewire::test(CreateMutation::class)
            ->fillForm(['mutation_date' => now()->toDateString(), 'from_warehouse_id' => $a->id, 'to_warehouse_id' => $a->id])
            ->call('create')
            ->assertHasFormErrors(['to_warehouse_id']);

        $this->assertSame(0, Mutation::count());

        Livewire::test(CreateMutation::class)
            ->fillForm(['mutation_date' => now()->toDateString(), 'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Mutation::count());
    }
}
