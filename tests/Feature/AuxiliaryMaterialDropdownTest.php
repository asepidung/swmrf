<?php

namespace Tests\Feature;

use App\Filament\Support\MasterSelect;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dropdown material di Bill of Material dan Material Waste hanya menampilkan bahan penolong (Owner, 23 Oktober 2026).
 */
class AuxiliaryMaterialDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_auxiliary_materials_are_offered(): void
    {
        $unit = \DB::table('material_units')->insertGetId(['name' => 'PCS', 'created_at' => now(), 'updated_at' => now()]);
        $category = \DB::table('material_categories')->insertGetId(['name' => 'PACKING', 'created_at' => now(), 'updated_at' => now()]);

        $make = fn (string $name, bool $aux): Material => Material::create([
            'code' => strtoupper($name), 'name' => $name, 'material_category_id' => $category, 'material_unit_id' => $unit,
            'min_stock' => 0, 'is_active' => true, 'show_in_stock' => true, 'is_auxiliary' => $aux,
        ]);

        $plastik = $make('PLASTIK', true);
        $make('MESIN', false);

        $options = MasterSelect::search(Material::class, '', true, fn ($q) => $q->where('is_auxiliary', true));

        $this->assertSame([$plastik->id => 'PLASTIK'], $options);
        $this->assertFalse((bool) Material::where('name', 'MESIN')->first()->is_auxiliary, 'bawaan: bukan bahan penolong');
    }
}
