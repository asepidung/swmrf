<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Desain label produk HARUS sama di semua jenis label (keputusan Ayah,
 * 4 Oktober 2026): logo Halal + nomor sertifikat/NKV/registrasi datang dari
 * SATU partial (`print.partials.legal-block`) dan SATU file nomor
 * (`config/label.php`) -- tidak diketik ulang di Blade label manapun.
 */
class LabelLegalBlockTest extends TestCase
{
    private function labelViews(): array
    {
        return glob(resource_path('views/print/*-label.blade.php'));
    }

    public function test_every_product_label_uses_the_shared_legal_block(): void
    {
        $views = array_filter(
            $this->labelViews(),
            fn (string $path) => str_contains(file_get_contents($path), 'id="barcode"'),
        );

        $this->assertGreaterThanOrEqual(7, count($views), 'Label produk berbarcode yang ditemukan lebih sedikit dari dugaan.');

        foreach ($views as $path) {
            $this->assertStringContainsString(
                "@include('print.partials.legal-block')",
                file_get_contents($path),
                basename($path).' tidak memakai partial legal-block.',
            );
        }
    }

    public function test_no_label_hardcodes_the_legal_numbers_or_the_old_logo(): void
    {
        $forbidden = ['img/halal.png', 'ID00110015321510124', 'ID00310000134840521', 'RPHR', 'PHD3201', 'NKV'];

        foreach ($this->labelViews() as $path) {
            $source = file_get_contents($path);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $source, basename($path)." mengetik '$needle' langsung -- pakai config/label.php lewat partial.");
            }
        }
    }

    public function test_the_shared_block_prints_every_number_from_the_config(): void
    {
        $html = view('print.partials.legal-block')->render();

        foreach (['halal_gudang', 'nkv_rph', 'nkv_gudang', 'registrasi_produk'] as $key) {
            $this->assertNotEmpty(config("label.$key"));
            $this->assertStringContainsString(config("label.$key"), $html);
        }
    }
}
