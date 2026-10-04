<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\LabelingBoning;
use App\Helpers\BarcodeHelper;
use App\Models\BeefStock;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BarcodeSegments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Barcode 28 digit: segmen berat 6 digit (#486).
 *
 * Label offal, kulit, dan bone sering digabung sampai ratusan-ribuan kilo,
 * padahal 4 digit berat hanya memuat 99,99 kg. `str_pad` tidak memotong, jadi
 * berat 5.747,66 kg menjadi `574766` dan barcodenya diam-diam 28 karakter --
 * semua pembaca yang menghitung menurut posisi bergeser dua karakter.
 */
class BarcodeSegmentsTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // Segmen
    // =====================================================================

    public function test_weight_is_six_digits_and_keeps_two_decimals(): void
    {
        $this->assertSame('002214', BarcodeSegments::weight(22.14));
        $this->assertSame('000005', BarcodeSegments::weight(0.05));
        $this->assertSame('574766', BarcodeSegments::weight(5747.66));
        $this->assertSame('999999', BarcodeSegments::weight(9999.99));
    }

    public function test_weight_rounds_instead_of_truncating(): void
    {
        // 22,145 kg x 100 = 2214,5 -> 2215. Memotong akan menghasilkan 2214.
        $this->assertSame('002215', BarcodeSegments::weight(22.145));
        // Pecahan biner (0.29 * 100 = 28.999...) tidak boleh menyusutkan 1 digit.
        $this->assertSame('000029', BarcodeSegments::weight(0.29));
    }

    public function test_a_weight_that_does_not_fit_is_refused_not_silently_widened(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BarcodeSegments::weight(10000);
    }

    public function test_a_negative_weight_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BarcodeSegments::weight(-1);
    }

    public function test_pcs_is_two_digits_and_refuses_overflow(): void
    {
        $this->assertSame('08', BarcodeSegments::pcs(8));
        $this->assertSame('99', BarcodeSegments::pcs(99));

        $this->expectException(InvalidArgumentException::class);

        BarcodeSegments::pcs(100);
    }

    public function test_a_barcode_is_taken_apart_by_position_and_round_trips(): void
    {
        $barcode = '1'.'150626'.'100100'.'3'.BarcodeSegments::weight(5747.66).'08'.'55'.'0007';

        $this->assertSame(28, strlen($barcode));

        $this->assertSame([
            'origin' => '1',
            'date' => '150626',
            'product' => '100100',
            'grade' => '3',
            'weight' => 5747.66,
            'pcs' => 8,
            'ph' => 5.5,
            'sequence' => '0007',
        ], BarcodeSegments::parse($barcode));
    }

    public function test_only_28_character_barcodes_are_standard(): void
    {
        $this->assertTrue(BarcodeSegments::isStandard(str_repeat('1', 28)));
        $this->assertFalse(BarcodeSegments::isStandard(str_repeat('1', 26)), '26 karakter = format lama');
        $this->assertFalse(BarcodeSegments::isStandard(str_repeat('1', 27)));
        $this->assertFalse(BarcodeSegments::isStandard(str_repeat('1', 29)));
        $this->assertFalse(BarcodeSegments::isStandard('1456260928000048310'), 'barcode legacy');
        $this->assertFalse(BarcodeSegments::isStandard(null));
        $this->assertNull(BarcodeSegments::parse(str_repeat('1', 26)));
    }

    public function test_origin_is_read_from_a_standard_barcode_and_unknown_otherwise(): void
    {
        $this->assertSame('BONING', BarcodeHelper::getOrigin('1'.str_repeat('0', 27)));
        $this->assertSame('R-RTRN', BarcodeHelper::getOrigin('4'.str_repeat('0', 27)));
        $this->assertSame('-UNIND', BarcodeHelper::getOrigin('1'.str_repeat('0', 25)));
        $this->assertSame('-UNIND', BarcodeHelper::getOrigin('1456260928000048310'));
    }

    // =====================================================================
    // Lewat jalur nyata: label Boning
    // =====================================================================

    public function test_a_heavy_offal_label_gets_a_28_digit_barcode_with_the_right_origin(): void
    {
        $category = ProductCategory::create(['name' => 'OFFAL', 'prefix' => 'OF', 'is_active' => true]);
        $product = Product::create([
            'name' => 'OFFAL', 'code' => 'OF0001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $boning = Boning::create([
            'boning_date' => now()->format('Y-m-d'),
            'created_by' => User::factory()->create(['role' => 'programmer', 'is_active' => true])->id,
        ]);

        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        foreach (['view_bonings', 'edit_bonings'] as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Boning', 'description' => $name])->id
            );
        }

        Livewire::actingAs($user->fresh())
            ->test(LabelingBoning::class, ['record' => $boning])
            ->set('data.warehouse_id', $warehouse->id)
            ->set('data.product_id', $product->id)
            ->set('data.grade_id', $grade->id)
            ->set('data.pack_date', now()->format('Y-m-d'))
            ->set('data.qty_pcs_combined', '5747.66/1')
            ->set('data.ph_level', '5.5')
            ->call('create');

        $item = BoningItem::firstOrFail();

        $this->assertSame(28, strlen($item->barcode));
        $this->assertSame('574766', substr($item->barcode, 14, 6));
        $this->assertSame(5747.66, BarcodeSegments::parse($item->barcode)['weight']);
        $this->assertEqualsWithDelta(5747.66, (float) $item->weight, 0.001);

        // Asal label terbaca BONING, bukan -UNIND seperti pada barcode yang
        // diam-diam meluap menjadi panjang lain.
        $this->assertSame('BONING', BeefStock::firstOrFail()->origin);
    }

    // =====================================================================
    // Penjaga
    // =====================================================================

    /**
     * Penjaga: segmen berat dan pcs hanya boleh disusun oleh `BarcodeSegments`.
     *
     * Bentuk lamanya, `str_pad(round($weight * 100), 4, ...)`, ditulis tangan
     * di tujuh tempat dan tidak memotong -- berat yang tidak muat meluap
     * diam-diam. Menyisir seluruh `app/`, komentar dibuang lebih dulu.
     */
    public function test_nobody_builds_the_weight_or_pcs_segment_by_hand(): void
    {
        $pelanggar = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_ends_with(str_replace('\\', '/', $file->getPathname()), 'app/Support/BarcodeSegments.php')) {
                continue;
            }

            $kode = '';
            foreach (@token_get_all(file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    // Ganti dengan baris kosong sejumlah yang sama: nomor baris tetap benar.
                    $kode .= str_repeat("\n", substr_count($token[1], "\n"));

                    continue;
                }
                $kode .= is_array($token) ? $token[1] : $token;
            }

            foreach (explode("\n", $kode) as $nomor => $baris) {
                if (preg_match('/str_pad\(\s*round\([^)]*\*\s*100\s*\)/', $baris)
                    || preg_match('/\$(weightStr|pcsStr)\s*=\s*str_pad\(/', $baris)
                    || preg_match('/substr\(\s*\$\w+\s*,\s*(14|18)\s*,\s*[42]\s*\)/', $baris)) {
                    $pelanggar[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($nomor + 1);
                }
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Segmen barcode berikut disusun/dibaca tangan di luar BarcodeSegments. str_pad tidak memotong, "
            ."jadi berat yang tidak muat meluap diam-diam dan semua pembaca berbasis posisi bergeser:\n"
            .implode("\n", $pelanggar),
        );
    }
}
