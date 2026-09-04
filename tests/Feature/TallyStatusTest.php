<?php

namespace Tests\Feature;

use App\Models\Tally;
use Tests\TestCase;

/**
 * Status Tally: TIGA nilai, bukan dua.
 *
 * Komentar di `$fillable` dulu menyebut "'processing', 'locked'" saja, padahal
 * `DeliveryOrder` menyetel status ketiga -- `do` -- saat surat jalannya
 * dibuat. Diperiksa di basis data hosting: `do=3`. Nilai yang paling banyak
 * ada justru yang tidak disebutkan, dan karena itu pula ia tidak punya warna
 * di badge: tally yang sudah menjadi surat jalan tampil seperti keadaan yang
 * tidak dikenali.
 *
 * Persis bug yang sama dengan `on_delivery` di Sales Order, dan ditemukan
 * dengan cara yang sama: menanyakan ke basis data yang sedang berjalan nilai
 * apa saja yang SUNGGUH ada, bukan membaca daftar yang ditulis di kode.
 */
class TallyStatusTest extends TestCase
{
    private const BERKAS_TALLY = [
        'app/Filament/Admin/Resources/TallyResource.php',
        'app/Filament/Admin/Resources/TallyResource/Pages/DraftTally.php',
        'app/Filament/Admin/Resources/TallyResource/Pages/ScanTally.php',
        'app/Filament/Admin/Resources/TallyResource/Pages/ViewTally.php',
    ];

    public function test_the_tally_module_never_writes_a_status_as_a_bare_string(): void
    {
        $pelanggar = [];

        foreach (self::BERKAS_TALLY as $jalur) {
            $isi = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents(base_path($jalur)));

            foreach (array_keys(Tally::statuses()) as $status) {
                if (str_contains($isi, "'".$status."'")) {
                    $pelanggar[] = $jalur.' -> '.$status;
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Status Tally ditulis sebagai teks mentah. Pakai Tally::STATUS_*:\n".implode("\n", $pelanggar)
        );
    }

    /** Tiap status yang bisa dimiliki dokumennya punya warna DAN nama yang terbaca. */
    public function test_every_status_has_a_colour_and_a_readable_label(): void
    {
        $isi = file_get_contents(base_path('app/Filament/Admin/Resources/TallyResource.php'));

        $awal = strpos($isi, '->colors([');
        $peta = substr($isi, $awal, strpos($isi, '])', $awal) - $awal);

        foreach (['STATUS_PROCESSING', 'STATUS_LOCKED', 'STATUS_DELIVERED'] as $konstanta) {
            $this->assertStringContainsString(
                $konstanta,
                $peta,
                "Status {$konstanta} tidak punya warna di peta badge.",
            );
        }

        // 'do' dulu tampil sebagai "Do" -- hasil ucfirst() pada singkatan,
        // yang tidak berarti apa-apa bagi pembacanya.
        $this->assertStringContainsString('Delivery Order', $isi);
    }

    /**
     * Menyaring status Sales Order yang batal TIDAK boleh memakai negasi yang
     * salah bentuk.
     *
     * Saat pemeriksaan ejaan gandanya dilepas, `!in_array($x, [...])` sempat
     * berubah menjadi `!$x === 'cancelled'` -- yang dibaca PHP sebagai
     * `(!$x) === 'cancelled'`, selalu salah, sehingga tombolnya hilang
     * selamanya. Ditangkap sebelum sempat berjalan.
     */
    public function test_no_malformed_negation_survives_in_the_tally_module(): void
    {
        foreach (self::BERKAS_TALLY as $jalur) {
            $isi = file_get_contents(base_path($jalur));

            $this->assertDoesNotMatchRegularExpression(
                '/!\s*\$[^\s;]*->status\s*===/',
                $isi,
                "Negasi salah bentuk di {$jalur}: `!\$x === ...` dibaca sebagai `(!\$x) === ...`.",
            );
        }
    }
}
