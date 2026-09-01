<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ringkasan Delivery Order dan isian berat diterima.
 *
 * Dua hal yang diperbaiki bersamaan karena keduanya ada di alur yang sama.
 */
class DeliveryOrderTotalsAndWeightTest extends TestCase
{
    private function resource(): string
    {
        return file_get_contents(app_path('Filament/Admin/Resources/DeliveryOrderResource.php'));
    }

    private function approvePage(): string
    {
        return file_get_contents(app_path(
            'Filament/Admin/Resources/DeliveryOrderResource/Pages/ApproveDeliveryOrder.php'
        ));
    }

    /**
     * Ringkasan totalnya dirender Filament, bukan HTML rakitan tangan.
     *
     * Blok lamanya memakai huruf extrabold, label kapital renggang berukuran
     * sepuluh piksel, garis pemisah, bayangan, serta warna amber dan emerald.
     * Ramai untuk tiga angka yang cuma perlu dibaca sekilas -- dan sebagian
     * gayanya bahkan tidak pernah muncul, karena kelas-kelas itu tidak ada di
     * CSS bawaan Filament sementara panel ini tidak memuat hasil build CSS
     * aplikasi.
     */
    public function test_the_totals_are_rendered_by_filament_not_hand_rolled_html(): void
    {
        $source = $this->resource();

        foreach ([
            'total_box',
            'total_weight',
            'total_received_weight',
        ] as $name) {
            $this->assertStringContainsString(
                "Placeholder::make('".$name."')",
                $source,
                $name.' tidak dirender sebagai Placeholder.',
            );
        }

        $this->assertStringNotContainsString('HtmlString', $source);
    }

    /**
     * Kelas yang tidak punya CSS di panel ini tidak boleh kembali.
     *
     * Panel admin tidak memuat hasil build CSS aplikasi, sehingga kelas
     * seperti ini tidak menghasilkan apa pun -- dan kegagalannya senyap.
     */
    public function test_no_class_without_css_is_used_for_the_totals(): void
    {
        $source = $this->resource();

        foreach ([
            'text-amber-600',
            'text-emerald-600',
            'tracking-wider',
            'text-[10px]',
            'bg-gray-50/50',
        ] as $class) {
            $this->assertStringNotContainsString(
                $class,
                $source,
                $class.' tidak menghasilkan CSS apa pun di panel ini.',
            );
        }
    }

    /** Rumus penjumlahannya ditulis sekali, dipakai oleh setiap angka. */
    public function test_the_totals_share_one_sum(): void
    {
        $source = $this->resource();

        $this->assertStringContainsString('protected static function sumItems(', $source);
        $this->assertSame(2, substr_count($source, 'static::sumItems($get('."'items'".')'));
    }

    /**
     * Berat diterima yang belum ada tidak ditampilkan sebagai nol.
     *
     * Nol pada dokumen yang belum sampai tujuan lebih menyesatkan daripada
     * tidak ada angka sama sekali -- ia terbaca seolah barangnya sudah tiba
     * dan ternyata kosong.
     */
    public function test_the_received_weight_is_hidden_until_it_exists(): void
    {
        $this->assertStringContainsString(
            "->visible(fn (\$record): bool => \$record?->status === 'Approved'",
            $this->resource(),
        );
    }

    /**
     * Berat diterima tidak boleh bertombol panah.
     *
     * Ia satu-satunya isian yang benar-benar diketik di halaman Approve, dan
     * angkanya menentukan kerugian susut kirim: selisihnya terhadap berat
     * kirim dicatat sebagai Financial Loss. Satu sentuhan panah yang tidak
     * disengaja menggeser angka kerugian tanpa ada yang menyadarinya.
     *
     * Keputusan yang sama sudah diambil untuk berat karkas, berat sapi masuk,
     * pH, dan TOP.
     */
    public function test_the_received_weight_has_no_spinner_arrows(): void
    {
        $source = $this->approvePage();
        $start = strpos($source, "TextInput::make('weight')");

        $this->assertNotFalse($start, 'Isian berat diterima tidak ditemukan.');

        $field = substr($source, $start, 900);

        $this->assertStringNotContainsString('->numeric()', $field);
        $this->assertStringContainsString("'numeric'", $field);
        $this->assertStringContainsString("'inputmode' => 'decimal'", $field);
    }

    /**
     * Berat kirim yang hanya dipajang boleh tetap memakai ->numeric().
     *
     * Ia disabled, jadi tombol panahnya tidak bisa disentuh sama sekali.
     * Dicatat di sini supaya tidak ada yang "merapikannya" tanpa sebab.
     */
    public function test_the_read_only_shipped_weight_is_left_alone(): void
    {
        $source = $this->approvePage();
        $start = strpos($source, "TextInput::make('shipped_weight')");
        $field = substr($source, $start, 300);

        $this->assertStringContainsString('->disabled()', $field);
    }

    /** Pesan di halaman ini memakai kunci Inggris, bukan kalimat Indonesia. */
    public function test_the_messages_use_english_keys(): void
    {
        $source = $this->approvePage();

        $this->assertStringNotContainsString("__('Pilih Barcode Tolakan')", $source);
        $this->assertStringNotContainsString("__('Tidak ada barang yang dipilih')", $source);

        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        $this->assertSame('Pilih Barcode Tolakan', $id['Select Rejected Barcode'] ?? null);
        $this->assertSame('Tidak ada barang yang dipilih', $id['No item selected'] ?? null);
    }
}
