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

    /**
     * Daftar tolakan dibaca sebagai Produk - Berat - Barcode.
     *
     * Urutan lamanya barcode dulu, sehingga mata harus melewati 26 angka
     * sebelum sampai ke nama produknya. Barcode tetap ditampilkan, di
     * belakang: dua karton produk yang sama dengan berat yang sama tidak bisa
     * dibedakan tanpa itu, dan yang dipindai memang barcode-nya.
     */
    public function test_the_rejection_list_reads_product_first(): void
    {
        $source = $this->approvePage();
        $start = strpos($source, "CheckboxList::make('rejected_barcodes')");

        $this->assertNotFalse($start, 'Daftar tolakan tidak ditemukan.');

        $field = substr($source, $start, 900);

        $this->assertStringContainsString("\$item->product?->name ?? '-'", $field);

        // Nama produk mendahului berat, berat mendahului barcode.
        $posisiProduk = strpos($field, 'product?->name');
        $posisiBerat = strpos($field, 'number_format($item->weight');
        $posisiBarcode = strpos($field, '$item->barcode,');

        $this->assertLessThan($posisiBerat, $posisiProduk, 'Produk harus lebih dulu daripada berat.');
        $this->assertLessThan($posisiBarcode, $posisiBerat, 'Berat harus lebih dulu daripada barcode.');
    }

    /**
     * Relasi produknya dimuat sekaligus.
     *
     * Tanpa itu, membuka modal tolakan menembak satu kueri untuk SETIAP
     * karton -- dan satu tally bisa berisi ratusan.
     */
    public function test_the_rejection_list_loads_products_in_one_query(): void
    {
        $source = $this->approvePage();
        $field = substr($source, strpos($source, "CheckboxList::make('rejected_barcodes')"), 900);

        $this->assertStringContainsString("->with('product')", $field);
    }

    /**
     * Hitungan hasil pindai ditulis di satu tempat.
     *
     * Dulu kalimatnya ada di tiga tempat: isi Placeholder, dan dua
     * pemanggilan $set yang sebenarnya tidak berguna -- isi Placeholder
     * memang dihitung ulang sendiri setiap render. Yang ditinggalkan hanya
     * salinan kalimat yang bisa berbeda dengan aslinya.
     */
    public function test_the_scanned_count_is_written_once(): void
    {
        $source = $this->approvePage();

        $this->assertStringNotContainsString('Total Ter-scan', $source);
        $this->assertSame(1, substr_count($source, ':count box selected'));
        $this->assertStringNotContainsString("\$set('scanned_count_placeholder'", $source);
    }

    /** Satu produk muat dalam satu baris di Receiving Check. */
    public function test_one_product_fits_on_one_row(): void
    {
        $source = $this->approvePage();
        $awal = strpos($source, "Section::make(__('Receiving Check')");
        $bagian = substr($source, $awal, 2600);

        // 4 + 2 + 2 + 4 = 12
        $this->assertStringNotContainsString('->columnSpan(12),', $bagian);
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
