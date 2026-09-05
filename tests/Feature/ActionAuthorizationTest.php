<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tombol yang mengubah keadaan harus memeriksa SIAPA yang menekannya.
 *
 * Pemindaian seluruh `app/` menemukan enam belas aksi yang mengubah keadaan
 * tanpa satu pun pemeriksaan izin -- hanya status dokumennya yang diperiksa.
 *
 * Yang paling tajam bukan tombolnya, melainkan HALAMANNYA: empat halaman
 * persetujuan permintaan dan satu halaman persetujuan surat jalan tidak punya
 * `canAccess()` sama sekali. Izinnya ada, sudah di-seed, muncul di form Hak
 * Akses, dan diperiksa ketika memutuskan apakah TAUTANNYA ditampilkan -- lalu
 * halamannya sendiri terbuka bagi siapa pun yang tahu alamatnya.
 *
 * Sudah dibuktikan sebelum diperbaiki: pengguna dengan hanya
 * `view_product_requisitions` mengembalikan `true` untuk halaman
 * "Approve & Generate PO".
 */
class ActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Halaman yang mengubah keadaan, beserta izin yang menjaganya.
     *
     * @return array<int, array{0: class-string, 1: string}>
     */
    public static function halamanBerbahaya(): array
    {
        return [
            [\App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ApproveFinanceProductRequisition::class, 'approve_product_requisitions'],
            [\App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition::class, 'review_product_requisitions'],
            [\App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ApproveFinanceMaterialRequisition::class, 'approve_material_requisitions'],
            [\App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ReviewMaterialRequisition::class, 'review_material_requisitions'],
            [\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ApproveDeliveryOrder::class, 'approve_delivery_orders'],
        ];
    }

    /**
     * @dataProvider halamanBerbahaya
     */
    public function test_a_state_changing_page_is_closed_without_its_permission(string $halaman, string $izin): void
    {
        $orangLuar = User::create([
            'name' => 'Luar', 'username' => 'luar_'.uniqid(),
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($orangLuar->fresh());

        $this->assertFalse(
            $halaman::canAccess(['record' => 1]),
            "{$halaman} terbuka tanpa izin {$izin}.",
        );

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => $izin],
                ['module_name' => 'Test', 'description' => $izin],
            )->id
        );

        $this->actingAs($orangLuar->fresh());

        $this->assertTrue(
            $halaman::canAccess(['record' => 1]),
            "{$halaman} tetap tertutup padahal izin {$izin} sudah diberikan.",
        );
    }

    /**
     * Aksi yang mengubah keadaan tidak boleh hanya memeriksa status dokumen.
     *
     * Pemindainya kasar dengan sengaja: yang dicari cuma apakah di dalam
     * potongan aksinya ada `hasPermission` atau `isProgrammer` sama sekali.
     * Penjaga yang menuntut lebih dari itu akan gampang salah menuduh, dan
     * penjaga yang salah menuduh pada akhirnya dimatikan orang.
     */
    public function test_no_state_changing_action_is_left_unguarded(): void
    {
        $berat = '/Action::make\(\s*\'([a-zA-Z_]*(?:delete|hapus|void|cancel|batal|approve|'
            .'setujui|finish|selesai|complete|lock|kunci|unlock|revert|post|close|'
            .'generate|issue|terbit)[a-zA-Z_]*)\'\s*\)/i';

        $pelanggar = [];

        foreach ($this->berkasPhp() as $berkas) {
            $isi = $this->tanpaKomentar(file_get_contents($berkas));

            // Halaman yang sudah menjaga pintunya sendiri tidak perlu
            // mengulang penjagaan di setiap tombol di dalamnya.
            if (str_contains($isi, 'function canAccess')) {
                continue;
            }

            preg_match_all($berat, $isi, $cocok, PREG_OFFSET_CAPTURE);

            foreach ($cocok[1] as $satu) {
                [$nama, $posisi] = $satu;

                $potong = substr($isi, $posisi, 2500);

                // Berhenti di `Action::make` BERIKUTNYA -- apa pun namanya.
                //
                // Semula hanya berhenti di aksi yang namanya ikut daftar
                // berbahaya, sehingga potongan sebuah tombol tautan menyerap
                // `->action(` milik tombol tetangganya dan ikut tertuduh.
                if (preg_match('/Action::make\(/', substr($potong, 20), $berikut, PREG_OFFSET_CAPTURE)) {
                    $potong = substr($potong, 0, $berikut[0][1] + 20);
                }

                if (str_contains($potong, 'hasPermission') || str_contains($potong, 'isProgrammer')) {
                    continue;
                }

                // Aksi yang hanya berpindah halaman tidak mengubah apa pun.
                if (! str_contains($potong, '->action(')) {
                    continue;
                }

                $kunci = $this->relatif($berkas).':'.$nama;

                if (in_array($kunci, $this->sengajaTanpaIzin(), true)) {
                    continue;
                }

                $pelanggar[] = $kunci;
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Aksi berikut mengubah keadaan tetapi tidak memeriksa siapa yang menekannya:\n"
            .implode("\n", $pelanggar),
        );
    }

    /**
     * Aksi yang SENGAJA tidak diberi izin tersendiri.
     *
     * @return array<int, string>
     */
    private function sengajaTanpaIzin(): array
    {
        return [
            // Keputusan Owner, 5 September 2026: "mutasi biarin dulu begitu
            // bro". Kirim dan terima mutasi dipakai harian dan dibiarkan
            // menumpang akses halamannya.
            'app/Filament/Admin/Resources/MutationResource/Pages/ScanMutation.php:finish',
            'app/Filament/Admin/Resources/MutationResource/Pages/ReceiveMutation.php:finish',
            'app/Filament/Admin/Resources/MutationResource/Pages/ReceiveMutation.php:cancel_receive',

            // Membatalkan satu pindaian di dalam opname yang sedang berjalan.
            // Halamannya sendiri hanya terbuka untuk opname yang masih boleh
            // dihitung, dan membatalkan pindaian tidak menyentuh stok.
            'app/Filament/Admin/Resources/StockTakeResource/Pages/ScanStockTake.php:cancel_scan',

            // Membatalkan Sales Order dari layar draft tally. Owner sudah
            // membahas alur ini: "so cancel ya delete aja so nya, cuma kemarin
            // canceled yang cancel orang yang bikin tally".
            'app/Filament/Admin/Resources/TallyResource/Pages/DraftTally.php:cancel',
        ];
    }

    /** @return \Generator<string> */
    private function berkasPhp(): \Generator
    {
        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'))
        );

        foreach ($berkas as $satu) {
            if ($satu->isFile() && $satu->getExtension() === 'php') {
                yield $satu->getPathname();
            }
        }
    }

    /** Komentar diganti baris kosong supaya nomor barisnya tetap benar. */
    private function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $hasil .= str_repeat("\n", substr_count($token[1], "\n"));

                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }

    private function relatif(string $jalur): string
    {
        $jalur = str_replace('\\', '/', $jalur);
        $akar = str_replace('\\', '/', base_path()).'/';

        return str_replace($akar, '', $jalur);
    }
}
