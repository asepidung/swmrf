<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Gudang harus DIPILIH, dan pH tidak boleh bertombol panah.
 *
 * Keduanya di alur pembuatan label Goods Receipt daging, dan keduanya jenis
 * kesalahan yang tidak menimbulkan error sama sekali.
 */
class GoodsReceiptWarehouseAndPhTest extends TestCase
{
    private function labelingPage(): string
    {
        return file_get_contents(app_path(
            'Filament/Admin/Resources/GoodsReceiptProductResource/Pages/LabelingGoodsReceiptProduct.php'
        ));
    }

    private function scanPage(): string
    {
        return file_get_contents(app_path(
            'Filament/Admin/Resources/GoodsReceiptProductResource/Pages/ScanGoodsReceiptProduct.php'
        ));
    }

    /**
     * Tidak ada gudang yang terpilih sendiri.
     *
     * Cadangannya dulu angka 1, yang berarti Jonggol. Gudang itu terpilih
     * sebelum pengguna memilih apa pun, sehingga barang bisa tercatat masuk
     * ke gudang yang salah tanpa satu pun gejala -- dan pada halaman label,
     * label pertama hari itu tercetak untuk gudang yang keliru.
     *
     * Sesudah dipilih sekali, sesi yang mengingatnya. Yang dibuang hanya
     * tebakan awalnya.
     */
    public function test_no_warehouse_is_chosen_on_the_users_behalf(): void
    {
        foreach ([$this->labelingPage(), $this->scanPage()] as $source) {
            $this->assertStringNotContainsString("session('gr_warehouse_id', 1)", $source);
            $this->assertStringContainsString("session('gr_warehouse_id')", $source);
        }
    }

    /**
     * Pemindaian ditahan sampai gudangnya dipilih.
     *
     * Tanpa penjagaan ini, membuang tebakan awalnya justru lebih buruk:
     * stoknya akan tercatat tanpa gudang sama sekali.
     */
    public function test_scanning_waits_for_a_warehouse(): void
    {
        $source = $this->scanPage();

        $this->assertStringContainsString('if (! $this->warehouse_id) {', $source);
        $this->assertStringContainsString("__('Choose a warehouse first')", $source);

        // Penjagaannya harus SEBELUM barcode diproses, bukan sesudah.
        $posisiPenjaga = strpos($source, 'if (! $this->warehouse_id) {');
        $posisiBarcode = strpos($source, '$barcode = trim($this->barcode);');

        $this->assertLessThan($posisiBarcode, $posisiPenjaga);
    }

    /**
     * pH tidak boleh berupa input bertombol panah.
     *
     * Rentangnya cuma 5,4 sampai 5,7 dengan langkah 0,1, jadi satu sentuhan
     * panah menggeser nilainya tanpa terasa. pH ikut masuk ke barcode,
     * sehingga digit yang salah berarti barcode yang salah arti -- dan
     * barcode itu terbawa ke seluruh modul sesudahnya.
     *
     * Keputusan yang sama sudah diambil untuk pH di modul Boning; halaman ini
     * tertinggal.
     */
    public function test_the_ph_field_has_no_spinner_arrows(): void
    {
        $source = $this->labelingPage();
        $field = substr($source, strpos($source, "TextInput::make('ph_level')"), 1200);

        $this->assertStringNotContainsString('->numeric()', $field);
        $this->assertStringNotContainsString('->step(', $field);
        $this->assertStringNotContainsString('->minValue(', $field);
        $this->assertStringNotContainsString('->maxValue(', $field);

        // Batasnya tetap terjaga, hanya caranya yang berubah.
        $this->assertStringContainsString("'min:5.4'", $field);
        $this->assertStringContainsString("'max:5.7'", $field);
    }

    /**
     * Modal "barang kurang dari pesanan" tidak lagi mengundang penutupan.
     *
     * Tombol yang MENUTUP PO dulu berwarna hijau, sehingga terbaca sebagai
     * pilihan yang aman dan dianjurkan. Padahal justru itu yang menutup
     * pintu: sisa pesanan tidak akan diterima lagi.
     */
    public function test_closing_a_purchase_order_does_not_look_like_the_safe_choice(): void
    {
        $view = file_get_contents(resource_path(
            'views/filament/admin/resources/goods-receipt-material-resource/pages/create-goods-receipt-material.blade.php'
        ));

        $this->assertStringContainsString('wire:click="forceCompleted" color="warning"', $view);
        $this->assertStringNotContainsString('wire:click="forceCompleted" color="success"', $view);

        // Teksnya dulu ditulis langsung tanpa __(), jadi tetap berbahasa
        // Inggris apa pun bahasa yang dipilih.
        $this->assertStringNotContainsString('PO Quantity Not Fulfilled', $view);
        $this->assertStringContainsString("__('Received quantity is less than ordered')", $view);

        // Konsekuensinya disebutkan, bukan cuma ditanyakan.
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);
        $this->assertStringContainsString(
            'tidak akan pernah diterima',
            $id['Is the rest still coming in a later delivery? Closing the purchase order means the remaining quantity will never be received.'] ?? '',
        );
    }
}
