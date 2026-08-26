<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\CreateMaterialRequisition;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialUnit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Material Requisition tadinya tidak punya pemformatan sama sekali: qty dan
 * price memakai ->numeric() polos (bahkan tertulis dua kali, sisa salin-tempel
 * dari Product), tanpa listener pemisah ribuan apa pun.
 *
 * Menambahkan mask TANPA membenahi titik simpannya justru berbahaya: keempat
 * halaman yang menyimpan item (Create, Edit, Review, Finance) menulis nilai
 * form APA ADANYA tanpa parseNumber(). Selama field itu <input type="number">,
 * itu aman karena browser menolak string ber-pemisah ribuan. Begitu mask
 * dipasang, browser mulai menerima "250.000" -- dan PHP membacanya sebagai
 * 250.0, sehingga harga 250 ribu tersimpan menyusut jadi 250 tanpa error
 * apa pun. Test ini membuktikan rantai lengkapnya sudah aman.
 */
class MaterialRequisitionThousandSeparatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer',
            'username' => 'mat_sep_programmer',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'CV KEMASAN JAYA',
            'address' => 'Bogor',
            'pic' => 'Rudi',
            'top_days' => 30,
        ]);

        $this->material = Material::create([
            'code' => 'MTR001',
            'name' => 'PLASTIK VACUUM',
            'material_category_id' => MaterialCategory::create(['name' => 'KEMASAN'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'PCS'])->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_stores_a_thousand_separated_price_at_its_full_value()
    {
        Livewire::actingAs($this->user)
            ->test(CreateMaterialRequisition::class)
            ->fillForm([
                'due_date' => now()->toDateString(),
                'supplier_id' => $this->supplier->id,
                'items' => [
                    [
                        'material_id' => $this->material->id,
                        'qty' => '1.500',
                        'price' => '15.000',
                        'note' => null,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $requisition = MaterialRequisition::first();
        $this->assertNotNull($requisition, 'Request gagal dibuat.');

        $item = $requisition->items()->first();
        $this->assertNotNull($item, 'Item request tidak tersimpan.');

        $this->assertEquals(15000, $item->price, 'Harga 15.000 tersimpan menyusut. Nilai berformat tidak di-parse.');
        $this->assertEquals(1500, $item->qty, 'Qty 1.500 tersimpan menyusut. Nilai berformat tidak di-parse.');
        $this->assertEquals(22500000, $item->subtotal, 'Subtotal wajib dihitung dari nilai yang sudah di-parse.');
    }

    /** @test */
    public function it_still_accepts_plain_unformatted_numbers()
    {
        Livewire::actingAs($this->user)
            ->test(CreateMaterialRequisition::class)
            ->fillForm([
                'due_date' => now()->toDateString(),
                'supplier_id' => $this->supplier->id,
                'items' => [
                    [
                        'material_id' => $this->material->id,
                        'qty' => '50',
                        'price' => '5000',
                        'note' => null,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = MaterialRequisition::first()->items()->first();

        $this->assertEquals(5000, $item->price);
        $this->assertEquals(50, $item->qty);
        $this->assertEquals(250000, $item->subtotal);
    }

    /**
     * qty dan price tidak boleh lagi <input type="number">: browser menolak
     * pemisah ribuan dan fieldnya tampil kosong.
     *
     * @test
     */
    public function it_keeps_qty_and_price_out_of_native_number_inputs()
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/MaterialRequisitionResource.php'));

        $repeaterBlock = substr(
            $source,
            strpos($source, "Repeater::make('items')"),
            strpos($source, "Section::make(fn() => __('Summary')") - strpos($source, "Repeater::make('items')"),
        );

        // Baris komentar yang MENYEBUT ->numeric() (menjelaskan kenapa ia
        // dilepas) tidak boleh membuat asersi ini keliru positif.
        $codeOnly = preg_replace('/^\s*\/\/.*$/m', '', $repeaterBlock);

        $this->assertStringNotContainsString('->numeric()', $codeOnly);
        $this->assertStringContainsString("'inputmode' => 'decimal'", $repeaterBlock);
        $this->assertStringContainsString("'inputmode' => 'numeric'", $repeaterBlock);
    }

    /**
     * Listener pemformat wajib dipasang di Section pembungkus, BUKAN di dalam
     * baris Repeater. Alpine yang menempel di baris memicu bug "baris zombie"
     * saat baris dihapus.
     *
     * @test
     */
    public function it_attaches_the_live_formatter_outside_the_repeater_rows()
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/MaterialRequisitionResource.php'));

        $sectionPos = strpos($source, "Section::make(fn() => __('Item Details'))");
        $repeaterPos = strpos($source, "Repeater::make('items')");

        $this->assertNotFalse($sectionPos);
        $this->assertNotFalse($repeaterPos);

        $wrapper = substr($source, $sectionPos, $repeaterPos - $sectionPos);

        $this->assertStringContainsString('x-on:input', $wrapper);
        $this->assertStringContainsString('x-on:keydown', $wrapper);
    }

    /** @test */
    public function it_never_uses_the_rawjs_money_mask_inside_the_repeater()
    {
        $this->assertStringNotContainsString(
            'money(',
            file_get_contents(app_path('Filament/Admin/Resources/MaterialRequisitionResource.php')),
        );
    }
}
