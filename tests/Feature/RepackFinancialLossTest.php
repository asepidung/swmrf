<?php

namespace Tests\Feature;

use App\Models\BeefStock;
use App\Models\FinancialLoss;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Repack;
use App\Models\RepackMaterial;
use App\Models\RepackResult;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Susut Repack (bahan masuk dikurangi hasil keluar) tidak pernah masuk ke
 * Financial Loss -- laporan cetak sudah menampilkan "Balance (Loss)" sejak
 * lama, tapi angkanya hanya hidup di layar, tidak tersimpan di mana pun.
 * Laporan Owner, 13 September 2026.
 *
 * Pola yang ditiru PERSIS dari susut kirim (`DeliveryOrder`) dan susut
 * timbang sapi (`CattleWeighing`, #299): `amount` tetap nol sampai HPP ada
 * (menilai dengan harga jual melebih-lebihkan kerugiannya), tapi `quantity`
 * (kilogram) tercatat sejak sekarang. Syarat tulis/hapusnya BERAT susut,
 * bukan rupiah -- memakai rupiah sebagai syarat berarti baris ini tidak
 * akan pernah tertulis sama sekali, jebakan yang sama yang sudah pernah
 * terjadi di CattleWeighing.
 */
class RepackFinancialLossTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Grade $grade;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function repack(array $bahan, array $hasil): Repack
    {
        $repack = Repack::create([
            'repack_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        foreach ($bahan as $i => $berat) {
            RepackMaterial::create([
                'repack_id' => $repack->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'grade_id' => $this->grade->id,
                'barcode' => 'BAHAN-'.$repack->id.'-'.$i,
                'weight' => $berat,
                'qty_pcs' => 1,
                'pack_date' => now()->toDateString(),
                'origin' => '1',
                'status' => 'IN_STOCK',
            ]);
        }

        foreach ($hasil as $i => $berat) {
            $barcode = 'HASIL-'.$repack->id.'-'.$i;

            RepackResult::create([
                'repack_id' => $repack->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'grade_id' => $this->grade->id,
                'barcode' => $barcode,
                'weight' => $berat,
                'qty_pcs' => 1,
                'pack_date' => now()->toDateString(),
            ]);

            // Disambungkan lewat barcode yang sama, meniru pasangan yang
            // dibuat `InputHasilRepack::create()` -- `unlock()` memeriksa
            // baris ini masih ada di gudang sebelum mengizinkan dokumennya
            // dibuka lagi.
            BeefStock::create([
                'barcode' => $barcode,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'grade_id' => $this->grade->id,
                'weight' => $berat,
                'qty_pcs' => 1,
                'pack_date' => now()->toDateString(),
                'origin' => '2',
                'status' => 'IN_STOCK',
            ]);
        }

        return $repack->fresh();
    }

    public function test_shrinkage_is_recorded_as_a_financial_loss_when_locked(): void
    {
        $repack = $this->repack([100], [92]);

        $repack->lock();

        $loss = FinancialLoss::where('lossable_type', Repack::class)
            ->where('lossable_id', $repack->id)
            ->first();

        $this->assertNotNull($loss);
        $this->assertSame(FinancialLoss::SUMBER_REPACK, $loss->transaction_type);
        $this->assertSame($repack->doc_no, $loss->reference_number);
        $this->assertSame(8.0, (float) $loss->quantity);
        $this->assertSame('Kg', $loss->unit);
        $this->assertSame(0.0, (float) $loss->amount);
        $this->assertTrue($loss->isNotPricedYet());
    }

    /**
     * Berat susut, bukan rupiah, yang menentukan baris ini tertulis.
     *
     * Rupiahnya SELALU nol untuk Repack sampai HPP ada -- kalau syaratnya
     * `amount > 0`, baris ini tidak akan pernah tertulis sama sekali.
     */
    public function test_no_loss_is_recorded_when_nothing_shrank(): void
    {
        $repack = $this->repack([100], [100]);

        $repack->lock();

        $this->assertSame(0, FinancialLoss::where('lossable_type', Repack::class)
            ->where('lossable_id', $repack->id)
            ->count());
    }

    /**
     * Hasil yang lebih berat daripada bahannya mustahil secara fisik --
     * `shrinkWeight()` negatif, jadi bukan susut dan tidak tercatat. Kasus
     * ini selalu di luar batas wajar apa pun ambangnya, jadi butuh izin QC
     * lebih dulu supaya `lock()` sendiri tidak menolak sebelum sempat
     * menulis apa pun.
     */
    public function test_no_loss_is_recorded_when_output_is_heavier_than_input(): void
    {
        $repack = $this->repack([100], [105]);
        $repack->grantShrinkOverride('Ditimbang ulang, hasilnya memang lebih berat.', $this->user->id);

        $repack->fresh()->lock();

        $this->assertSame(0, FinancialLoss::where('lossable_type', Repack::class)
            ->where('lossable_id', $repack->id)
            ->count());
    }

    /**
     * Buka kunci, betulkan bahan yang salah ketik, kunci lagi -- baris
     * lama DIPERBARUI, bukan menumpuk baris baru untuk dokumen yang sama.
     */
    public function test_relocking_after_a_correction_updates_the_existing_row_instead_of_duplicating(): void
    {
        $repack = $this->repack([100], [92]);
        $repack->lock();

        $repack->fresh()->unlock();
        RepackMaterial::create([
            'repack_id' => $repack->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'barcode' => 'BAHAN-TAMBAHAN-'.$repack->id,
            'weight' => 10,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'origin' => '1',
            'status' => 'IN_STOCK',
        ]);

        $repack->fresh()->lock();

        $rows = FinancialLoss::where('lossable_type', Repack::class)
            ->where('lossable_id', $repack->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(18.0, (float) $rows->first()->quantity);
    }

    /**
     * Buka kunci, betulkan sampai susutnya hilang, kunci lagi -- baris lama
     * dihapus. Tanpa ini laporan kerugian menyimpan jejak susut yang sudah
     * tidak nyata lagi.
     */
    public function test_an_existing_loss_is_withdrawn_when_relocked_with_no_shrinkage_left(): void
    {
        $repack = $this->repack([100], [92]);
        $repack->lock();

        $this->assertSame(1, FinancialLoss::where('lossable_type', Repack::class)
            ->where('lossable_id', $repack->id)
            ->count());

        $repack->fresh()->unlock();
        $barcodeTambahan = 'HASIL-TAMBAHAN-'.$repack->id;
        RepackResult::create([
            'repack_id' => $repack->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'barcode' => $barcodeTambahan,
            'weight' => 8,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
        ]);
        BeefStock::create([
            'barcode' => $barcodeTambahan,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 8,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'origin' => '2',
            'status' => 'IN_STOCK',
        ]);

        $repack->fresh()->lock();

        $this->assertSame(0, FinancialLoss::where('lossable_type', Repack::class)
            ->where('lossable_id', $repack->id)
            ->count());
    }
}
