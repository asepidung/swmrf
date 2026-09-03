<?php

namespace App\Support;

/**
 * Satu-satunya tempat rumus tagihan Invoice dihitung.
 *
 * Rumus harga -> diskon -> jumlah dulu disalin LIMA KALI di
 * `InvoiceResource`: sekali di `updateTotals()`, dan empat kali lagi sebagai
 * nilai awal `items`, `total_discount`, `subtotal`, dan `balance`. Semuanya
 * menghitung hal yang sama, jadi salinannya tidak terasa -- sampai salah
 * satunya berbeda.
 *
 * Dan sudah berbeda. `subtotal` versi nilai awal berisi barang SAJA,
 * sementara `updateTotals()` menimpanya dengan barang DITAMBAH biaya
 * tambahan. Satu kolom, dua arti, tergantung apakah ada yang sempat mengetik
 * sesuatu di form. Ini pola yang sama persis dengan saldo hutang yang dulu
 * ada di enam tempat.
 *
 * Sekarang artinya dikunci:
 *
 *     subtotal = barang, sesudah diskon barisnya
 *     charge   = biaya tambahan, sesudah diskon barisnya
 *     balance  = subtotal + charge - uang muka - yang sudah dibayar
 *
 * Bagian "yang sudah dibayar" TIDAK dihitung di sini. Form tidak tahu
 * apa-apa tentang pembayaran pelanggan, jadi `balance` yang dihasilkannya
 * hanya untuk DITAMPILKAN -- Invoice::recalculate() yang menurunkan angka
 * sebenarnya saat barisnya disimpan.
 *
 * Kolom `charge` sudah ada di tabel sejak awal dan tidak pernah diisi.
 * Sekarang ia yang menampung biaya tambahan, jadi `subtotal` bisa kembali
 * berarti subtotal.
 */
class InvoiceTotals
{
    /**
     * Hitung satu baris: berat x harga, dikurangi diskon persennya.
     *
     * @return array{discount_rp: float, amount: float}
     */
    public static function line(float $quantity, float $price, float $discountPercent): array
    {
        $gross = $quantity * $price;
        $discountRp = round($gross * ($discountPercent / 100), 0);

        return [
            'discount_rp' => $discountRp,
            'amount' => round($gross - $discountRp, 0),
        ];
    }

    /**
     * Baca angka yang datang dari form.
     *
     * Titik SELALU pemisah ribuan di sini, karena setiap field uang di form
     * Invoice memasang mask `$money` yang memang menaruhnya. Sebelum mask itu
     * ada, pembersihan yang sama dijalankan pada field yang tidak berformat --
     * sehingga `1234.56` yang dimaksudkan sebagai seribu dua ratus rupiah
     * lebih terbaca 123.456, dan tagihannya seratus kali lipat.
     *
     * Koma diterjemahkan menjadi titik desimal, satu-satunya arti yang mungkin
     * ia punya.
     */
    public static function number(mixed $value): float
    {
        $text = (string) ($value ?? '0');
        $text = str_replace('.', '', $text);

        return (float) str_replace(',', '.', $text);
    }

    /**
     * Persen diskon TIDAK dibersihkan seperti uang.
     *
     * Diskon di sistem ini persen bulat, dan fieldnya sengaja tidak berformat.
     * Titik di sana hanya bisa berarti koma desimal, jadi membuangnya
     * mengubah 2.5% menjadi 25% -- bug yang sudah pernah terjadi di Sales
     * Order dan tidak boleh kembali lewat pintu ini.
     */
    public static function percent(mixed $value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? '0'));
    }
}
