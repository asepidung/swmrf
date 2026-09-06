<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Repack;
use App\Models\RepackMaterial;
use App\Models\RepackResult;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repack: apa yang masuk dibanding apa yang keluar.
 *
 * Sampai 4 September 2026 tidak ada satu baris pun yang membandingkan
 * `Repack::materials()` dengan `Repack::results()`. Dokumen dengan bahan 100 kg
 * dan hasil 500 kg bisa dikunci, stoknya bertambah 400 kg dari udara, dan
 * permanen karena sesudah terkunci tidak bisa diubah.
 *
 * Bentuk penjagaannya keputusan Project Owner, dari dua tawarannya sendiri:
 * tolak keras membuat pekerjaan berhenti saat kenyataan jomplang, peringatan
 * biasa diabaikan. Yang diambil gabungan keduanya -- yang mengerjakan tidak
 * bisa meneruskan sendiri, tetapi dokumennya menunggu orang berwenang alih-alih
 * dibuang, dan penembusannya meninggalkan alasan tertulis.
 */
class RepackYieldTest extends TestCase
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

    /**
     * @param  array<int, float>  $bahan
     * @param  array<int, float>  $hasil
     */
    /** Menambah satu bahan ke repack yang sudah ada. */
    private function tambahBahan(Repack $repack, float $berat): RepackMaterial
    {
        return RepackMaterial::create([
            'repack_id' => $repack->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'barcode' => 'BAHAN-TAMBAH-'.$repack->id.'-'.uniqid(),
            'weight' => $berat,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'origin' => '1',
            'status' => 'IN_STOCK',
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
            RepackResult::create([
                'repack_id' => $repack->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'grade_id' => $this->grade->id,
                'barcode' => 'HASIL-'.$repack->id.'-'.$i,
                'weight' => $berat,
                'qty_pcs' => 1,
                'pack_date' => now()->toDateString(),
            ]);
        }

        return $repack->fresh();
    }

    // =====================================================================
    // Menghitung
    // =====================================================================

    public function test_it_measures_what_went_in_against_what_came_out(): void
    {
        $repack = $this->repack([60, 40], [55, 40]);

        $this->assertSame(100.0, $repack->inputWeight());
        $this->assertSame(95.0, $repack->outputWeight());
        $this->assertSame(5.0, $repack->shrinkWeight());
        $this->assertSame(5.0, $repack->shrinkPercent());
    }

    /**
     * Belum ada bahan berarti BELUM BISA DIHITUNG, bukan nol persen.
     *
     * Nol persen adalah dokumen yang bahannya utuh menjadi hasil. Belum ada
     * bahan adalah dokumen yang belum dikerjakan. Menyamakan keduanya membuat
     * dokumen kosong terbaca sempurna.
     */
    public function test_a_repack_without_input_has_no_percentage_at_all(): void
    {
        $repack = $this->repack([], []);

        $this->assertNull($repack->shrinkPercent());
    }

    /**
     * Hasil yang dihapus tidak ikut dihitung.
     *
     * Ketiga angka ini dulu dijumlahkan dengan `DB::table()` mentah, dan hanya
     * SALAH SATUNYA yang menyaring `deleted_at` dengan tangan. Sekarang
     * semuanya lewat relasi, jadi penyaringnya datang sendiri.
     */
    public function test_a_deleted_result_no_longer_counts(): void
    {
        $repack = $this->repack([100], [50, 45]);

        $this->assertSame(95.0, $repack->outputWeight());

        $repack->results()->latest('id')->first()->delete();

        $this->assertSame(50.0, $repack->fresh()->outputWeight());
    }

    // =====================================================================
    // Gerbangnya mati selama ambangnya belum dipilih manusia
    // =====================================================================

    /**
     * Tanpa ambang, susut sebesar apa pun tidak menghalangi.
     *
     * Ini disengaja. Sekarang belum ada satu pun data susut yang pernah
     * tersimpan, jadi angka apa pun yang dikarang di kode akan salah.
     * Menghalangi pekerjaan dengan angka yang tidak dipilih manusia mana pun
     * sudah pernah menjadi kesalahan pada penjaga berat retur hari ini.
     */
    public function test_without_a_limit_nothing_is_held_back(): void
    {
        $repack = $this->repack([100], [40]);

        $this->assertNull(Repack::shrinkLimitPercent());
        $this->assertTrue($repack->isWithinShrinkLimit());

        $repack->lock();

        $this->assertTrue($repack->fresh()->kunci);
        $this->assertSame('LOCKED', $repack->fresh()->status);
        $this->assertNull($repack->fresh()->yield_override_at);
    }

    public function test_within_the_limit_it_locks_without_a_reason(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [95]);

        $this->assertTrue($repack->isWithinShrinkLimit());

        $repack->lock();

        $this->assertTrue($repack->fresh()->kunci);
        $this->assertNull($repack->fresh()->yield_override_reason);
    }

    public function test_beyond_the_limit_it_refuses_without_a_reason(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        $this->assertFalse($repack->isWithinShrinkLimit());

        try {
            $repack->lock();
            $this->fail('Repack di luar batas seharusnya ditolak tanpa alasan.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }

        // Dokumennya TIDAK dibuang -- ia menunggu.
        $this->assertFalse($repack->fresh()->kunci);
        $this->assertSame('OPEN', $repack->fresh()->status ?? 'OPEN');
    }

    /**
     * Menembus meninggalkan bekas: alasannya, namanya, dan waktunya.
     *
     * Inilah yang membuat bentuk ini tidak jatuh menjadi "peringatan yang
     * diabaikan". Penembusan yang sering terbaca sebagai pola -- entah
     * ambangnya terlalu ketat, atau ada yang salah di lapangan.
     */
    public function test_beyond_the_limit_it_locks_with_a_written_reason(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        // Izinnya diberikan LEBIH DULU oleh QC, bukan diketik saat mengunci.
        // Keputusan Owner 7 September 2026 -- lihat catatan di
        // `Repack::grantShrinkOverride()`.
        $repack->grantShrinkOverride('Karkasnya memang berlemak tebal, dibuang banyak.', $this->user->id);

        $repack->fresh()->lock();

        $tersimpan = $repack->fresh();

        $this->assertTrue($tersimpan->kunci);
        $this->assertSame('Karkasnya memang berlemak tebal, dibuang banyak.', $tersimpan->yield_override_reason);
        $this->assertSame($this->user->id, $tersimpan->yield_override_by);
        $this->assertNotNull($tersimpan->yield_override_at);
        $this->assertTrue($tersimpan->shrinkLimitWasOverridden());
    }

    /**
     * Alasan yang cuma spasi bukan alasan.
     */
    public function test_a_blank_reason_does_not_count_as_a_reason(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        $this->expectException(\RuntimeException::class);

        $repack->lock('   ', $this->user->id);
    }

    /**
     * Hasil LEBIH BERAT daripada bahannya selalu di luar batas.
     *
     * Itu mustahil secara fisik dan hampir pasti salah ketik -- tidak ada
     * persentase yang bisa membenarkannya, bahkan ambang 100%.
     */
    public function test_output_heavier_than_input_is_never_within_any_limit(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 100, $this->user->id);

        $repack = $this->repack([100], [500]);

        $this->assertSame(-400.0, $repack->shrinkWeight());
        $this->assertFalse($repack->isWithinShrinkLimit());
    }

    // =====================================================================
    // Syarat lain yang sudah ada di satu rumah
    // =====================================================================

    public function test_a_repack_without_input_cannot_be_locked(): void
    {
        $repack = $this->repack([], [50]);

        $this->expectException(\RuntimeException::class);

        $repack->lock();
    }

    public function test_a_repack_without_output_cannot_be_locked(): void
    {
        $repack = $this->repack([100], []);

        $this->expectException(\RuntimeException::class);

        $repack->lock();
    }

    public function test_a_locked_repack_cannot_be_locked_twice(): void
    {
        $repack = $this->repack([100], [95]);
        $repack->lock();

        $this->expectException(\RuntimeException::class);

        $repack->fresh()->lock();
    }

    /**
     * Membuka kunci ikut melepas jejak penembusannya.
     *
     * Begitu dokumennya bisa diubah lagi, alasan yang dulu menyertai angka
     * lama tidak lagi menjelaskan angka yang sekarang.
     */
    public function test_unlocking_clears_the_override_trace(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);
        $repack->grantShrinkOverride('Alasan lama', $this->user->id);
        $repack->fresh()->lock();

        $repack->fresh()->unlock();

        $tersimpan = $repack->fresh();

        $this->assertFalse($tersimpan->kunci);
        $this->assertNull($tersimpan->yield_override_reason);
        $this->assertNull($tersimpan->yield_override_by);
        $this->assertNull($tersimpan->yield_override_at);
        $this->assertFalse($tersimpan->shrinkLimitWasOverridden());
    }

    // =====================================================================
    // Ambangnya
    // =====================================================================

    /**
     * Ambang yang dikosongkan mematikan gerbangnya lagi.
     */
    public function test_clearing_the_limit_turns_the_gate_off_again(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);
        $repack = $this->repack([100], [80]);
        $this->assertFalse($repack->isWithinShrinkLimit());

        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, null, $this->user->id);

        $this->assertNull(Repack::shrinkLimitPercent());
        $this->assertTrue($repack->fresh()->isWithinShrinkLimit());
    }

    // =====================================================================
    // Izin QC
    // =====================================================================

    /**
     * Yang di dalam batas dikunci LANGSUNG, tanpa siapa pun mengizinkan.
     *
     * Keputusan Owner: "jika susutnya diambang batas user bisa langsung
     * lock". Gerbangnya cuma menyala untuk yang di luar batas -- kalau tidak,
     * setiap dokumen menunggu tanda tangan dan pekerjaan berhenti.
     */
    public function test_within_the_limit_it_locks_without_any_approval(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [95]);

        $repack->lock();

        $this->assertTrue($repack->fresh()->kunci);
        $this->assertFalse($repack->fresh()->shrinkLimitWasOverridden());
    }

    /**
     * Yang di luar batas DITOLAK, dan penolakannya menyebut apa yang kurang.
     *
     * Keputusan Owner: yang mengerjakan mengklik Lock lalu mendapat
     * peringatan bahwa ia perlu izin QC -- bukan menghadapi tombol mati.
     * Tombol mati memberi tahu bahwa sesuatu tidak bisa dikerjakan;
     * peringatan memberi tahu APA yang harus dikerjakan berikutnya.
     */
    public function test_beyond_the_limit_the_lock_is_refused_until_qc_approves(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        try {
            $repack->lock();

            $this->fail('Repack di luar batas terkunci tanpa izin QC.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('QC', $e->getMessage());
        }

        $this->assertFalse($repack->fresh()->kunci);

        // Sesudah QC mengizinkan, barulah bisa.
        $repack->grantShrinkOverride('Lemaknya tebal, banyak yang dibuang.', $this->user->id);

        $repack->fresh()->lock();

        $this->assertTrue($repack->fresh()->kunci);
    }

    /** Izin tanpa alasan bukan izin. */
    public function test_an_approval_without_a_reason_is_refused(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        $this->expectException(\RuntimeException::class);

        $repack->grantShrinkOverride('   ', $this->user->id);
    }

    /** Yang masih di dalam batas tidak bisa "diizinkan" -- tidak ada yang perlu. */
    public function test_nothing_within_the_limit_can_be_approved(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [95]);

        $this->expectException(\RuntimeException::class);

        $repack->grantShrinkOverride('Tidak perlu sebenarnya.', $this->user->id);
    }

    /**
     * Izin QC GUGUR begitu angkanya berubah.
     *
     * Ini jebakan yang paling mungkin terjadi dan paling sulit terlihat: QC
     * mengizinkan susut 12%, lalu ada yang menyunting hasilnya sehingga
     * susutnya menjadi 40%, dan dokumennya dikunci dengan izin yang sama --
     * tanpa satu pun gejala.
     *
     * Izin QC menyertai ANGKA yang dilihat QC saat memberikannya.
     */
    public function test_the_approval_lapses_when_the_numbers_change(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        $repack->grantShrinkOverride('Susut 20% masih bisa dijelaskan.', $this->user->id);

        $this->assertTrue($repack->fresh()->shrinkLimitWasOverridden());

        // Hasilnya disunting: susutnya melonjak.
        $repack->results()->first()->update(['weight' => 40]);

        $this->assertFalse(
            $repack->fresh()->shrinkLimitWasOverridden(),
            'Izin QC masih berlaku untuk angka yang sudah berubah.',
        );

        $this->expectException(\RuntimeException::class);

        $repack->fresh()->lock();
    }

    /** Menambah bahan pun menggugurkan izinnya. */
    public function test_adding_material_also_lapses_the_approval(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        $repack->grantShrinkOverride('Sudah diperiksa.', $this->user->id);

        $this->assertTrue($repack->fresh()->shrinkLimitWasOverridden());

        $this->tambahBahan($repack, 50);

        $this->assertFalse($repack->fresh()->shrinkLimitWasOverridden());
    }

    /** Repack yang sudah terkunci tidak bisa diizinkan lagi. */
    public function test_a_locked_repack_cannot_be_approved_again(): void
    {
        Setting::write(Setting::REPACK_MAX_SHRINK_PERCENT, 10, $this->user->id);

        $repack = $this->repack([100], [80]);

        $repack->grantShrinkOverride('Alasan pertama.', $this->user->id);
        $repack->fresh()->lock();

        $this->expectException(\RuntimeException::class);

        $repack->fresh()->grantShrinkOverride('Alasan kedua.', $this->user->id);
    }
}
