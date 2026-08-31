<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Diskon berasal dari data pelanggan, bukan dari potongan namanya.
 *
 * Sebelumnya `InvoiceResource` memberi 2% kepada pelanggan yang NAMANYA
 * mengandung `DCA`, `DCB`, atau `DCC`, di empat tempat terpisah. Aturannya
 * benar secara bisnis -- tiga Distribution Center Lion Superindo memang
 * disepakati mendapat diskon itu -- tetapi tempatnya keliru.
 *
 * Dua akibatnya, dan keduanya senyap:
 *
 *  - Penggantiannya terjadi saat INVOICE dibuat, bukan saat SO dibuat, jadi
 *    SO tertulis 0% sementara invoice menagih 2%. Dokumen yang dipegang
 *    pelanggan tidak cocok dengan tagihan yang dikirim.
 *  - Mengganti nama pelanggan diam-diam mengubah harganya, dan pelanggan
 *    baru yang namanya kebetulan memuat huruf itu ikut mendapat diskon.
 *
 * Diskonnya sekarang ada di `customers.default_discount`. Bukan di grup:
 * grup LION berisi 29 pelanggan dan hanya tiga DC-nya yang berhak. Bukan
 * pula di segment, yang berlaku lintas perusahaan sehingga DC milik
 * pelanggan lain ikut kena.
 */
class CustomerDefaultDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $name, float $discount = 0): Customer
    {
        return Customer::create([
            'name' => $name,
            'customer_group_id' => CustomerGroup::firstOrCreate(['name' => 'LION'])->id,
            'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id,
            'address' => 'Jalan Uji',
            'top' => 30,
            'default_discount' => $discount,
            'invoice_exchange' => false,
            'is_active' => true,
        ]);
    }

    /** Kolomnya benar-benar ada, dan nol bila tidak diisi. */
    public function test_a_customer_carries_its_own_default_discount(): void
    {
        $store = $this->makeCustomer('LION TOKO BSA');
        $distributionCentre = $this->makeCustomer('LION DCA SUPERINDO', 2);

        $this->assertSame(0.0, $store->fresh()->default_discount);
        $this->assertSame(2.0, $distributionCentre->fresh()->default_discount);
    }

    /**
     * Nama tidak lagi menentukan diskon.
     *
     * Inilah inti perubahannya: dua pelanggan yang namanya sama-sama memuat
     * "DCA" boleh berbeda diskon, dan mengganti nama tidak mengubah apa pun.
     */
    public function test_the_name_no_longer_decides_the_discount(): void
    {
        $lookalike = $this->makeCustomer('PT DCAHAYA MANDIRI');

        $this->assertSame(0.0, $lookalike->fresh()->default_discount);

        $real = $this->makeCustomer('LION DCB SUPERINDO', 2);
        $real->update(['name' => 'LION DISTRIBUTION CENTRE B']);

        $this->assertSame(2.0, $real->fresh()->default_discount);
    }

    /**
     * Tidak ada lagi penetapan harga berdasarkan potongan nama, di mana pun.
     *
     * Memeriksa InvoiceResource saja tidak cukup; yang dijaga di sini adalah
     * agar bentuk seperti ini tidak lahir lagi di modul lain.
     */
    public function test_no_pricing_rule_anywhere_matches_on_a_customer_name(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            // Mencocokkan nama pelanggan lalu menyentuh harga atau diskon.
            if (preg_match('/(strpos|str_contains|Str::contains)\s*\(\s*(strtoupper\s*\()?\s*\$\w*(customer|pelanggan)\w*->name/i', $source)) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Nama pelanggan dipakai untuk mengambil keputusan di:'],
            $offenders,
            ['Nama bisa berubah dan bisa bertabrakan. Pakai kolom pada pelanggannya.'],
        )));
    }

    /** Invoice memakai diskon dari SO, tanpa menimpanya. */
    public function test_the_invoice_uses_the_discount_recorded_on_the_sales_order(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/InvoiceResource.php'));

        $this->assertStringNotContainsString('$discountPercent = 2.0;', $source);
        $this->assertSame(
            4,
            substr_count($source, '$discountPercent = $soItem ? (float)$soItem->discount : 0.0;'),
            'Keempat tempat perhitungan harus sama-sama memakai diskon dari SO.',
        );
    }

    /** Sales Order mengisi diskonnya sendiri dari pelanggan. */
    public function test_the_sales_order_fills_the_discount_from_the_customer(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/SalesOrderResource.php'));

        $this->assertStringContainsString('customerDefaultDiscount', $source);

        // Baris baru dari modal Add Products membawa diskonnya, dan mengganti
        // pelanggan menyegarkan diskon pada baris yang sudah ada.
        $this->assertStringContainsString(
            "'discount' => static::customerDefaultDiscount(\$customerId)",
            $source,
        );
        $this->assertStringContainsString('$set("items.{$key}.discount", $discount);', $source);
    }

    /** Diskonnya bisa dilihat dari daftar pelanggan, tidak tersembunyi lagi. */
    public function test_the_discount_is_visible_in_the_customer_list(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Clusters/CustomersCluster/Resources/CustomerResource.php'
        ));

        $this->assertStringContainsString("TextColumn::make('default_discount')", $source);
        $this->assertStringContainsString("TextInput::make('default_discount')", $source);

        // Batasnya nyata, bukan pemeriksaan panjang karakter.
        $field = substr($source, strpos($source, "TextInput::make('default_discount')"), 900);
        $this->assertStringContainsString("'numeric'", $field);
        $this->assertStringContainsString("'max:100'", $field);
        $this->assertStringNotContainsString('->numeric()', $field);
    }

    /**
     * TOP tidak boleh bertombol panah, sama seperti diskon.
     *
     * Ia menentukan tanggal jatuh tempo piutang, jadi tergeser satu hari
     * tanpa disadari bukan hal sepele. Keputusan yang sama sudah diambil
     * untuk berat karkas dan pH.
     */
    public function test_the_payment_term_has_no_spinner_arrows(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Clusters/CustomersCluster/Resources/CustomerResource.php'
        ));

        $field = substr($source, strpos($source, "TextInput::make('top')"), 600);

        $this->assertStringNotContainsString('->numeric()', $field);
        $this->assertStringContainsString("'integer'", $field);
        $this->assertStringContainsString("'inputmode' => 'numeric'", $field);
    }

    /**
     * Migrasi cutover memindahkan keadaan yang berlaku, bukan mengubahnya.
     *
     * Mencabut aturan lama begitu saja akan membuat SO yang belum diinvoice
     * tertagih kurang 2%. Migrasinya mengisi kolom pelanggan dan diskon pada
     * SO yang belum tertagih, sementara invoice yang sudah terbit tidak
     * disentuh -- ia menyimpan angkanya sendiri di `invoice_items`.
     */
    public function test_the_cutover_migration_only_touches_what_is_still_open(): void
    {
        $migration = file_get_contents(base_path(
            'database/migrations/2026_08_31_180100_move_lion_dc_discount_from_customer_names_to_data.php'
        ));

        $this->assertStringContainsString('whereNotIn', $migration);
        $this->assertStringContainsString("DB::table('invoices')->select('sales_order_id')", $migration);

        // Kolomnya sudah ada saat migrasi data dijalankan.
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('customers', 'default_discount'));
    }
}
