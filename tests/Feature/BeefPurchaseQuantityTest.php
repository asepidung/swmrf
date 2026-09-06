<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Qty pembelian daging bulat, dan yang berkoma DITOLAK -- bukan dibulatkan.
 *
 * Kolomnya dulu `decimal(15,2)` dan kotak isiannya menerima koma, tetapi
 * layar daftar rinci maupun berkas cetaknya sama-sama membulatkan. Jadi 12,50
 * bisa tersimpan 12,50 dan terbaca 13 di dua tempat sekaligus -- tanpa galat,
 * dan tanpa satu pun cara melihat angka yang sebenarnya selain membuka basis
 * datanya.
 *
 * Keputusan Owner, 6 September 2026: daging dibeli dalam kilogram BULAT, sama
 * seperti material. Yang salah tipe kolomnya, bukan tampilannya.
 *
 * **Membulatkan diam-diam bukan pilihan.** Kalau koma dibiarkan lewat lalu
 * basis data yang membulatkannya, yang mengetik tidak pernah tahu angkanya
 * berubah -- dan itu bentuk kesalahan yang sama dengan yang baru saja
 * dibereskan, cuma pindah tempat.
 *
 * Berat yang sesungguhnya tetap berkoma di tempat yang benar:
 * `goods_receipt_product_items.weight`, saat barangnya diterima dan ditimbang.
 * Yang bulat PESANANNYA, bukan timbangannya.
 */
class BeefPurchaseQuantityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kolomnya benar-benar bilangan bulat.
     *
     * @dataProvider kolomQty
     */
    public function test_the_quantity_column_is_a_whole_number(string $tabel): void
    {
        $tipe = null;

        foreach (Schema::getColumns($tabel) as $kolom) {
            if ($kolom['name'] === 'qty') {
                $tipe = $kolom['type'];
            }
        }

        $this->assertNotNull($tipe, "Kolom $tabel.qty tidak ada.");

        $this->assertStringNotContainsString(
            'decimal',
            strtolower($tipe),
            "Kolom $tabel.qty berdesimal lagi, sementara layar dan berkas cetaknya membulatkan. "
            .'Angka yang tersimpan tidak akan pernah sama dengan yang dibaca orang.',
        );
    }

    /** @return array<string, array{string}> */
    public static function kolomQty(): array
    {
        return [
            'product_requisition_items' => ['product_requisition_items'],
            'purchase_product_items' => ['purchase_product_items'],
        ];
    }

    /**
     * Tidak ada layar yang menampilkan qty daging dengan desimal.
     *
     * Kolomnya sudah bulat, jadi menampilkan dua angka di belakang koma
     * cuma memasang nol palsu -- dan lebih buruk, ia mengajak orang mengetik
     * koma yang akan ditolak.
     */
    public function test_no_screen_shows_beef_purchase_quantity_with_decimals(): void
    {
        $pelanggar = [];

        $berkas = array_merge(
            glob(app_path('Filament/Admin/Resources/ProductRequisitionResource*.php')),
            glob(app_path('Filament/Admin/Resources/ProductRequisitionResource/Pages/*.php')),
            glob(app_path('Filament/Admin/Resources/PurchaseProductResource*.php')),
            glob(app_path('Filament/Admin/Resources/PurchaseProductResource/Pages/*.php')),
        );

        foreach ($berkas as $satu) {
            $isi = $this->tanpaKomentar(file_get_contents($satu));

            foreach ([
                "/number_format\(\s*[^,]*qty[^,]*,\s*[1-9]/i" => 'number_format dengan desimal',
                "/make\(\s*'qty'\s*\)[^;]{0,200}?->numeric\(\s*[1-9]/is" => 'kolom tabel dengan desimal',
            ] as $pola => $sebab) {
                if (preg_match($pola, $isi)) {
                    $pelanggar[] = basename($satu).'  -- '.$sebab;
                }
            }
        }

        $pelanggar = array_values(array_unique($pelanggar));
        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Qty pembelian daging ditampilkan berdesimal padahal kolomnya bulat:\n"
            .implode("\n", $pelanggar),
        );
    }

    /**
     * Kotak isiannya menolak koma, bukan membulatkannya.
     *
     * Yang diperiksa aturannya sendiri, dijalankan langsung -- bukan sekadar
     * ada tidaknya teks di berkas.
     */
    public function test_a_fractional_quantity_is_refused(): void
    {
        $aturan = $this->aturanQty();

        $ditolak = [];
        $aturan('qty', '12,5', function (string $pesan) use (&$ditolak): void {
            $ditolak[] = $pesan;
        });

        $this->assertNotSame([], $ditolak, 'Qty 12,5 diterima. Basis data akan membulatkannya diam-diam.');
    }

    /** Yang bulat tetap diterima, termasuk yang memakai pemisah ribuan. */
    public function test_a_whole_quantity_is_accepted(): void
    {
        $aturan = $this->aturanQty();

        foreach (['12', '1.500', '250'] as $nilai) {
            $ditolak = [];
            $aturan('qty', $nilai, function (string $pesan) use (&$ditolak): void {
                $ditolak[] = $pesan;
            });

            $this->assertSame([], $ditolak, "Qty $nilai seharusnya diterima.");
        }
    }

    /** Nol tetap ditolak, seperti sebelumnya. */
    public function test_zero_is_still_refused(): void
    {
        $aturan = $this->aturanQty();

        $ditolak = [];
        $aturan('qty', '0', function (string $pesan) use (&$ditolak): void {
            $ditolak[] = $pesan;
        });

        $this->assertNotSame([], $ditolak, 'Qty nol diterima.');
    }

    /**
     * Aturan `qty` yang SESUNGGUHNYA, diambil dari tempatnya sendiri.
     *
     * Bukan ditulis ulang di sini. Aturan yang disalin ke tempat kedua selalu
     * berakhir berbeda dari yang pertama, dan ujinya jadi membuktikan
     * salinannya benar -- bukan yang dipakai orang.
     */
    private function aturanQty(): \Closure
    {
        return \App\Filament\Admin\Resources\ProductRequisitionResource::aturanQtyBulat();
    }

    private function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }
}
