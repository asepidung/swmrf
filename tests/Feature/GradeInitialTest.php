<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RepackResource\Pages\InputHasilRepack;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Repack;
use App\Models\RepackResult;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Grade tampil satu huruf di layar scan/hasil barcode: CHILL=C, FROZEN=F, A, B, R. */
class GradeInitialTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function grades_are_shown_as_one_letter(): void
    {
        $this->assertSame('C', Grade::initial('CHILL'));
        $this->assertSame('F', Grade::initial('FROZEN'));
        $this->assertSame('A', Grade::initial('A'));
        $this->assertSame('B', Grade::initial('B'));
        $this->assertSame('R', Grade::initial('R'));
        $this->assertSame('', Grade::initial(null));
    }

    /** @test */
    public function the_repack_result_list_shows_the_letter_but_keeps_the_stored_name(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $gudang = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::create(['name' => 'FROZEN', 'is_active' => true]);
        $kategori = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $produk = Product::create(['name' => 'SIRLOIN', 'code' => 'B001', 'category_id' => $kategori->id, 'structure_type' => 'main', 'is_active' => true]);
        $repack = Repack::create(['repack_date' => now()->toDateString(), 'created_by' => $user->id]);
        $hasil = RepackResult::create([
            'repack_id' => $repack->id, 'product_id' => $produk->id, 'warehouse_id' => $gudang->id, 'grade_id' => $grade->id,
            'weight' => 5, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'barcode' => 'BC-G',
        ]);

        Livewire::actingAs($user)->test(InputHasilRepack::class, ['record' => $repack])
            ->assertTableColumnFormattedStateSet('grade.name', 'F', $hasil)
            ->assertTableColumnStateSet('grade.name', 'FROZEN', $hasil);

        $this->assertSame('FROZEN', $grade->fresh()->name);
    }
}
