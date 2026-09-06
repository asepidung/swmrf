<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Buku besar material harus bisa diperiksa, sama seperti buku besar daging.
 *
 * `stock:reconcile` hanya memeriksa daging. Bahan penolong punya buku
 * besarnya sendiri di `material_stock_movements`, dan kalau buku besar itu
 * bolong tidak ada satu pun yang memberitahu -- layarnya tetap menampilkan
 * angka, hanya angkanya tidak lagi bisa dipertanggungjawabkan.
 *
 * Bentuk datanya berbeda dari sisi daging. Stok daging dicatat per BARCODE
 * dan barisnya dihapus begitu barangnya keluar; stok material dicatat per
 * JENIS dengan satu angka `qty` yang disunting naik-turun, dan barisnya tidak
 * pernah dihapus. Perbandingannya karena itu cukup per material, tanpa
 * dimensi gudang maupun grade.
 *
 * Yang dibuktikan di sini bukan bahwa perintahnya jalan, melainkan bahwa ia
 * benar-benar MEMBANDINGKAN: sebuah perintah pemeriksa yang selalu menjawab
 * "bersih" lebih berbahaya daripada tidak ada pemeriksa sama sekali, karena
 * ia memberi rasa aman yang tidak ada dasarnya.
 */
class ReconcileMaterialStockTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = MaterialCategory::create(['name' => 'KEMASAN', 'is_active' => true]);
        $satuan = MaterialUnit::create(['name' => 'PCS', 'is_active' => true]);

        $this->material = Material::create([
            'name' => 'PLASTIK VAKUM',
            'code' => 'MTR-001',
            'material_category_id' => $kategori->id,
            'material_unit_id' => $satuan->id,
            'is_active' => true,
        ]);
    }

    /** Buku besar yang utuh dinyatakan bersih. */
    public function test_a_whole_ledger_is_reported_clean(): void
    {
        StockService::adjustStock($this->material->id, 10, 'MASUK', 'DOC-1', 'terima');
        StockService::adjustStock($this->material->id, -3, 'KELUAR', 'DOC-2', 'pakai');

        $this->artisan('stock:reconcile-material')
            ->expectsOutputToContain('BERSIH')
            ->assertExitCode(0);
    }

    /**
     * Stok yang disunting tanpa mencatat pergerakannya KETAHUAN.
     *
     * Inilah satu-satunya hal yang membuat perintah ini ada. Kalau bagian ini
     * tidak diuji, yang tersisa cuma perintah yang selalu bilang "bersih".
     */
    public function test_a_stock_edited_without_a_movement_is_caught(): void
    {
        StockService::adjustStock($this->material->id, 10, 'MASUK', 'DOC-1', 'terima');

        // Disunting LANGSUNG, melewati StockService -- persis bentuk kerusakan
        // yang dicari perintah ini.
        DB::table('material_stocks')
            ->where('material_id', $this->material->id)
            ->update(['qty' => 99]);

        // Bukan cuma "gagal": jumlah yang meleset dan besar selisihnya ikut
        // diperiksa, supaya perintahnya terbukti benar-benar MENGHITUNG dan
        // bukan sekadar gagal karena hal lain.
        //
        // 10 di buku besar lawan 99 di stok: selisihnya 89.
        $this->artisan('stock:reconcile-material')
            ->expectsOutputToContain('Yang meleset            : 1')
            ->expectsOutputToContain('Jumlah selisih mutlak   : 89.00')
            ->expectsOutputToContain('MELESET')
            ->assertExitCode(1);
    }

    /** Posisi pada tanggal tertentu tidak menghitung yang sesudahnya. */
    public function test_the_position_on_a_date_ignores_what_came_after(): void
    {
        StockService::adjustStock($this->material->id, 10, 'MASUK', 'DOC-1', 'terima');

        DB::table('material_stock_movements')->update(['created_at' => now()->subDays(3)]);

        StockService::adjustStock($this->material->id, 5, 'MASUK', 'DOC-2', 'terima lagi');

        $this->artisan('stock:reconcile-material', ['--date' => now()->subDays(2)->toDateString()])
            ->expectsOutputToContain('10.00')
            ->assertExitCode(0);
    }

    /** Tanggal yang tidak terbaca ditolak, bukan diam-diam dianggap hari ini. */
    public function test_an_unreadable_date_is_refused(): void
    {
        $this->artisan('stock:reconcile-material', ['--date' => 'kemarin sore'])
            ->expectsOutputToContain('Tanggalnya tidak terbaca')
            ->assertExitCode(2);
    }

    /**
     * Perintahnya tidak menulis apa pun.
     *
     * Ia dijalankan di server yang sedang dipakai orang. Sebuah pemeriksa
     * yang ikut mengubah data bukan pemeriksa.
     */
    public function test_the_command_writes_nothing(): void
    {
        $sumber = file_get_contents(app_path('Console/Commands/ReconcileMaterialStock.php'));

        foreach (['->update(', '->insert(', '->delete(', '->save(', '::create('] as $penulisan) {
            $this->assertStringNotContainsString(
                $penulisan,
                $sumber,
                "Perintah pemeriksa ini memuat `$penulisan`. Ia dijalankan di server yang sedang "
                .'dipakai orang dan tidak boleh mengubah apa pun.',
            );
        }
    }
}
