<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStockMovement;
use App\Models\MaterialUnit;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Jejak audit tidak boleh mengarang siapa yang mengerjakan.
 *
 * Tiga belas tempat menulis `auth()->id() ?? 1` -- "kalau tidak ada yang
 * login, tulis pengguna id 1". Angka itu bukan pilihan yang dipikirkan; ia
 * sekadar angka pertama.
 *
 * Pengguna id 1 TIDAK ADA di sistem ini. Yang paling awal id 100, dan itu
 * permintaan Owner justru supaya id 1 dan seterusnya bisa dipakai pengguna
 * warisan waktu data aplikasi lama dipindahkan nanti.
 *
 * Selama data lama belum masuk, fallback itu menunjuk orang yang tidak ada:
 * kolom ber-foreign-key menolaknya dengan galat SQL, yang tanpa foreign key
 * menyimpan angka nyangkut. **Sesudah data lama masuk, id 1 menjadi orang
 * sungguhan** -- dan kegagalan yang tadinya keras berubah menjadi diam:
 * tindakan tercatat rapi atas nama orang yang tidak mengerjakannya, tanpa
 * satu pun gejala.
 *
 * Karena itu ini harus beres SEBELUM pemindahan data, bukan sesudahnya.
 *
 * "Tidak diketahui" adalah jawaban yang jujur. Nama orang lain bukan.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    /** Kolom yang wajib boleh kosong, karena ada jalur yang menulisnya tanpa pengguna. */
    private const KOLOM_JEJAK = [
        ['material_findings', 'created_by'],
        ['purchase_materials', 'approved_by'],
        ['beef_stock_movements', 'created_by'],
        ['material_stock_movements', 'created_by'],
        ['delivery_order_receipts', 'created_by'],
        ['invoices', 'created_by'],
        ['mutations', 'received_by'],
        ['price_lists', 'created_by'],
        ['product_requisitions', 'reviewed_by'],
    ];

    /**
     * Tidak ada lagi yang mengarang pengguna id 1.
     *
     * Yang dijaga POLANYA, bukan tiga belas berkas yang kebetulan sudah
     * ketahuan -- penulis berikutnya akan lahir dengan menyalin tetangganya,
     * dan bentuk ini gampang sekali disalin karena kelihatan seperti
     * kehati-hatian.
     */
    public function test_nobody_invents_a_user_when_there_is_none(): void
    {
        $pelanggar = [];

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($berkas as $satu) {
            if (! $satu->isFile() || $satu->getExtension() !== 'php') {
                continue;
            }

            $isi = $this->tanpaKomentar(file_get_contents($satu->getPathname()));

            if (preg_match('/(?:auth\(\)->id\(\)|Auth::id\(\))\s*(?:\?\?|\?:)\s*\d/', $isi, $cocok)) {
                $pelanggar[] = basename($satu->getPathname()).'  ('.trim($cocok[0]).')';
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Berkas berikut mengarang pengguna waktu tidak ada yang login. Sesudah data lama "
            ."dipindahkan, angka itu akan menunjuk ORANG SUNGGUHAN yang tidak mengerjakannya:\n"
            .implode("\n", $pelanggar),
        );
    }

    /**
     * Kolom jejaknya memang boleh kosong.
     *
     * Membuang fallback-nya saja tidak cukup: kalau kolomnya `NOT NULL`,
     * yang dulu mengarang sekarang GAGAL menyimpan. Keduanya harus berjalan
     * bersama.
     *
     * @dataProvider kolomJejak
     */
    public function test_the_trail_column_may_say_it_does_not_know(string $tabel, string $kolom): void
    {
        $nullable = null;

        foreach (Schema::getColumns($tabel) as $satu) {
            if ($satu['name'] === $kolom) {
                $nullable = $satu['nullable'];
            }
        }

        $this->assertNotNull($nullable, "Kolom $tabel.$kolom tidak ada.");

        $this->assertTrue(
            $nullable,
            "Kolom $tabel.$kolom masih NOT NULL. Ada jalur yang menulisnya tanpa pengguna, dan "
            ."tanpa fallback yang mengarang, jalur itu sekarang gagal menyimpan.",
        );
    }

    /** @return array<string, array{string, string}> */
    public static function kolomJejak(): array
    {
        $hasil = [];

        foreach (self::KOLOM_JEJAK as [$tabel, $kolom]) {
            $hasil["$tabel.$kolom"] = [$tabel, $kolom];
        }

        return $hasil;
    }

    /**
     * Pergerakan stok yang ditulis tanpa pengguna tercatat "tidak diketahui".
     *
     * Bukan gagal, dan bukan atas nama orang lain. Inilah perilaku yang
     * dijaga -- dan inilah yang akan dipakai perintah konsol atau job kalau
     * suatu saat ada yang menulis stok dari sana.
     */
    public function test_a_movement_written_without_a_user_records_nobody(): void
    {
        $kategori = MaterialCategory::create(['name' => 'KEMASAN', 'is_active' => true]);
        $satuan = MaterialUnit::create(['name' => 'PCS', 'is_active' => true]);

        $material = Material::create([
            'name' => 'PLASTIK VAKUM',
            'code' => 'MTR-001',
            'material_category_id' => $kategori->id,
            'material_unit_id' => $satuan->id,
            'is_active' => true,
        ]);

        // Sengaja TANPA actingAs: inilah keadaan yang dulu memicu fallback.
        StockService::adjustStock($material->id, 10, 'UJI', 'DOC-UJI', 'tanpa pengguna');

        $pergerakan = MaterialStockMovement::firstOrFail();

        $this->assertNull(
            $pergerakan->created_by,
            'Pergerakan stok tanpa pengguna tercatat atas nama seseorang.',
        );

        $this->assertEqualsWithDelta(10.0, (float) $pergerakan->qty_in, 0.001);
    }

    /** Komentar dibuang supaya keterangannya tidak ikut tertuduh. */
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
