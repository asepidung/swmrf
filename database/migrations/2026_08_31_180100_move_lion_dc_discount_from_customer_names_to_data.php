<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pergantian aturan diskon Lion DC, tanpa mengubah apa pun yang tertagih.
 *
 * Aturan lama ada di dalam kode: InvoiceResource memberi 2% kepada pelanggan
 * yang NAMANYA mengandung `DCA`, `DCB`, atau `DCC`. Aturan itu dicabut dan
 * digantikan kolom `customers.default_discount`.
 *
 * Mencabutnya begitu saja akan mengubah tagihan, karena diskonnya dulu tidak
 * pernah ikut tersimpan di Sales Order -- ia ditempelkan belakangan saat
 * invoice dibuat. Migrasi ini memindahkan keadaan yang sedang berlaku ke
 * dalam data, sehingga hasil tagihannya persis sama sebelum dan sesudah.
 *
 * DUA HAL YANG SENGAJA TIDAK DISENTUH:
 *
 *  - **Invoice yang sudah terbit.** Ia menyimpan `discount_percent` dan
 *    `discount_rp` sendiri di `invoice_items`, jadi angkanya sudah beku dan
 *    tidak ikut berubah oleh apa pun di sini.
 *  - **SO yang sudah diinvoice.** Mengubah diskonnya hanya akan menulis ulang
 *    riwayat tanpa mengubah satu rupiah pun yang tertagih.
 *
 * Yang diperbaiki hanyalah SO yang BELUM diinvoice -- justru rombongan inilah
 * yang akan tertagih kurang 2% bila aturan lamanya dicabut tanpa persiapan.
 *
 * Pencocokan nama dipakai sekali terakhir di sini, memang disengaja: inilah
 * satu-satunya keterangan yang tersedia tentang siapa saja yang selama ini
 * menerima diskon. Sesudah ini, yang menentukan adalah kolomnya.
 */
return new class extends Migration
{
    private const DISCOUNT = 2.00;

    /** @var array<int, string> */
    private const LEGACY_NAME_FRAGMENTS = ['DCA', 'DCB', 'DCC'];

    public function up(): void
    {
        $customerIds = $this->customersMatchedByTheOldRule();

        if ($customerIds === []) {
            return;
        }

        DB::table('customers')
            ->whereIn('id', $customerIds)
            ->update(['default_discount' => self::DISCOUNT]);

        // Hanya SO yang belum punya invoice sama sekali.
        $openSalesOrderIds = DB::table('sales_orders')
            ->whereIn('customer_id', $customerIds)
            ->whereNotIn('id', DB::table('invoices')->select('sales_order_id'))
            ->pluck('id')
            ->all();

        if ($openSalesOrderIds === []) {
            return;
        }

        DB::table('sales_order_items')
            ->whereIn('sales_order_id', $openSalesOrderIds)
            ->update(['discount' => self::DISCOUNT]);
    }

    /**
     * Tidak bisa dibalikkan dengan jujur.
     *
     * Sesudah kolomnya diisi, tidak ada lagi cara membedakan diskon yang
     * berasal dari migrasi ini dengan diskon yang kemudian diubah orang.
     * Mengosongkannya kembali berarti menghapus pekerjaan orang lain, jadi
     * lebih baik tidak melakukan apa-apa daripada menebak.
     */
    public function down(): void
    {
        // Sengaja dibiarkan kosong.
    }

    /** @return array<int, int> */
    private function customersMatchedByTheOldRule(): array
    {
        $query = DB::table('customers');

        foreach (self::LEGACY_NAME_FRAGMENTS as $fragment) {
            $query->orWhere('name', 'like', '%'.$fragment.'%');
        }

        return $query->pluck('id')->all();
    }
};
