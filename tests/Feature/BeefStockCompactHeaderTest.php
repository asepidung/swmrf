<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bagian atas halaman Beef Stock diringkas (Owner, 22 Oktober 2026): filter di balik ikon corong, bukan terbuka di atas tabel.
 */
class BeefStockCompactHeaderTest extends TestCase
{
    public function test_the_filters_sit_behind_the_toolbar_icon_but_the_position_safeguard_stays_visible(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/BeefStockResource.php'));

        $this->assertStringContainsString('->filtersLayout(FiltersLayout::Dropdown)', $source);
        $this->assertStringNotContainsString('FiltersLayout::AboveContent', $source);

        // Pengaman "angka ini milik tanggal lain" tidak boleh ikut hilang: kalimat posisi tetap di atas tabel, dan filter tanggal punya lencana.
        $this->assertStringContainsString('->description(fn (): ?string => static::keteranganPosisi())', $source);
        $this->assertStringContainsString("__('Position as at :date'", $source);
    }
}
