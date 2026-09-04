<?php

namespace Tests\Feature;

use App\Models\SalesOrder;
use Tests\TestCase;

/**
 * Status Sales Order: enam nilai, satu rumah.
 *
 * Sampai 4 September 2026 keenamnya ditulis sebagai teks mentah tersebar di
 * belasan berkas. Yang membuatnya lebih berbahaya daripada status Invoice:
 * kata `processing` dan `cancelled` JUGA dipakai sebagai status Tally, jadi
 * teks yang sama berarti dua hal berbeda tergantung tabel yang dibicarakan.
 *
 * Penyisirnya sengaja DIBATASI pada berkas yang pokok bahasannya memang Sales
 * Order. Menyisir seluruh `app/` akan menuduh Tally memakai status Sales
 * Order, padahal ia sedang menyebut miliknya sendiri.
 */
class SalesOrderStatusTest extends TestCase
{
    private const BERKAS_SALES_ORDER = [
        'app/Filament/Admin/Resources/SalesOrderResource.php',
        'app/Filament/Admin/Resources/SalesOrderResource/Pages/EditSalesOrder.php',
        'app/Filament/Admin/Resources/SalesOrderResource/Pages/CreateSalesOrder.php',
        'app/Filament/Admin/Resources/SalesOrderResource/Pages/ListSalesOrders.php',
        'app/Filament/Admin/Resources/SalesOrderResource/Pages/SalesOrderDetailList.php',
        'app/Policies/SalesOrderPolicy.php',
    ];

    public function test_the_sales_order_module_never_writes_a_status_as_a_bare_string(): void
    {
        $pelanggar = [];

        foreach (self::BERKAS_SALES_ORDER as $jalur) {
            $berkas = base_path($jalur);

            if (! file_exists($berkas)) {
                continue;
            }

            $isi = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents($berkas));

            foreach (array_keys(SalesOrder::statuses()) as $status) {
                if (str_contains($isi, "'".$status."'")) {
                    $pelanggar[] = $jalur.' -> '.$status;
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Status Sales Order ditulis sebagai teks mentah. Pakai SalesOrder::STATUS_*:\n"
            .implode("\n", $pelanggar)
        );
    }

    /**
     * Ejaan satu L tidak pernah menjadi status Sales Order.
     *
     * Aplikasi ini memakai dua ejaan untuk kata batal: `cancelled` di Sales
     * Order, Tally, dan Delivery Plan; `canceled` di PO dan Goods Receipt.
     * Keduanya nyata dan dipakai modul berbeda. Yang keliru adalah kode Sales
     * Order yang dulu memeriksa KEDUANYA dengan `in_array()`, seolah miliknya
     * sendiri bisa berbentuk dua rupa -- pemeriksaan yang menyembunyikan
     * perpecahannya alih-alih menyelesaikannya.
     *
     * Diperiksa di basis data hosting sebelum dilepas: `completed=3
     * waiting=4`, tidak ada satu pun baris berejaan satu L.
     */
    public function test_the_single_l_spelling_is_never_a_sales_order_status(): void
    {
        $pelanggar = [];

        foreach (self::BERKAS_SALES_ORDER as $jalur) {
            $berkas = base_path($jalur);

            if (! file_exists($berkas)) {
                continue;
            }

            $isi = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents($berkas));

            if (str_contains($isi, "'canceled'")) {
                $pelanggar[] = $jalur;
            }
        }

        $this->assertSame([], $pelanggar, 'Sales Order memakai ejaan `cancelled`, bukan `canceled`.');
    }

    /**
     * Tiap status yang bisa dimiliki dokumennya punya warna.
     *
     * Peta warnanya dulu bohong di dua arah: memberi warna kepada `prepared`
     * yang tidak pernah ditulis satu baris kode pun, dan MELEWATKAN
     * `on_delivery` -- keadaan yang justru paling sering dilihat, sehingga
     * Sales Order yang sedang dikirim tampil tanpa warna.
     */
    public function test_every_status_has_a_colour_and_no_colour_is_wasted(): void
    {
        $isi = file_get_contents(base_path('app/Filament/Admin/Resources/SalesOrderResource.php'));

        $awal = strpos($isi, '->colors([');
        $peta = substr($isi, $awal, strpos($isi, '])', $awal) - $awal);

        foreach (array_keys(SalesOrder::statuses()) as $status) {
            $this->assertStringContainsString(
                'STATUS_'.strtoupper($status),
                $peta,
                "Status `{$status}` tidak punya warna di peta badge.",
            );
        }

        $this->assertStringNotContainsString('prepared', $peta, 'Warna untuk keadaan yang tidak pernah ada.');
    }
}
