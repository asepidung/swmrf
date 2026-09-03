<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource;
use Tests\TestCase;

/**
 * Penghitungan total di form Invoice.
 *
 * `updateTotals()` membuang titik dari harga sebelum membacanya sebagai
 * angka. Itu benar HANYA kalau titiknya memang pemisah ribuan yang dipasang
 * JavaScript form. Di Invoice tidak ada mask uang sama sekali -- field
 * harganya cuma `->numeric()` -- jadi titik yang muncul di sana hanya bisa
 * berarti koma desimal.
 *
 * Pengujian ini memotret perilaku yang ada apa adanya, supaya perbaikannya
 * nanti punya pembanding.
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

    /** Harga bulat, tanpa titik: ini jalur yang normal dan memang benar. */
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
     * Harga berdesimal ditagih SERATUS KALI LIPAT.
     *
     * Titiknya dibuang sebagai "pemisah ribuan", padahal di Invoice tidak ada
     * mask yang memasangnya. 1.234,56 per kilo terbaca 123.456 per kilo.
     */
    public function test_a_decimal_price_is_billed_a_hundred_times_over(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 1, 'price' => '1234.56', 'discount_percent' => 0],
            ],
            'additionalCharges' => [],
            'down_payment' => 0,
        ]);

        $this->assertSame(
            123456.0,
            (float) $hasil['items'][0]['amount'],
            'Kalau angka ini sudah 1234.56, perilakunya sudah diperbaiki -- perbarui pengujian ini.',
        );
    }

    /**
     * Baris biaya tambahan memperlakukan titik dengan cara yang BERBEDA.
     *
     * Harga barang membuang titiknya; harga biaya tambahan membacanya sebagai
     * desimal. Dua field sejenis di satu halaman, dua hasil berbeda untuk
     * ketikan yang sama persis.
     */
    public function test_the_same_typing_means_two_different_things_on_one_page(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 1, 'price' => '1234.56', 'discount_percent' => 0],
            ],
            'additionalCharges' => [
                ['qty' => 1, 'price' => '1234.56', 'discount_percent' => 0],
            ],
            'down_payment' => 0,
        ]);

        $this->assertSame(123456.0, (float) $hasil['items'][0]['amount']);
        $this->assertSame(1235.0, (float) $hasil['additionalCharges'][0]['amount']);
    }

    /**
     * `subtotal` berisi barang DITAMBAH biaya tambahan, bukan subtotal barang.
     *
     * Nilai awal field yang sama -- saat form baru dibuka dan belum disentuh --
     * dihitung dari barang SAJA. Jadi satu kolom yang sama berisi dua hal yang
     * berbeda, tergantung apakah ada yang mengetik sesuatu atau tidak.
     */
    public function test_subtotal_actually_holds_the_grand_total(): void
    {
        $hasil = $this->hitung([
            'items' => [
                ['weight' => 10, 'price' => 50000, 'discount_percent' => 0],
            ],
            'additionalCharges' => [
                ['qty' => 2, 'price' => 25000, 'discount_percent' => 0],
            ],
            'down_payment' => 0,
        ]);

        $this->assertSame(550000.0, (float) $hasil['subtotal']);
        $this->assertSame(550000.0, (float) $hasil['balance']);
    }
}
