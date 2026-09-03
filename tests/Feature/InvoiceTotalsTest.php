<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource;
use Tests\TestCase;

/**
 * Penghitungan total di form Invoice.
 *
 * Rumusnya dulu disalin LIMA KALI di satu berkas, dan salinannya sudah
 * berbeda arah: `subtotal` versi nilai awal berisi barang saja, sementara
 * `updateTotals()` menimpanya dengan barang ditambah biaya tambahan.
 *
 * Sekarang semuanya lewat `App\Support\InvoiceTotals`, dan arti tiap kolom
 * dikunci:
 *
 *     subtotal = barang, sesudah diskon barisnya
 *     charge   = biaya tambahan, sesudah diskon barisnya
 *     balance  = subtotal + charge - uang muka
 */
class InvoiceTotalsTest extends TestCase
{
    /**
     * Jalankan updateTotals() atas satu himpunan state form.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>  state sesudahnya
     */
    private function hitung(array $state): array
    {
        $get = function (string $path) use (&$state) {
            return data_get($state, $path);
        };

        $set = function (string $path, $value) use (&$state) {
            data_set($state, $path, $value);
        };

        InvoiceResource::updateTotals($get, $set);

        return $state;
    }

    /** Harga bulat, jalur yang normal. */
    public function test_a_whole_price_is_billed_as_typed(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 10, 'price' => 50000, 'discount_percent' => 0],
            ],
            'additionalCharges' => [],
            'down_payment' => 0,
        ]);

        $this->assertSame(500000.0, (float) $hasil['items'][0]['amount']);
        $this->assertSame(500000.0, (float) $hasil['subtotal']);
    }

    /**
     * Titik pada uang SELALU pemisah ribuan, dan sekarang masknya memang
     * memasangnya.
     *
     * Sebelumnya pembersihan ini berjalan pada field yang tidak berformat
     * sama sekali, sehingga `1234.56` yang dimaksudkan sebagai seribu dua
     * ratus rupiah lebih terbaca 123.456 -- tagihannya seratus kali lipat.
     * Sekarang tidak ada lagi cara mengetik koma desimal ke sana.
     */
    public function test_a_thousands_separator_is_read_as_one(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 1, 'price' => '1.234.560', 'discount_percent' => 0],
            ],
            'additionalCharges' => [],
            'down_payment' => 0,
        ]);

        $this->assertSame(1234560.0, (float) $hasil['items'][0]['amount']);
    }

    /**
     * Barang dan biaya tambahan membaca ketikan yang sama dengan cara yang
     * sama.
     *
     * Dulu tidak: harga barang membuang titiknya, harga biaya tambahan
     * membacanya sebagai desimal. Dua field sejenis di satu halaman, dua
     * hasil berbeda untuk ketikan yang sama persis.
     */
    public function test_both_repeaters_read_the_same_typing_the_same_way(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 1, 'price' => '1.500.000', 'discount_percent' => 0],
            ],
            'additionalCharges' => [
                ['qty' => 1, 'price' => '1.500.000', 'discount_percent' => 0],
            ],
            'down_payment' => 0,
        ]);

        $this->assertSame(1500000.0, (float) $hasil['items'][0]['amount']);
        $this->assertSame(1500000.0, (float) $hasil['additionalCharges'][0]['amount']);
    }

    /**
     * Persen diskon TIDAK dibersihkan seperti uang.
     *
     * Field diskon sengaja tidak berformat, jadi titik di sana hanya bisa
     * berarti koma desimal. Membuangnya mengubah 2,5% menjadi 25% -- bug yang
     * sudah pernah terjadi di Sales Order.
     */
    public function test_a_decimal_discount_is_not_multiplied_by_a_hundred(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 1, 'price' => 1000000, 'discount_percent' => '2.5'],
            ],
            'additionalCharges' => [],
            'down_payment' => 0,
        ]);

        $this->assertSame(25000.0, (float) $hasil['items'][0]['discount_rp']);
        $this->assertSame(975000.0, (float) $hasil['items'][0]['amount']);
    }

    /**
     * Tiap kolom total berisi satu hal, dan hanya hal itu.
     */
    public function test_each_total_column_holds_exactly_one_thing(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 10, 'price' => 50000, 'discount_percent' => 0],
            ],
            'additionalCharges' => [
                ['qty' => 2, 'price' => 25000, 'discount_percent' => 0],
            ],
            'down_payment' => 100000,
        ]);

        $this->assertSame(500000.0, (float) $hasil['subtotal'], 'subtotal berisi barang saja');
        $this->assertSame(50000.0, (float) $hasil['charge'], 'charge berisi biaya tambahan saja');
        $this->assertSame(450000.0, (float) $hasil['balance'], 'balance = subtotal + charge - DP');
    }

    /** Diskon per baris ikut terhitung ke total diskonnya. */
    public function test_line_discounts_add_up(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 10, 'price' => 50000, 'discount_percent' => 2],
                ['weight' => 5, 'price' => 40000, 'discount_percent' => 2],
            ],
            'additionalCharges' => [],
            'down_payment' => 0,
        ]);

        $this->assertSame(14000.0, (float) $hasil['total_discount']);
        $this->assertSame(686000.0, (float) $hasil['subtotal']);
        $this->assertSame(15.0, (float) $hasil['total_weight']);
    }
}
