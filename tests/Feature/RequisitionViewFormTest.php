<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Halaman View menyalin data item ke dalam form Repeater. Field qty, price,
 * dan item_total di form itu memakai ->numeric(), yang dirender sebagai
 * <input type="number">.
 *
 * Input bertipe number TIDAK BISA menampung string ber-pemisah ribuan seperti
 * "1.234,50" — browser menolaknya dan field tampil KOSONG. Karena itu nilai
 * yang dikirim ke form wajib berupa angka mentah, bukan hasil number_format().
 */
class RequisitionViewFormTest extends TestCase
{
    /** @return array<string, array<int, string>> */
    public static function viewPages(): array
    {
        return [
            'Request Beef' => ['Filament/Admin/Resources/ProductRequisitionResource/Pages/ViewProductRequisition.php'],
            'Request Material' => ['Filament/Admin/Resources/MaterialRequisitionResource/Pages/ViewMaterialRequisition.php'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider viewPages
     */
    public function it_does_not_format_numeric_fields_before_filling_the_form(string $file)
    {
        $source = file_get_contents(app_path($file));

        foreach (['qty', 'price', 'item_total'] as $field) {
            $this->assertDoesNotMatchRegularExpression(
                "/'{$field}'\s*=>\s*number_format\(/",
                $source,
                "Field '{$field}' di {$file} diformat sebelum mengisi form. "
                . 'Field itu <input type="number"> dan akan tampil kosong bila diberi string ber-pemisah ribuan.'
            );
        }
    }
}
