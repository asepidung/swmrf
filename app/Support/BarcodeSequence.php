<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Urutan empat digit terakhir sebuah barcode SWM.
 *
 * Barcodenya berbentuk `origin + ddmmyy + kode produk + grade + berat + pcs +
 * pH + urutan`. Yang dihitung di sini hanya bagian terakhirnya: nomor urut
 * yang membedakan dua karton yang seluruh sisanya kebetulan sama persis.
 *
 * **Aturan ini pernah ditulis TUJUH KALI**, dan enam salinannya salah dengan
 * cara yang sama:
 *
 *   - `strlen($barcode) >= 26` sebagai syarat sah. Project Owner sudah
 *     menegaskan tidak semua barcode 26 karakter; begitu baris terakhirnya
 *     kebetulan barcode lama yang lebih pendek, urutannya kembali ke 1 dan
 *     melahirkan barcode kembar.
 *   - `substr(-4)` pada baris TERAKHIR MENURUT ID, bukan urutan TERBESAR.
 *     Selama nomornya selalu naik keduanya sama, tetapi sekali saja ada baris
 *     yang formatnya lain, yang terbaca justru baris itu.
 *   - `orderBy('barcode', 'desc')` yang mengurutkan sebagai TEKS, sehingga
 *     "9" dianggap lebih besar daripada "10".
 *
 * Ditambal tiga kali di tiga berkas (#230, #269, #283) sebelum ada yang
 * memindai seluruh aplikasi -- dan pemindai itu langsung menemukan empat
 * berkas lagi. Penjaga yang menyebut satu nama berkas tidak pernah menahan
 * berkas berikutnya.
 *
 * Sekarang satu rumah, dan `StockGuardsTest` menjaga bentuk lamanya tidak
 * kembali di mana pun.
 */
class BarcodeSequence
{
    /** Panjang bagian urutan di ujung barcode. */
    public const PANJANG = 4;

    /**
     * Urutan berikutnya untuk sebuah awalan, dilihat dari beberapa tabel
     * sekaligus.
     *
     * Beberapa tabel, karena satu barcode bisa lahir di satu tempat dan pindah
     * ke tempat lain: label opname lahir di `stock_take_items` dan baru
     * menjadi `beef_stocks` saat opnamenya diselesaikan. Melihat satu tabel
     * saja berarti dua temuan dalam satu opname mendapat nomor yang sama.
     *
     * @param  array<int, Builder>  $queries  sudah dikunci sesuai kebutuhannya
     */
    public static function next(string $prefix, array $queries): int
    {
        $terbesar = 0;

        $queries = array_merge($queries, self::semuaTabelBarcode());

        foreach ($queries as $query) {
            $nilai = $query
                ->where('barcode', 'like', $prefix.'%')
                ->pluck('barcode')
                ->map(fn (?string $barcode): int => (int) substr((string) $barcode, -self::PANJANG))
                ->max();

            $terbesar = max($terbesar, (int) $nilai);
        }

        return $terbesar + 1;
    }

    /**
     * Sama dengan `next()`, tetapi sudah dalam bentuk empat digit siap tempel.
     *
     * @param  array<int, Builder>  $queries
     */
    public static function nextPadded(string $prefix, array $queries): string
    {
        return str_pad((string) self::next($prefix, $queries), self::PANJANG, '0', STR_PAD_LEFT);
    }
    /**
     * SEMUA tabel yang menyimpan barcode, termasuk yang sudah dihapus lunak.
     *
     * Sejak relabel Tally membawa origin ASLI barang (issue #497, bukan lagi
     * `6` miliknya sendiri), satu awalan `origin + tanggal` bisa dilahirkan
     * lebih dari satu tempat: label boning dan label relabel sama-sama
     * berawalan `1`. Penghitung yang hanya melihat tabelnya sendiri bisa
     * memberi nomor urut yang sama pada dua karton yang segalanya sama --
     * dan `beef_stocks.barcode` UNIK, jadi yang kedua gagal di tempat yang
     * jauh dari penyebabnya. Karena itu setiap penghitung ikut melihat
     * semuanya, apa pun tabel yang ia sebut sendiri.
     *
     * @return array<int, Builder>
     */
    private static function semuaTabelBarcode(): array
    {
        $models = [
            \App\Models\BoningItem::class,
            \App\Models\RepackResult::class,
            \App\Models\GoodsReceiptProductItem::class,
            \App\Models\SalesReturnItem::class,
            \App\Models\StockTakeItem::class,
            \App\Models\TallyItem::class,
            \App\Models\BeefStock::class,
        ];

        return array_map(
            fn (string $model): Builder => in_array(SoftDeletes::class, class_uses_recursive($model), true)
                ? $model::withTrashed()
                : $model::query(),
            $models,
        );
    }
}
