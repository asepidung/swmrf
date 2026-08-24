<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Aturan yang dijaga di sini: sebuah field boleh diberi nilai berformat
 * (ber-pemisah ribuan) HANYA bila field itu bukan `->numeric()`.
 *
 * `->numeric()` dirender sebagai `<input type="number">`, dan input bertipe
 * number tidak bisa menampung string seperti "1.234,50" — browser menolaknya
 * dan fieldnya tampil KOSONG. Bukan error, jadi gejalanya menyesatkan.
 *
 * Keduanya harus bergerak bersamaan. Memformat tanpa melepas `->numeric()`
 * membuat field kosong; melepas `->numeric()` tanpa mem-parse saat menyimpan
 * membuat "250.000" tersimpan sebagai 250.
 */
class RequisitionViewFormTest extends TestCase
{
    /** @return array<string, array<int, string>> */
    public static function requisitionModules(): array
    {
        return [
            'Request Beef' => [
                'Filament/Admin/Resources/ProductRequisitionResource.php',
                'Filament/Admin/Resources/ProductRequisitionResource/Pages/ViewProductRequisition.php',
            ],
            'Request Material' => [
                'Filament/Admin/Resources/MaterialRequisitionResource.php',
                'Filament/Admin/Resources/MaterialRequisitionResource/Pages/ViewMaterialRequisition.php',
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider requisitionModules
     */
    public function it_only_formats_fields_that_are_not_native_number_inputs(string $resourceFile, string $viewFile)
    {
        $resource = file_get_contents(app_path($resourceFile));
        $view = file_get_contents(app_path($viewFile));

        foreach (['qty', 'price', 'item_total'] as $field) {
            $start = strpos($resource, "TextInput::make('{$field}')");

            if ($start === false) {
                continue;
            }

            $isNativeNumberInput = str_contains(substr($resource, $start, 900), '->numeric()');
            $isFormattedOnFill = (bool) preg_match("/'{$field}'\s*=>\s*number_format\(/", $view);

            if ($isNativeNumberInput) {
                $this->assertFalse(
                    $isFormattedOnFill,
                    "Field '{$field}' memakai ->numeric() tapi diberi nilai berformat di {$viewFile}. "
                    . 'Input bertipe number menolak pemisah ribuan, jadi fieldnya akan tampil kosong.'
                );
            } else {
                $this->assertTrue(true);
            }
        }
    }
}
