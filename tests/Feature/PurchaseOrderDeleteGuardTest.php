<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Purchase Order yang sudah dibayar tidak boleh dihapus.
 *
 * Uang muka sudah terlanjur keluar ke pemasok. Menghapus PO-nya membuat
 * pembayaran itu menunjuk ke dokumen yang tidak ada lagi, dan uangnya hilang
 * dari jejak tanpa satu pun error.
 *
 * Penjagaannya di MODEL, bukan di tombolnya, supaya berlaku untuk semua jalur
 * penghapusan -- tombol di layar, aksi massal, maupun tinker. Idiomnya
 * mengikuti penjagaan yang sudah lebih dulu ada di Goods Receipt.
 */
class PurchaseOrderDeleteGuardTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function purchaseOrders(): array
    {
        return [
            'PO Daging' => ['PurchaseProduct'],
            'PO Material' => ['PurchaseMaterial'],
        ];
    }

    /**
     * Kedua jenis PO dijaga, dan dijaga di tempat yang sama.
     *
     * @dataProvider purchaseOrders
     */
    public function test_a_purchase_order_with_a_payment_cannot_be_deleted(string $model): void
    {
        $source = file_get_contents(app_path('Models/'.$model.'.php'));

        $this->assertStringContainsString('static::deleting', $source, $model.' tidak punya penjaga hapus.');
        $this->assertStringContainsString('supplierPayments()->exists()', $source, $model);
        $this->assertStringContainsString(
            'This purchase order cannot be deleted because a supplier payment is already recorded against it.',
            $source,
            $model,
        );
    }

    /** Pesannya terdaftar di kedua bahasa. */
    public function test_the_message_is_translated(): void
    {
        $key = 'This purchase order cannot be deleted because a supplier payment is already recorded against it.';

        foreach (['id', 'en'] as $locale) {
            $translations = json_decode(file_get_contents(base_path('lang/'.$locale.'.json')), true);

            $this->assertArrayHasKey($key, $translations, 'lang/'.$locale.'.json');
        }
    }

    /**
     * Goods Receipt yang hutangnya sudah dibayar tidak boleh dibuka kuncinya.
     *
     * Membuka kunci MENGHAPUS hutangnya. Kalau sudah ada uang yang dibayarkan
     * atas hutang itu, pembayarannya jadi menunjuk ke sesuatu yang tidak ada
     * lagi.
     *
     * Penjagaan ini sudah ada sebelumnya dan memang bekerja; yang ditambahkan
     * adalah pemeriksaan STATUS payable, bukan hanya jumlah pembayarannya --
     * baris pembayaran bernilai nol lolos dari penjumlahan sementara statusnya
     * sudah terlanjur berubah.
     */
    public function test_a_paid_goods_receipt_cannot_be_unlocked(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/GoodsReceiptProductResource.php'
        ));

        $this->assertStringContainsString("in_array(\$payable->status, ['partial', 'paid'], true)", $source);
        $this->assertStringContainsString(
            'This Goods Receipt cannot be unlocked because a payment is already recorded against its payable.',
            $source,
        );

        // Pesannya tidak lagi kalimat Indonesia yang ditanam di kode.
        $this->assertStringNotContainsString('Tidak bisa membuka kunci karena', $source);
    }

    /**
     * Penjagaannya membaca kolom, bukan relasi yang tidak ada.
     *
     * `Payable` TIDAK punya relasi `payments()`. Memanggilnya melempar
     * "Call to undefined method", sehingga penjagaan buka-kunci selama ini
     * MELEDAK alih-alih menolak -- dan pesan errornya tidak menjelaskan apa
     * pun kepada pengguna.
     *
     * Ini jenis kegagalan yang paling menipu di proyek ini: penjagaannya
     * terbaca benar, tertulis rapi, dan tidak pernah bekerja.
     */
    public function test_the_unlock_guard_reads_a_column_that_actually_exists(): void
    {
        $this->assertFalse(
            method_exists(\App\Models\Payable::class, 'payments'),
            'Kalau relasi payments() memang ditambahkan, penjagaannya boleh memakainya lagi.',
        );

        foreach ([
            'Filament/Admin/Resources/GoodsReceiptProductResource.php',
            'Filament/Admin/Resources/GoodsReceiptMaterialResource.php',
        ] as $file) {
            $source = file_get_contents(app_path($file));

            $this->assertStringNotContainsString('$payable->payments()', $source, basename($file));
            $this->assertStringContainsString('(float) $payable->paid_amount > 0', $source, basename($file));
        }
    }

    /**
     * Buku kas menampilkan catatan terbaru paling atas.
     *
     * Tanggal saja tidak cukup: beberapa catatan pada HARI YANG SAMA tidak
     * punya urutan yang pasti, sehingga yang barusan dibuat bisa muncul di
     * bawah yang lebih dulu.
     */
    public function test_the_cash_book_breaks_ties_by_id(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CashBookResource.php'));

        $this->assertStringContainsString("->orderBy('transaction_date', 'desc')", $source);
        $this->assertStringContainsString("->orderBy('id', 'desc')", $source);
    }
}
