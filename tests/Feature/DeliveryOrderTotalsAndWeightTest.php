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
     * Daftar tolakan bisa dipakai walau isinya ratusan karton.
     *
     * Satu tally bisa berisi ratusan karton, dan daftar centang polos tidak
     * terpakai untuk jumlah segitu: barcode 26 karakter membuat barisnya
     * melipat, dan tidak ada cara mencari.
     */
    public function test_the_rejection_list_survives_hundreds_of_boxes(): void
    {
        $source = $this->approvePage();
        $start = strpos($source, "CheckboxList::make('rejected_barcodes')");

        $this->assertNotFalse($start, 'Daftar tolakan tidak ditemukan.');

        $field = substr($source, $start, 700);

        // Satu-satunya cara masuk akal menemukan satu karton di antara ratusan.
        $this->assertStringContainsString('->searchable()', $field);

        // Menolak seluruh kiriman tanpa menekan ratusan kotak.
        $this->assertStringContainsString('->bulkToggleable()', $field);

        // Tingginya dibatasi lewat style langsung, bukan kelas Tailwind:
        // panel ini tidak memuat hasil build CSS aplikasi.
        $this->assertStringContainsString('overflow-y: auto', $field);
    }

    /**
     * Produk dan berat menjadi label, barcode menjadi keterangan.
     *
     * Urutan lamanya barcode dulu, sehingga mata harus melewati 26 angka
     * sebelum sampai ke nama produknya. Sebagai keterangan, Filament
     * merender barcode dengan huruf lebih kecil dan redup, sehingga
     * barisnya rata dan yang terbaca lebih dulu adalah produknya.
     */
    public function test_the_product_is_the_label_and_the_barcode_is_the_description(): void
    {
        $source = $this->approvePage();

        $this->assertStringContainsString('protected static function rejectionOptions(', $source);
        $this->assertStringContainsString("'labels' => \$labels", $source);
        $this->assertStringContainsString("'barcodes' => \$barcodes", $source);

        $field = substr($source, strpos($source, "CheckboxList::make('rejected_barcodes')"), 700);

        $this->assertStringContainsString('->descriptions(', $field);
    }

    /**
     * Label dan keterangannya berasal dari satu kueri yang sama.
     *
     * Kalau masing-masing mengambil sendiri, daftar ratusan karton itu
     * dibaca dua kali setiap kali modalnya digambar ulang -- dan
     * CheckboxList menggambar ulang setiap kali satu kotak dicentang.
     */
    public function test_the_rejection_list_reads_the_boxes_once(): void
    {
        $source = $this->approvePage();
        $awal = strpos($source, 'protected static function rejectionOptions(');
        $badan = substr($source, $awal, 1400);

        $this->assertStringContainsString("->with('product')", $badan);
        $this->assertSame(1, substr_count($badan, '->get()'));
        $this->assertStringContainsString('static $cache', $badan);
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
