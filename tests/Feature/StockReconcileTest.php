<?php

namespace Tests\Feature;

use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `stock:reconcile` -- membuktikan buku besar pergerakan bisa dipercaya.
 *
 * `beef_stocks` sengaja hanya menyimpan keadaan sekarang; barang yang keluar
 * dihapus barisnya. Riwayatnya ada di `beef_stock_movements`, dan posisi stok
 * pada tanggal mana pun dihitung ulang dari sana -- tetapi hanya sejauh buku
 * besarnya utuh.
 *
 * Perintah ini yang memeriksa keutuhannya, dan test ini yang memeriksa
 * perintahnya: sebuah pemeriksa yang selalu bilang "bersih" tidak memeriksa
 * apa pun.
 */
class StockReconcileTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $gudang;

    private Grade $grade;

    private Product $produk;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->gudang = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $this->produk = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    private function masuk(string $barcode, float $kg, ?string $kapan = null): void
    {
        $this->gerak($barcode, $kg, 0, 'IN_GR_BEEF', $kapan);
    }

    private function keluar(string $barcode, float $kg, ?string $kapan = null): void
    {
        $this->gerak($barcode, 0, $kg, 'TALLY', $kapan);
    }

    private function gerak(string $barcode, float $in, float $out, string $jenis, ?string $kapan): void
    {
        $gerakan = BeefStockMovement::create([
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'condition' => $this->grade->id,
            'barcode' => $barcode,
            'transaction_type' => $jenis,
            'reference_document' => 'DOC-1',
            'weight_in' => $in,
            'weight_out' => $out,
            'pcs_in' => $in > 0 ? 1 : 0,
            'pcs_out' => $out > 0 ? 1 : 0,
            'created_by' => $this->user->id,
        ]);

        if ($kapan) {
            $gerakan->forceFill(['created_at' => $kapan])->saveQuietly();
        }
    }

    private function stok(string $barcode, float $kg): BeefStock
    {
        return BeefStock::create([
            'barcode' => $barcode,
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->grade->id,
            'weight' => $kg,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'status' => 'IN_STOCK',
        ]);
    }

    // =====================================================================

    /** Masuk 20, keluar 8, sisa 12 -- dan tabel stok memang berisi 12. */
    public function test_it_reports_clean_when_the_ledger_matches_the_stock(): void
    {
        $this->masuk('BC-1', 20.00);
        $this->keluar('BC-1', 8.00);
        $this->stok('BC-1', 12.00);

        $this->artisan('stock:reconcile')
            ->expectsOutputToContain('BERSIH')
            ->assertExitCode(0);
    }

    /**
     * Kalau buku besarnya meleset, perintahnya harus GAGAL.
     *
     * Pemeriksa yang selalu bilang bersih tidak memeriksa apa pun. Di sini
     * bukunya bilang 20 kg masuk tanpa satu pun keluar, sementara tabel stok
     * hanya berisi 12 kg -- artinya ada 8 kg yang hilang tanpa catatan.
     */
    public function test_it_fails_when_stock_left_without_a_movement(): void
    {
        $this->masuk('BC-1', 20.00);
        $this->stok('BC-1', 12.00);

        $this->artisan('stock:reconcile')
            ->expectsOutputToContain('MELESET')
            ->assertExitCode(1);
    }

    /**
     * Stok yang sudah ada sebelum buku besarnya mulai mencatat dihitung
     * tersendiri.
     *
     * Tanpa itu ia terbaca seolah bukunya bolong, padahal yang kurang justru
     * titik awalnya -- dan salah membaca sebabnya membuat orang mencari
     * kesalahan di tempat yang keliru.
     */
    public function test_it_names_stock_that_never_had_an_entry(): void
    {
        $this->stok('BC-LAMA', 30.00);

        $this->artisan('stock:reconcile')
            ->expectsOutputToContain('tanpa catatan masuk')
            ->expectsOutputToContain('BC-LAMA')
            ->assertExitCode(1);
    }

    /** Baris berstatus lain tidak dihitung layar Stock Overview, tapi ada. */
    public function test_it_names_rows_that_are_not_in_stock(): void
    {
        $this->masuk('BC-1', 20.00);
        $this->stok('BC-1', 20.00);
        $this->stok('BC-2', 5.00)->update(['status' => 'OUT_TO_REPACK']);

        $this->artisan('stock:reconcile')
            ->expectsOutputToContain('selain IN_STOCK')
            ->expectsOutputToContain('OUT_TO_REPACK')
            ->assertExitCode(0);
    }

    // =====================================================================
    // Posisi tanggal mundur
    // =====================================================================

    /**
     * Posisi pada tanggal tertentu hanya menghitung yang sudah tercatat
     * sampai akhir hari itu.
     */
    public function test_it_prints_the_position_on_a_given_date(): void
    {
        $this->masuk('BC-1', 20.00, '2026-08-10 09:00:00');
        $this->keluar('BC-1', 8.00, '2026-09-02 09:00:00');

        $this->artisan('stock:reconcile', ['--date' => '2026-08-31'])
            ->expectsOutputToContain('20.00')
            ->assertExitCode(0);

        $this->artisan('stock:reconcile', ['--date' => '2026-09-30'])
            ->expectsOutputToContain('12.00')
            ->assertExitCode(0);
    }

    /**
     * Angka tanggal mundur TIDAK BOLEH dicetak tanpa peringatannya.
     *
     * Tabelnya hanya punya `created_at`, jadi yang terbaca adalah waktu input
     * dan bukan tanggal dokumen. Angka yang benar tetapi disalahpahami sama
     * merugikannya dengan angka yang salah.
     */
    public function test_the_backdated_position_always_carries_its_warning(): void
    {
        $this->masuk('BC-1', 20.00, '2026-08-10 09:00:00');

        $this->artisan('stock:reconcile', ['--date' => '2026-08-31'])
            ->expectsOutputToContain('WAKTU INPUT')
            ->assertExitCode(0);
    }

    public function test_a_date_it_cannot_read_is_refused(): void
    {
        $this->artisan('stock:reconcile', ['--date' => 'kemarin sore'])
            ->expectsOutputToContain('tidak terbaca')
            ->assertExitCode(2);
    }

    /** Perintah ini HANYA MEMBACA: tidak boleh ada satu pun penulisan. */
    public function test_it_never_writes_anything(): void
    {
        $this->masuk('BC-1', 20.00);
        $this->stok('BC-1', 12.00);

        $sebelum = [
            'stok' => BeefStock::count(),
            'gerakan' => BeefStockMovement::count(),
        ];

        $this->artisan('stock:reconcile');
        $this->artisan('stock:reconcile', ['--date' => '2026-08-31']);

        $this->assertSame($sebelum['stok'], BeefStock::count());
        $this->assertSame($sebelum['gerakan'], BeefStockMovement::count());

        $berkas = file_get_contents(base_path('app/Console/Commands/ReconcileBeefStock.php'));

        foreach (['->insert(', '->update(', '->delete(', '->save(', '::create('] as $penulisan) {
            $this->assertStringNotContainsString(
                $penulisan,
                $berkas,
                "Perintah ini harus hanya membaca, tetapi memuat {$penulisan}",
            );
        }
    }
}
