<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use App\Filament\Admin\Resources\MaterialStockTakeResource\Pages\ListMaterialStockTakes;
use App\Filament\Admin\Resources\MaterialStockTakeResource\Pages\ManageMaterialStockTakeItems;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStock;
use App\Models\MaterialStockTake;
use App\Models\MaterialStockTakeItem;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Material Stock Take, 15 September 2026.
 *
 * Temuan 1 (halaman input hitungan tanpa `canAccess()`) diuji lewat data
 * provider yang sudah ada di `ActionAuthorizationTest::halamanBerbahaya()`.
 */
class MaterialStockTakeSusulanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->material = Material::create([
            'code' => 'MTR-01',
            'name' => 'KERTAS HVS',
            'material_category_id' => MaterialCategory::create(['name' => 'KERTAS'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'RIM'])->id,
            'min_stock' => 0,
            'show_in_stock' => true,
        ]);
    }

    private function opname(string $tanggal, string $status = MaterialStockTake::STATUS_COMPLETED): MaterialStockTake
    {
        return MaterialStockTake::create([
            'document_number' => 'MSO-'.uniqid(),
            'period' => \Illuminate\Support\Carbon::parse($tanggal)->format('Y-m'),
            'date' => $tanggal,
            'status' => $status,
            'summary_note' => 'Catatan opname',
            'created_by' => $this->user->id,
        ]);
    }

    // =====================================================================
    // Temuan 2 -- filter tanggal harus default bulan berjalan, diam-diam
    // =====================================================================

    /** @test */
    public function the_listing_hides_last_months_opname_by_default_but_shows_it_when_the_filter_is_widened(): void
    {
        $lama = $this->opname(now()->subMonth()->startOfMonth()->toDateString());
        $baru = $this->opname(now()->toDateString());

        Livewire::actingAs($this->user)
            ->test(ListMaterialStockTakes::class)
            ->assertSee($baru->document_number)
            ->assertDontSee($lama->document_number);

        Livewire::actingAs($this->user)
            ->test(ListMaterialStockTakes::class)
            ->set('tableFilters.created_at.created_from', now()->subMonths(2)->format('Y-m-d'))
            ->assertSee($baru->document_number)
            ->assertSee($lama->document_number);
    }

    // =====================================================================
    // Temuan 3, 4, 5 -- cetakan opname: field salah/hilang, nol palsu
    // =====================================================================

    /** @test */
    public function the_printout_shows_the_real_period_and_note_fields_as_whole_numbers(): void
    {
        $opname = $this->opname(now()->toDateString());

        MaterialStockTakeItem::create([
            'material_stock_take_id' => $opname->id,
            'material_id' => $this->material->id,
            'system_qty' => 10,
            'physical_qty' => 8,
            'difference_qty' => -2,
        ]);

        $html = view('print.material-stock-take', ['record' => $opname->load('items.material')])->render();

        // Temuan 3: kolom sebenarnya `period`, bukan `periode` -- field ini
        // dulu SELALU kosong karena Eloquent diam-diam mengembalikan null
        // untuk atribut yang tidak dikenal.
        $this->assertStringContainsString($opname->period, $html);

        // Temuan 4: kolom sebenarnya `summary_note`, bukan `note` -- catatan
        // yang diisi dulu tidak pernah muncul di cetakan.
        $this->assertStringContainsString('Catatan opname', $html);
        $this->assertStringNotContainsString('No additional notes.', $html);

        // Temuan 5: qty material bilangan bulat sejak migrasi
        // `2026_09_06_180000_material_quantities_are_whole_numbers.php` --
        // cetakan tidak boleh lagi menambahkan dua desimal palsu ("10,00").
        $this->assertStringContainsString('10', $html);
        $this->assertStringNotContainsString('10,00', $html);
        $this->assertStringNotContainsString('8,00', $html);
        $this->assertStringNotContainsString('-2,00', $html);
    }

    // =====================================================================
    // Temuan 6 -- cetakan opname bisa diakses siapa pun yang login
    // =====================================================================

    /** @test */
    public function the_print_route_is_closed_without_view_permission(): void
    {
        $opname = $this->opname(now()->toDateString());

        $orangLuar = User::create([
            'name' => 'Luar', 'username' => 'luar_cetak_opname',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($orangLuar)
            ->get(route('material-stock-take.print', $opname->id))
            ->assertForbidden();

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_material_stock_takes'],
                ['module_name' => 'Material Stock Take', 'description' => 'view_material_stock_takes'],
            )->id
        );

        $this->actingAs($orangLuar->fresh())
            ->get(route('material-stock-take.print', $opname->id))
            ->assertOk();
    }

    // =====================================================================
    // Susulan batch 3, 17 September 2026
    // =====================================================================

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Material Stock Take', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    /**
     * Sebelumnya cuma `view_material_stock_takes` yang disyaratkan, padahal
     * halaman inilah tempat perubahan stok sungguhan terjadi -- keputusan
     * yang sama dengan Tally: yang MENGUBAH stok butuh izin `edit_...`.
     */
    /** @test */
    public function the_items_page_requires_edit_material_stock_takes_not_just_view(): void
    {
        $opname = $this->opname(now()->toDateString(), MaterialStockTake::STATUS_IN_PROGRESS);

        $this->actingAs($this->employee(['view_material_stock_takes']))
            ->get(MaterialStockTakeResource::getUrl('items', ['record' => $opname]))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_material_stock_takes', 'edit_material_stock_takes']))
            ->get(MaterialStockTakeResource::getUrl('items', ['record' => $opname]))
            ->assertSuccessful();
    }

    /**
     * `applyToStock()` sebelumnya tidak mengunci baris apa pun -- klik
     * ganda / dua tab bisa lolos `isCountable()` di kedua sisi sebelum
     * salah satunya menulis status COMPLETED, lalu KEDUANYA menerapkan
     * selisih yang sama: stok bergerak dua kali untuk satu opname.
     */
    /** @test */
    public function applying_the_same_stock_take_twice_only_moves_stock_once(): void
    {
        MaterialStock::create(['material_id' => $this->material->id, 'qty' => 100]);
        $opname = $this->opname(now()->toDateString(), MaterialStockTake::STATUS_IN_PROGRESS);
        MaterialStockTakeItem::create([
            'material_stock_take_id' => $opname->id, 'material_id' => $this->material->id,
            'system_qty' => 100, 'physical_qty' => 90, 'difference_qty' => -10,
        ]);

        $hasilPertama = $opname->applyToStock();
        // Meniru klik ganda: memanggil lagi pada instance yang SAMA (belum
        // di-fresh()), persis seperti dua permintaan yang tiba nyaris
        // bersamaan sebelum salah satunya sempat menulis status baru.
        $hasilKedua = $opname->applyToStock();

        $this->assertTrue($hasilPertama);
        $this->assertFalse($hasilKedua, 'Panggilan kedua seharusnya tidak menerapkan apa pun lagi.');
        $this->assertSame(90.0, (float) MaterialStock::where('material_id', $this->material->id)->value('qty'));
    }

    /** @test */
    public function the_physical_qty_column_rejects_edits_from_inside_its_own_body_too(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/MaterialStockTakeResource/Pages/ManageMaterialStockTakeItems.php'
        ));
        $badan = substr($source, strpos($source, "updateStateUsing(function"), 600);

        $this->assertStringContainsString("hasPermission('edit_material_stock_takes')", $badan);
    }
}
