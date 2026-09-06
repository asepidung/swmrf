<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\FastMovingProducts;
use App\Filament\Admin\Pages\SalesReport;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Support\LaporanPenjualan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dua laporan penjualan: total per bulan, dan produk yang paling sering
 * dipesan.
 *
 * Keduanya meniru laporan yang sudah dipakai bertahun-tahun di aplikasi lama
 * (`reports/sales.php` dan `reports/fast_moving.php`), dengan dua perbedaan
 * yang disengaja dan dicatat:
 *
 *  - Fast Moving dihitung dari SALES ORDER, bukan surat jalan. Permintaan
 *    Owner: "lebih baik sales order". Satu sales order bisa dikirim beberapa
 *    kali, dan pesanan yang batal sebelum dikirim tidak pernah muncul di
 *    surat jalan sama sekali -- jadi angkanya memang tidak akan sama persis
 *    dengan laporan lama.
 *  - Sales Report menjumlah `billedAmount()`, bukan satu kolom `xamount`
 *    seperti dulu. Aplikasi ini tidak menyimpan grand total; ia menghitungnya
 *    dari penyusunnya, dan retur yang sudah disetujui mengurangi.
 */
class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $segmen = \App\Models\CustomerSegment::create(['name' => 'UMUM', 'is_active' => true]);

        $this->pelanggan = Customer::create([
            'name' => 'TOKO SEJAHTERA',
            'customer_segment_id' => $segmen->id,
            'address' => 'Bogor',
            'pic' => 'Budi',
            'phone' => '0812345678',
            'top' => 30,
            'invoice_exchange' => false,
            'is_taxable' => false,
        ]);
    }

    private function invoice(string $tanggal, float $subtotal, float $charge = 0, float $dp = 0): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-'.str_pad((string) (Invoice::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'sales_order_id' => $this->salesOrderKosong()->id,
            'customer_id' => $this->pelanggan->id,
            'invoice_date' => $tanggal,
            'due_date' => $tanggal,
            'subtotal' => $subtotal,
            'charge' => $charge,
            'down_payment' => $dp,
            'status' => Invoice::STATUS_UNPAID,
        ]);
    }

    /** Invoice di aplikasi ini selalu lahir dari sebuah sales order. */
    private function salesOrderKosong(): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => 'SO-INV-'.str_pad((string) (SalesOrder::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->pelanggan->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
    }

    // =====================================================================
    // Sales Report
    // =====================================================================

    /**
     * Angkanya SAMA dengan `Invoice::billedAmount()`, invoice per invoice.
     *
     * Inilah uji yang paling penting di berkas ini. Laporannya menjumlah
     * lewat SQL supaya satu tahun cukup satu query, sementara sisa aplikasi
     * memakai `billedAmount()` yang menghitung di PHP. Dua jalan menuju
     * angka yang sama adalah dua tempat yang bisa berbeda -- dan kalau
     * berbeda, yang salah tidak akan ketahuan dari layar mana pun: keduanya
     * menampilkan angka yang tampak masuk akal.
     *
     * Uji ini yang menahan keduanya tetap satu jawaban.
     */
    public function test_the_monthly_total_matches_what_each_invoice_says_it_billed(): void
    {
        $this->invoice('2026-03-05', 10_000_000, charge: 250_000, dp: 1_000_000);
        $this->invoice('2026-03-20', 4_000_000);
        $this->invoice('2026-07-01', 7_500_000, dp: 500_000);

        $bulanan = LaporanPenjualan::totalPerBulan(2026);

        foreach ([3, 7] as $bulan) {
            $dariModel = Invoice::query()
                ->whereYear('invoice_date', 2026)
                ->whereMonth('invoice_date', $bulan)
                ->get()
                ->sum(fn (Invoice $satu): float => $satu->billedAmount());

            $this->assertEqualsWithDelta(
                $dariModel,
                $bulanan[$bulan],
                0.01,
                "Laporan bulan $bulan tidak sama dengan jumlah billedAmount() invoicenya sendiri.",
            );
        }
    }

    /** Dua belas bulan selalu ada, walaupun tidak ada invoicenya. */
    public function test_every_month_is_present_even_when_empty(): void
    {
        $bulanan = LaporanPenjualan::totalPerBulan(2026);

        $this->assertSame(range(1, 12), array_keys($bulanan));
        $this->assertEqualsWithDelta(0.0, $bulanan[11], 0.01);
    }

    /**
     * Invoice yang dihapus tidak ikut dihitung.
     *
     * Hapus lunak, jadi barisnya masih ada di tabel -- dan justru itu yang
     * membuatnya berbahaya: query yang lupa menyaringnya tetap berjalan
     * mulus dan menghasilkan angka yang terlalu besar.
     */
    public function test_a_deleted_invoice_is_not_counted(): void
    {
        $satu = $this->invoice('2026-05-10', 5_000_000);
        $this->invoice('2026-05-11', 3_000_000);

        $satu->delete();

        $this->assertEqualsWithDelta(3_000_000.0, LaporanPenjualan::totalPerBulan(2026)[5], 0.01);
    }

    /** Tahun berjalan selalu bisa dipilih, walaupun belum ada invoicenya. */
    public function test_the_current_year_can_always_be_chosen(): void
    {
        $this->assertContains((int) now()->format('Y'), LaporanPenjualan::tahunYangAdaDatanya());
    }

    /**
     * Bulan yang BELUM terjadi ditulis kosong, bukan nol.
     *
     * Nol berarti "tidak ada penjualan". Bulan yang belum datang bukan itu,
     * dan menuliskannya nol membuat garis tahun berjalan terjun ke dasar --
     * terbaca seolah penjualannya berhenti.
     */
    public function test_months_that_have_not_happened_yet_are_left_blank(): void
    {
        $data = Livewire::actingAs($this->user)
            ->test(SalesReport::class)
            ->viewData('bulanan');

        $bulanIni = (int) now()->format('n');

        if ($bulanIni < 12) {
            $this->assertNull($data[12], 'Bulan yang belum terjadi ditulis nol, bukan dikosongkan.');
        }

        $this->assertNotNull($data[$bulanIni]);
    }

    /** Halamannya terbuka. */
    public function test_the_sales_report_page_renders(): void
    {
        Livewire::actingAs($this->user)
            ->test(SalesReport::class)
            ->assertOk();
    }

    // =====================================================================
    // Fast Moving Products
    // =====================================================================

    private function pesan(string $tanggal, Product $produk, float $berat, string $status = 'waiting'): SalesOrder
    {
        $so = SalesOrder::create([
            'so_number' => 'SO-'.str_pad((string) (SalesOrder::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->pelanggan->id,
            'delivery_date' => $tanggal,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'product_id' => $produk->id,
            'weight' => $berat,
            'price' => 100_000,
        ]);

        return $so;
    }

    private function produk(string $kode, ProductCategory $kategori): Product
    {
        return Product::create([
            'name' => 'PRODUK '.$kode,
            'code' => $kode,
            'category_id' => $kategori->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    /**
     * Yang mengurutkan FREKUENSI, bukan berat.
     *
     * Keputusan Owner: "paling sering di pesan ... walaupun qty nya
     * ditampilkan tapi itu bukan jadi acuan". Ujinya sengaja dibuat supaya
     * kedua ukuran itu BERTENTANGAN -- satu produk dipesan tiga kali dengan
     * berat kecil, satunya sekali dengan berat besar. Kalau yang berat naik
     * ke atas, urutannya memakai ukuran yang salah.
     */
    public function test_the_ranking_is_by_frequency_not_by_weight(): void
    {
        $kategori = ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => '1', 'is_active' => true]);

        $sering = $this->produk('SRG', $kategori);
        $berat = $this->produk('BRT', $kategori);

        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $tanggal) {
            $this->pesan($tanggal, $sering, 5);
        }

        $this->pesan('2026-09-04', $berat, 500);

        $baris = LaporanPenjualan::seringDipesan('2026-09-01', '2026-09-30', $kategori->id, 10);

        $this->assertSame('SRG', $baris->first()->code, 'Urutannya memakai berat, bukan frekuensi.');
        $this->assertSame(3, (int) $baris->first()->frekuensi);

        // Beratnya tetap ikut ditampilkan sebagai keterangan.
        $this->assertEqualsWithDelta(15.0, (float) $baris->first()->berat, 0.01);
    }

    /** Pesanan yang dibatalkan tidak pernah menjadi permintaan. */
    public function test_a_cancelled_order_does_not_count_as_demand(): void
    {
        $kategori = ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => '1', 'is_active' => true]);
        $produk = $this->produk('AAA', $kategori);

        $this->pesan('2026-09-01', $produk, 10);
        $this->pesan('2026-09-02', $produk, 10, status: 'cancelled');

        // Ejaan kedua yang ikut ada di basis data warisan.
        $this->pesan('2026-09-03', $produk, 10, status: 'canceled');

        $baris = LaporanPenjualan::seringDipesan('2026-09-01', '2026-09-30', $kategori->id, 10);

        $this->assertSame(1, (int) $baris->first()->frekuensi);
    }

    /** Pesanan di luar rentang tanggal tidak ikut. */
    public function test_orders_outside_the_range_are_left_out(): void
    {
        $kategori = ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => '1', 'is_active' => true]);
        $produk = $this->produk('AAA', $kategori);

        $this->pesan('2026-08-31', $produk, 10);
        $this->pesan('2026-09-05', $produk, 10);

        $baris = LaporanPenjualan::seringDipesan('2026-09-01', '2026-09-30', $kategori->id, 10);

        $this->assertSame(1, (int) $baris->first()->frekuensi);
    }

    /**
     * Kategori lain tidak ikut masuk daftar.
     *
     * Membandingkan di dalam satu kategori itu inti laporannya: tulang dan
     * prime cut tidak pernah bersaing memperebutkan tempat yang sama di
     * gudang, jadi mengurutkannya dalam satu daftar tidak menjawab apa pun.
     */
    public function test_another_category_does_not_enter_the_list(): void
    {
        $prime = ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => '1', 'is_active' => true]);
        $lain = ProductCategory::create(['name' => 'CURUT', 'prefix' => '2', 'is_active' => true]);

        $this->pesan('2026-09-01', $this->produk('AAA', $prime), 10);
        $this->pesan('2026-09-02', $this->produk('BBB', $lain), 10);

        $baris = LaporanPenjualan::seringDipesan('2026-09-01', '2026-09-30', $prime->id, 10);

        $this->assertCount(1, $baris);
        $this->assertSame('AAA', $baris->first()->code);
    }

    /** Halamannya terbuka. */
    public function test_the_fast_moving_page_renders(): void
    {
        ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => '1', 'is_active' => true]);

        Livewire::actingAs($this->user)
            ->test(FastMovingProducts::class)
            ->assertOk();
    }

    // =====================================================================
    // Hak akses
    // =====================================================================

    /**
     * Keduanya dijaga izin.
     *
     * Halaman laporan tidak punya policy karena tidak punya model sendiri --
     * dan Laravel MENGIZINKAN segalanya untuk yang tidak punya policy. Jadi
     * penjagaannya harus ditulis di halamannya, dan keberadaannya diperiksa
     * di sini.
     *
     * @dataProvider halamanLaporan
     */
    public function test_the_report_page_is_gated_by_a_permission(string $halaman, string $izin): void
    {
        $sumber = file_get_contents(
            app_path('Filament/Admin/Pages/'.class_basename($halaman).'.php')
        );

        $this->assertStringContainsString('canAccess', $sumber);
        $this->assertStringContainsString($izin, $sumber);

        $pegawai = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $this->actingAs($pegawai);
        $this->assertFalse($halaman::canAccess(), "Halaman $halaman terbuka untuk yang tidak punya izin.");
    }

    /** @return array<string, array{string, string}> */
    public static function halamanLaporan(): array
    {
        return [
            'Sales Report' => [SalesReport::class, 'view_sales_report'],
            'Fast Moving Products' => [FastMovingProducts::class, 'view_fast_moving_products'],
        ];
    }

    /** Izinnya benar-benar ada, bukan hanya disebut. */
    public function test_both_permissions_exist(): void
    {
        foreach (['view_sales_report', 'view_fast_moving_products'] as $izin) {
            $this->assertTrue(
                \App\Models\Permission::where('name', $izin)->exists(),
                "Izin $izin tidak pernah dibuat, jadi menunya tidak akan muncul untuk siapa pun.",
            );
        }
    }
}
