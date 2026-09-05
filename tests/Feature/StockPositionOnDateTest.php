<?php

namespace Tests\Feature;

use App\Filament\Clusters\BeefStocks\Pages\StockPositionOnDate;
use App\Models\BeefStockMovement;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockTake;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman Posisi Stok per Tanggal.
 *
 * Angkanya TIDAK diambil dari `beef_stocks` -- tabel itu hanya tahu keadaan
 * sekarang. Semuanya dihitung ulang dari `beef_stock_movements`.
 */
class StockPositionOnDateTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $jonggol;

    private Warehouse $perum;

    private Grade $chill;

    private Product $produk;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->jonggol = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->perum = Warehouse::create(['code' => 'PRM', 'name' => 'PERUM', 'is_active' => true]);
        $this->chill = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $this->produk = Product::create([
            'name' => 'TENDERLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    private function gerak(float $in, float $out, string $kapan, ?Warehouse $gudang = null): void
    {
        $gerakan = BeefStockMovement::create([
            'product_id' => $this->produk->id,
            'warehouse_id' => ($gudang ?? $this->jonggol)->id,
            'condition' => $this->chill->id,
            'barcode' => 'BC-' . uniqid(),
            'transaction_type' => $in > 0 ? 'IN_GR_BEEF' : 'TALLY',
            'reference_document' => 'DOC',
            'weight_in' => $in,
            'weight_out' => $out,
            'pcs_in' => $in > 0 ? 1 : 0,
            'pcs_out' => $out > 0 ? 1 : 0,
            'created_by' => $this->user->id,
        ]);

        $gerakan->forceFill(['created_at' => $kapan])->saveQuietly();
    }

    // =====================================================================

    /**
     * Tanggal 1 September berarti posisi AKHIR HARI 1 September.
     *
     * Pertanyaan Owner: "misal gw filter ke tanggal 1 september itu lu
     * tampilin 1 september jam berapa?" Jawabannya 23:59:59, dan halamannya
     * menulis jam itu terang-terangan supaya tidak jadi tebak-tebakan.
     */
    public function test_a_date_means_the_end_of_that_day(): void
    {
        $this->gerak(20.00, 0, '2026-09-01 08:00:00');
        $this->gerak(5.00, 0, '2026-09-01 22:30:00');   // masih hari yang sama
        $this->gerak(100.00, 0, '2026-09-02 00:30:00'); // sudah lewat

        $halaman = new StockPositionOnDate();
        $halaman->tanggal = '2026-09-01';

        $this->assertSame('2026-09-01 23:59:59', $halaman->batas()->format('Y-m-d H:i:s'));
        $this->assertSame(25.0, $halaman->posisi()['grand']);
    }

    /** Yang keluar sesudah tanggalnya belum boleh ikut mengurangi. */
    public function test_movements_after_the_date_are_not_counted(): void
    {
        $this->gerak(20.00, 0, '2026-08-10 09:00:00');
        $this->gerak(0, 8.00, '2026-09-02 09:00:00');

        $halaman = new StockPositionOnDate();

        $halaman->tanggal = '2026-08-31';
        $this->assertSame(20.0, $halaman->posisi()['grand']);

        $halaman->tanggal = '2026-09-30';
        $this->assertSame(12.0, $halaman->posisi()['grand']);
    }

    /** Total harus sama dengan jumlah kolomnya, seperti di Stock Overview. */
    public function test_the_total_equals_the_sum_of_its_columns(): void
    {
        $this->gerak(20.00, 0, '2026-09-01 08:00:00', $this->jonggol);
        $this->gerak(12.00, 0, '2026-09-01 08:00:00', $this->perum);

        $posisi = (function (): array {
            $halaman = new StockPositionOnDate();
            $halaman->tanggal = '2026-09-01';

            return $halaman->posisi();
        })();

        $this->assertCount(2, $posisi['buckets']);
        $this->assertSame(32.0, $posisi['grand']);
        $this->assertSame(32.0, array_sum($posisi['total']));
    }

    /** Kombinasi yang saldonya nol pada tanggal itu tidak memakan kolom. */
    public function test_a_combination_back_to_zero_gets_no_column(): void
    {
        $this->gerak(20.00, 0, '2026-08-01 08:00:00', $this->perum);
        $this->gerak(0, 20.00, '2026-08-02 08:00:00', $this->perum);
        $this->gerak(10.00, 0, '2026-08-03 08:00:00', $this->jonggol);

        $halaman = new StockPositionOnDate();
        $halaman->tanggal = '2026-08-31';

        $buckets = $halaman->posisi()['buckets'];

        $this->assertCount(1, $buckets);
        $this->assertSame('JONGGOL', $buckets[0]['warehouse']);
    }

    // =====================================================================
    // Halamannya
    // =====================================================================

    public function test_the_page_renders_the_position(): void
    {
        $this->gerak(302.54, 0, '2026-09-01 08:00:00');

        Livewire::actingAs($this->user)
            ->test(StockPositionOnDate::class, ['tanggal' => '2026-09-01'])
            ->assertSuccessful()
            ->assertSee('TENDERLOIN')
            ->assertSee('302.54');
    }

    /**
     * Peringatan "waktu input" tidak boleh hilang dari halamannya.
     *
     * Angka yang benar tetapi disalahpahami sama merugikannya dengan angka
     * yang salah: `beef_stock_movements` hanya punya `created_at`, jadi yang
     * terbaca waktu input dan bukan tanggal dokumen.
     */
    public function test_the_page_always_carries_the_entry_time_warning(): void
    {
        $this->gerak(10.00, 0, '2026-09-01 08:00:00');

        Livewire::actingAs($this->user)
            ->test(StockPositionOnDate::class, ['tanggal' => '2026-09-01'])
            ->assertSee(__('The date is the time of ENTRY, not the document date.'))
            ->assertSee('23:59:59');
    }

    /**
     * Selama opname daging berjalan, angkanya ditutup.
     *
     * Halaman ini menjawab persis pertanyaan yang seharusnya dijawab oleh
     * hitungan fisik.
     */
    public function test_the_numbers_are_hidden_while_a_stock_count_runs(): void
    {
        $this->gerak(302.54, 0, '2026-09-01 08:00:00');

        StockTake::create([
            'document_number' => 'SO-1',
            'period' => now()->format('Y-m'),
            'date' => now()->toDateString(),
            'status' => 'IN_PROGRESS',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(StockPositionOnDate::class, ['tanggal' => '2026-09-01'])
            ->assertSee(__('A stock count is running, so the numbers are hidden.'))
            ->assertDontSee('302.54');
    }

    public function test_it_needs_the_stock_permission(): void
    {
        $orangLuar = User::create([
            'name' => 'Luar', 'username' => 'luar_posisi',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($orangLuar->fresh());
        $this->assertFalse(StockPositionOnDate::canAccess());

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_beef_stocks'],
                ['module_name' => 'Beef Stocks', 'description' => 'View beef stocks'],
            )->id
        );

        $this->actingAs($orangLuar->fresh());
        $this->assertTrue(StockPositionOnDate::canAccess());
    }

    /** Halaman ini TIDAK boleh membaca `beef_stocks` sama sekali. */
    public function test_it_never_reads_the_current_stock_table(): void
    {
        $berkas = file_get_contents(base_path(
            'app/Filament/Clusters/BeefStocks/Pages/StockPositionOnDate.php'
        ));

        $this->assertStringNotContainsString("'beef_stocks'", $berkas);
        $this->assertStringNotContainsString('BeefStock::', $berkas);
    }
}
