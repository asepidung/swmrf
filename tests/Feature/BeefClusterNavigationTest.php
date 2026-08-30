<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BeefStockAgingResource;
use App\Filament\Admin\Resources\BeefStockMovementResource;
use App\Filament\Admin\Resources\BeefStockResource;
use App\Filament\Clusters\BeefStocks\Pages\FoundItemScanner;
use Tests\TestCase;

/**
 * Beef Stock harus jadi pintu masuk cluster Beef.
 *
 * Keputusan Project Owner: membuka menu Beef harus langsung mendarat di stok,
 * karena itulah yang paling sering dilihat. Filament menentukan urutan
 * sub-menu -- dan karenanya halaman mana yang dituju saat cluster diklik --
 * lewat `navigationSort`.
 *
 * Sebelumnya BeefStockResource dan BeefStockAgingResource sama-sama bernilai
 * 3. Urutan dua nilai kembar diputuskan tie-break, bukan oleh keputusan siapa
 * pun, sehingga Beef Stock terdampar di posisi ketiga tanpa ada yang memilih
 * begitu. Test ini menjaga urutannya tetap disengaja: nilai yang kembar
 * langsung dianggap salah.
 */
class BeefClusterNavigationTest extends TestCase
{
    public function test_beef_stock_comes_first_in_the_cluster(): void
    {
        $this->assertSame(1, BeefStockResource::getNavigationSort());
    }

    public function test_every_page_in_the_cluster_has_its_own_distinct_position(): void
    {
        $sorts = [
            'Beef Stock' => BeefStockResource::getNavigationSort(),
            'Stock Movements' => BeefStockMovementResource::getNavigationSort(),
            'Aging' => BeefStockAgingResource::getNavigationSort(),
            'Found Item' => FoundItemScanner::getNavigationSort(),
        ];

        $this->assertSame(
            count($sorts),
            count(array_unique($sorts)),
            'Dua halaman memakai navigationSort yang sama, jadi urutannya ditentukan tie-break '
                .'dan bukan oleh keputusan siapa pun: '.json_encode($sorts),
        );

        // Dan Beef Stock benar-benar yang terkecil, bukan sekadar unik.
        $this->assertSame(min($sorts), BeefStockResource::getNavigationSort());
    }

    /** Diksi tombol wajib berkunci Inggris dan terdaftar di kedua bahasa. */
    public function test_the_damaged_label_button_uses_an_english_key_registered_in_both_languages(): void
    {
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);
        $en = json_decode(file_get_contents(base_path('lang/en.json')), true);

        $this->assertArrayHasKey('Damaged Label', $id);
        $this->assertArrayHasKey('Damaged Label', $en);
        $this->assertSame('Label Rusak', $id['Damaged Label']);

        // Kunci Indonesia yang lama tidak boleh hidup lagi di kode mana pun.
        $this->assertArrayNotHasKey('Label Rusak', $id);
        $this->assertArrayNotHasKey('Label Rusak', $en);
    }

    /**
     * Kelas warna Tailwind tidak bekerja di blade panel ini.
     *
     * Proyek ini tidak mengompilasi tema Filament kustom, jadi `bg-warning-500`
     * dan kerabatnya tidak menghasilkan CSS apa pun -- tombolnya tetap bisa
     * diklik, hanya tidak berwarna, sehingga kerusakannya tidak pernah terasa
     * sebagai kerusakan. Halaman ini memakai komponen Filament.
     */
    public function test_the_scanner_page_does_not_rely_on_uncompiled_color_classes(): void
    {
        $blade = file_get_contents(
            resource_path('views/filament/clusters/beef-stocks/pages/found-item-scanner.blade.php')
        );

        $this->assertStringNotContainsString('bg-warning-', $blade);
        $this->assertStringContainsString('<x-filament::button', $blade);
    }
}
