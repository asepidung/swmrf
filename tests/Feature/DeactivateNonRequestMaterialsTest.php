<?php

namespace Tests\Feature;

use App\Console\Commands\DeactivateNonRequestMaterials;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivateNonRequestMaterialsTest extends TestCase
{
    use RefreshDatabase;

    private function material(string $name): Material
    {
        $category = MaterialCategory::first() ?? MaterialCategory::create(['name' => 'CAT', 'is_active' => true]);
        $unit = MaterialUnit::first() ?? MaterialUnit::create(['name' => 'PCS', 'is_active' => true]);

        return Material::create(['name' => $name, 'material_category_id' => $category->id, 'material_unit_id' => $unit->id, 'is_active' => true]);
    }

    /** @test */
    public function only_the_non_request_items_are_deactivated_and_dry_run_writes_nothing(): void
    {
        $atk = $this->material('kertas a4');
        $request = $this->material('GARAM CURING');

        $this->artisan('materials:deactivate-non-request')->assertSuccessful();
        $this->assertTrue($atk->fresh()->is_active);

        $this->artisan('materials:deactivate-non-request', ['--apply' => true])->assertSuccessful();

        $this->assertFalse($atk->fresh()->is_active);
        $this->assertTrue($request->fresh()->is_active);
        $this->assertNotNull(Material::find($atk->id), 'dinonaktifkan, bukan dihapus');

        $this->artisan('materials:deactivate-non-request', ['--apply' => true])->assertSuccessful();
        $this->assertGreaterThan(70, count(DeactivateNonRequestMaterials::NAMES));
    }
}
