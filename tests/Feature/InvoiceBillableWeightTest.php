<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use Tests\TestCase;

/**
 * Invoice menagih berat pada DO Receipt APA ADANYA.
 *
 * Keputusan bisnis Project Owner, 1 September 2026, dan sengaja dicatat di
 * sini karena tampak "salah" bila hanya membaca kodenya.
 *
 * Aturan pelanggan memang berbunyi: berat lebih ditagih sesuai PO. Tetapi
 * penyesuaiannya dikerjakan MANUSIA di DO Receipt, bukan oleh rumus di
 * invoice. Barang datang 52 kg untuk PO 50 kg, petugas menurunkan angkanya
 * menjadi 50 di DO Receipt, dan selisih 2 kg tercatat sebagai Financial Loss
 * lewat perbandingan berat kirim lawan berat terima yang sudah ada.
 *
 * Membatasinya otomatis di invoice -- `min(diterima, PO)` -- pernah dipasang
 * dan langsung dicabut lagi, karena merusak dua hal sekaligus: penyesuaian
 * yang seharusnya terlihat menjadi tersembunyi, dan kerugian 2 kg itu tidak
 * pernah tercatat sama sekali. JANGAN dipasang lagi.
 */
class InvoiceBillableWeightTest extends TestCase
{
    private function invoiceSource(): string
    {
        return file_get_contents(app_path('Filament/Admin/Resources/InvoiceResource.php'));
    }

    /**
     * Perhitungannya memakai berat resi tanpa pembanding apa pun.
     *
     * Dulu penjagaan ini menghitung EMPAT salinan rumus yang harus seragam.
     * Salinannya sudah dihapus -- rumusnya hidup di satu tempat, dan itulah
     * yang membuat keseragamannya tidak lagi perlu dijaga dengan menghitung.
     */
    public function test_the_calculation_bills_the_receipt_weight_as_it_stands(): void
    {
        $source = $this->invoiceSource();

        $this->assertSame(
            1,
            substr_count($source, 'InvoiceTotals::line((float) $item->weight, $price, $discount)'),
            'Berat resi dipakai apa adanya, di satu tempat saja.',
        );
    }

    /**
     * Pembatasan otomatis tidak boleh kembali.
     *
     * Penyesuaian berat adalah keputusan manusia di DO Receipt, dan di situlah
     * selisihnya menjadi Financial Loss. Rumus yang diam-diam memangkas
     * angkanya menghapus keduanya.
     */
    public function test_no_automatic_cap_is_reintroduced(): void
    {
        $source = $this->invoiceSource();

        $this->assertStringNotContainsString('billableWeight', $source);
        $this->assertStringNotContainsString('min($received', $source);
        $this->assertStringNotContainsString('$soItem->weight)', $source);
    }

    /** Alasannya ditinggal di kode, supaya tidak "diperbaiki" lagi. */
    public function test_the_reason_is_written_down_where_it_matters(): void
    {
        $this->assertStringContainsString(
            'penyesuaiannya dilakukan MANUSIA di',
            $this->invoiceSource(),
        );
    }

    /**
     * Selisih berat kirim lawan berat terima tetap menjadi Financial Loss.
     *
     * Inilah yang menangkap penyesuaian 52 menjadi 50 tadi. Kalau bagian ini
     * hilang, penurunan angkanya tidak meninggalkan jejak apa pun.
     */
    public function test_the_shipping_shrinkage_is_still_recorded(): void
    {
        $approve = file_get_contents(app_path(
            'Filament/Admin/Resources/DeliveryOrderResource/Pages/ApproveDeliveryOrder.php'
        ));

        $this->assertStringContainsString('if ($receivedWeight < $shippedWeight)', $approve);
        $this->assertStringContainsString('financialLoss()->updateOrCreate', $approve);
    }

    /**
     * Awalan nomor DO dan nomor resi berasal dari satu tempat.
     *
     * Nomor resi diturunkan dari nomor DO dengan mengganti awalannya. Dulu
     * awalannya diketik ulang sebagai teks di halaman Approve; kalau awalan di
     * model diubah dan salinan itu tertinggal, penggantiannya tidak menemukan
     * apa pun dan nomor resi menjadi SAMA PERSIS dengan nomor DO.
     *
     * Tidak ada yang menahannya: index unique pada `receipt_number` sudah
     * dilepas pada migrasi 1 Juli 2026.
     */
    public function test_the_document_prefixes_have_one_home(): void
    {
        $this->assertSame('SWM-DO#', DeliveryOrder::NUMBER_PREFIX);
        $this->assertSame('SWM-REC#', DeliveryOrder::RECEIPT_NUMBER_PREFIX);

        $approve = file_get_contents(app_path(
            'Filament/Admin/Resources/DeliveryOrderResource/Pages/ApproveDeliveryOrder.php'
        ));

        $this->assertStringContainsString('DeliveryOrder::NUMBER_PREFIX', $approve);
        $this->assertStringContainsString('DeliveryOrder::RECEIPT_NUMBER_PREFIX', $approve);
        $this->assertStringNotContainsString("'SWM-DO#'", $approve);
        $this->assertStringNotContainsString("'SWM-REC#'", $approve);

        $model = file_get_contents(app_path('Models/DeliveryOrder.php'));

        $this->assertSame(1, substr_count($model, "'SWM-DO#'"));
        $this->assertStringContainsString('self::NUMBER_PREFIX', $model);
    }

    /** Penggantian awalannya menghasilkan nomor resi yang benar. */
    public function test_the_receipt_number_mirrors_the_delivery_order_number(): void
    {
        $this->assertSame(
            'SWM-REC#260001',
            str_replace(
                DeliveryOrder::NUMBER_PREFIX,
                DeliveryOrder::RECEIPT_NUMBER_PREFIX,
                DeliveryOrder::NUMBER_PREFIX.'260001',
            ),
        );
    }
}
