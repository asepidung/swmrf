<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialResource\Pages\CreateMaterial;
use App\Filament\Admin\Resources\MaterialResource\Pages\EditMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #509, langkah 1: "Isi per satuan" dan penanda drylog di master material.
 *
 * Material dibeli per satuan beli (1 Box @ Rp 1.000.000, isi 1.000 pcs) tetapi
 * dipakai dan dibuang per satuan pakai (pcs). Kolom `content_per_unit`
 * menjembatani keduanya HANYA untuk menilai; PO, GR, dan stok tidak berubah.
 */
class MaterialContentPerUnitTest extends TestCase
{
    use RefreshDatabase;

    private function programmer(): User
    {
        return User::create([
            'name' => 'Programmer',
            'username' => 'prog_'.uniqid(),
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'PLASTIK VAKUM',
            'material_unit_id' => MaterialUnit::firstOrCreate(['name' => 'BOX'])->id,
            'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'PACKAGING'])->id,
            'min_stock' => 0,
            'content_per_unit' => 1000,
        ], $override);
    }

    /** @test */
    public function a_material_defaults_to_one_per_unit_and_not_drylog(): void
    {
        $material = Material::create([
            'name' => 'KARTON',
            'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'PACKAGING'])->id,
            'material_unit_id' => MaterialUnit::firstOrCreate(['name' => 'PCS'])->id,
            'min_stock' => 0,
            'is_active' => true,
        ])->fresh();

        $this->assertSame(1, $material->content_per_unit);
        $this->assertFalse($material->is_drylog);
    }

    /** @test */
    public function the_content_per_unit_and_the_drylog_flag_are_saved_from_the_form(): void
    {
        $this->actingAs($this->programmer());

        Livewire::test(CreateMaterial::class)
            ->fillForm($this->payload(['is_drylog' => true]))
            ->call('create')
            ->assertHasNoFormErrors();

        $material = Material::where('name', 'PLASTIK VAKUM')->firstOrFail();
        $this->assertSame(1000, $material->content_per_unit);
        $this->assertTrue($material->is_drylog);
    }

    /** @test */
    public function the_content_per_unit_is_required_and_must_be_a_whole_number_of_at_least_one(): void
    {
        $this->actingAs($this->programmer());

        foreach ([0, -5, 2.5, null] as $bad) {
            Livewire::test(CreateMaterial::class)
                ->fillForm($this->payload(['content_per_unit' => $bad]))
                ->call('create')
                ->assertHasFormErrors(['content_per_unit']);
        }

        $this->assertSame(0, Material::count());
    }

    /** @test */
    public function the_values_come_back_on_the_edit_form(): void
    {
        $this->actingAs($this->programmer());
        $material = Material::create($this->payload(['is_active' => true, 'is_drylog' => true]));

        Livewire::test(EditMaterial::class, ['record' => $material->id])
            ->assertFormSet(['content_per_unit' => 1000, 'is_drylog' => true]);
    }
}
