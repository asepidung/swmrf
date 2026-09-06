<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Angka untuk dua laporan penjualan, di satu tempat.
 *
 * Halaman laporannya hanya menggambar. Seluruh aturan "apa yang dihitung"
 * ada di sini supaya bisa diuji tanpa membuka layar, dan supaya laporan
 * kedua tidak menghitung hal yang sama dengan cara yang sedikit berbeda.
 */
class LaporanPenjualan
{
    /**
     * Retur yang mengurangi tagihan hanya yang SUDAH DISETUJUI.
     *
     * Sama persis dengan `Invoice::returnedAmount()`. Retur yang masih Draft
     * belum mengurangi apa pun -- barangnya bisa saja tidak jadi kembali.
     */
    private const RETUR_DIAKUI = 'Approved';

    /**
     * Total penjualan per bulan dalam satu tahun, dalam rupiah.
     *
     * Yang dijumlahkan `billedAmount()`: berapa yang benar-benar DITAGIHKAN
     * kepada pelanggan -- subtotal ditambah biaya lain, dikurangi uang muka
     * dan dikurangi retur yang sudah disetujui.
     *
     * Rumusnya ditulis sebagai SQL supaya satu tahun cukup SATU query, bukan
     * satu query per invoice -- `Invoice::returnedAmount()` menembak basis
     * data setiap kali dipanggil. Bahwa SQL itu menjawab sama dengan
     * `Invoice::billedAmount()` BUKAN diandalkan dari kemiripan bentuknya:
     * ada uji yang membandingkan keduanya invoice per invoice, dan uji itulah
     * yang menahan keduanya tetap satu jawaban saat salah satunya diubah.
     *
     * Pengelompokan per bulannya dikerjakan di PHP, bukan `GROUP BY MONTH()`.
     * `MONTH()` milik MySQL; SQLite -- yang dipakai seluruh rangkaian uji --
     * tidak mengenalnya, dan query yang hanya bisa dijalankan di satu mesin
     * berarti angkanya tidak pernah benar-benar diuji sebelum sampai ke
     * pengguna.
     *
     * @return array<int, float> kunci 1..12, selalu lengkap dua belas bulan
     */
    public static function totalPerBulan(int $tahun): array
    {
        $hasil = array_fill(1, 12, 0.0);

        $baris = Invoice::query()
            ->select('invoices.invoice_date')
            ->selectRaw(
                'invoices.subtotal + invoices.charge - invoices.down_payment '
                .'- COALESCE(retur.kredit, 0) as ditagih'
            )
            ->leftJoinSub(
                static::kreditRetur(),
                'retur',
                'retur.invoice_id',
                '=',
                'invoices.id',
            )
            ->whereYear('invoices.invoice_date', $tahun)
            ->get();

        foreach ($baris as $satu) {
            $bulan = (int) \Illuminate\Support\Carbon::parse($satu->invoice_date)->format('n');

            $hasil[$bulan] += (float) $satu->ditagih;
        }

        return array_map(fn (float $satu): float => round($satu, 2), $hasil);
    }

    /**
     * Kredit retur per invoice, sebagai query yang bisa disambung.
     *
     * `sales_returns` memakai hapus lunak, dan retur yang dihapus tidak boleh
     * ikut mengurangi tagihan.
     */
    private static function kreditRetur(): \Illuminate\Database\Query\Builder
    {
        return DB::table('sales_return_items')
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->where('sales_returns.status', self::RETUR_DIAKUI)
            ->whereNull('sales_returns.deleted_at')
            ->groupBy('sales_return_items.invoice_id')
            ->select('sales_return_items.invoice_id')
            ->selectRaw('SUM(sales_return_items.line_amount) as kredit');
    }

    /**
     * Tahun-tahun yang benar-benar punya invoice, terbaru lebih dulu.
     *
     * Tahun berjalan selalu ikut walaupun belum ada invoicenya sama sekali --
     * kalau tidak, laporan di awal Januari akan terbuka pada tahun lalu tanpa
     * penjelasan apa pun.
     *
     * @return list<int>
     */
    public static function tahunYangAdaDatanya(): array
    {
        // Tahunnya diambil di PHP, bukan `YEAR()` -- alasannya sama dengan
        // `totalPerBulan()`: fungsi itu milik MySQL saja.
        $tahun = Invoice::query()
            ->whereNotNull('invoice_date')
            ->distinct()
            ->pluck('invoice_date')
            ->map(fn ($satu): int => (int) \Illuminate\Support\Carbon::parse($satu)->format('Y'))
            ->all();

        $tahun[] = (int) now()->format('Y');

        $tahun = array_values(array_unique(array_filter($tahun)));
        rsort($tahun);

        return $tahun;
    }

    /**
     * Produk yang paling SERING dipesan, per kategori.
     *
     * Keputusan Owner, 6 September 2026: "paling sering di pesan, bisa dilihat
     * dari delivery order atau sales order, tapi lebih baik sales order
     * walaupun qty nya ditampilkan tapi itu bukan jadi acuan".
     *
     * Jadi yang mengurutkan FREKUENSI -- berapa banyak sales order berbeda
     * yang memuat produk itu -- bukan beratnya. Beratnya tetap ditampilkan
     * sebagai keterangan, karena dua produk dengan frekuensi sama tetapi
     * berat jauh berbeda menceritakan hal yang berbeda.
     *
     * Aplikasi lama menghitungnya dari surat jalan (`dodetail`), bukan sales
     * order. Angkanya karena itu TIDAK akan sama persis dengan laporan lama:
     * satu sales order bisa dikirim beberapa kali, dan pesanan yang batal
     * sebelum dikirim tidak pernah muncul di sana sama sekali.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function seringDipesan(
        string $dari,
        string $sampai,
        ?int $kategoriId,
        int $berapa,
    ): \Illuminate\Support\Collection {
        return DB::table('sales_order_items')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->join('products', 'products.id', '=', 'sales_order_items.product_id')
            ->whereNull('sales_orders.deleted_at')
            // Pesanan yang dibatalkan tidak pernah menjadi permintaan.
            // Dua ejaannya sama-sama ada di basis data warisan, dan keduanya
            // memang harus dibuang -- lihat `DeliveryPlan`.
            ->whereNotIn('sales_orders.status', ['cancelled', 'canceled'])
            ->whereBetween('sales_orders.delivery_date', [$dari, $sampai])
            ->when($kategoriId, fn ($q) => $q->where('products.category_id', $kategoriId))
            ->groupBy('products.id', 'products.code', 'products.name')
            ->select('products.id', 'products.code', 'products.name')
            ->selectRaw('COUNT(DISTINCT sales_orders.id) as frekuensi')
            ->selectRaw('COALESCE(SUM(sales_order_items.weight), 0) as berat')
            ->orderByDesc('frekuensi')
            ->orderByDesc('berat')
            ->limit($berapa)
            ->get();
    }

    /**
     * Tanggal pengiriman terakhir yang tercatat, sebagai batas akhir bawaan.
     *
     * Aplikasi lama memakai `MAX(deliverydate)`, bukan hari ini. Bedanya
     * terasa pada sistem yang datanya belum berjalan setiap hari: batas
     * "hari ini" membuat laporannya kosong dan terbaca seolah tidak ada
     * pesanan sama sekali.
     */
    public static function tanggalPesananTerakhir(): string
    {
        $tanggal = DB::table('sales_orders')
            ->whereNull('deleted_at')
            ->max('delivery_date');

        return $tanggal ? \Illuminate\Support\Carbon::parse($tanggal)->toDateString() : now()->toDateString();
    }
}
