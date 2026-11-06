<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga perbaikan audit UI/UX 6 November 2026 (#808).
 *
 * Semuanya urusan CSS dan atribut HTML yang tidak punya "kegagalan keras":
 * kalau hilang, layar tetap tampil, hanya teksnya kecil, tombolnya pucat,
 * atau gelembung peringatan browser kembali muncul. Karena itu dijaga di sini.
 */
class MobileAccessibilityGuardTest extends TestCase
{
    private function read(string $relative): string
    {
        return file_get_contents(base_path($relative));
    }

    public function test_font_dasar_kembali_100_persen_di_hp(): void
    {
        $provider = $this->read('app/Providers/Filament/AdminPanelProvider.php');

        $this->assertStringContainsString(
            '@media (max-width: 767px) { html { font-size: 100%; } }',
            $provider,
            'Di bawah 768px font dasar harus 100%, bukan 90%/85% milik layar lebar.'
        );

        // Aturan 100% harus muncul SESUDAH aturan 85%, kalau tidak kalah urutan.
        $this->assertGreaterThan(
            strpos($provider, '@media (max-width: 1279px) { html { font-size: 85%; } }'),
            strpos($provider, '@media (max-width: 767px) { html { font-size: 100%; } }')
        );
    }

    public function test_warna_tombol_utama_cukup_kontras(): void
    {
        $provider = $this->read('app/Providers/Filament/AdminPanelProvider.php');

        $this->assertStringContainsString('Color::Amber[700]', $provider);
        $this->assertStringContainsString('Color::Green[700]', $provider);
        $this->assertStringContainsString('focus-visible', $provider);
    }

    public function test_teks_primary_tanpa_varian_gelap_tidak_pucat_di_mode_gelap(): void
    {
        $css = $this->read('resources/views/filament/admin/missing-color-utilities.blade.php');

        $this->assertStringContainsString(':root.dark .text-primary-600:not([class*="dark:text-"])', $css);
        $this->assertStringContainsString(':root.dark .text-success-600:not([class*="dark:text-"])', $css);
    }

    public function test_target_sentuh_item_repeater_sales_order_di_hp(): void
    {
        $this->assertStringContainsString(
            "'class' => 'swm-so-repeater'",
            $this->read('app/Filament/Admin/Resources/SalesOrderResource.php')
        );

        $css = $this->read('resources/views/filament/admin/missing-color-utilities.blade.php');

        $this->assertStringContainsString('.swm-so-repeater .fi-fo-repeater-item-header', $css);
        $this->assertStringContainsString('min-height: 2.75rem', $css);
    }

    public function test_tombol_simpan_sales_order_memakai_validasi_filament_bukan_browser(): void
    {
        foreach ([
            'app/Filament/Admin/Resources/SalesOrderResource/Pages/CreateSalesOrder.php',
            'app/Filament/Admin/Resources/SalesOrderResource/Pages/EditSalesOrder.php',
        ] as $page) {
            $this->assertStringContainsString("'formnovalidate' => true", $this->read($page), $page);
        }
    }
}
