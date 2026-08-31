<?php

namespace Tests\Feature;

use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleReceivingItem;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Satu eartag hanya boleh ada sekali, di seluruh riwayat.
 *
 * Keputusan Project Owner: eartag tidak boleh kembar dalam satu dokumen
 * penerimaan, dan lebih baik lagi tidak boleh kembar sama sekali dengan
 * riwayat. Seekor sapi datang sekali; nomor telinganya adalah identitasnya.
 *
 * Perhatikan bedanya dengan `barcode` pada tabel transaksional, yang di
 * proyek ini SENGAJA tanpa unique index -- barang hasil potong keluar-masuk
 * berkali-kali lintas dokumen. Sapi hidup tidak begitu.
 *
 * Validasi form untuk kedua hal itu sudah ada sejak awal dan tetap
 * dipertahankan sebagai fast-path. Yang diuji di sini adalah lapisan yang
 * MENGIKAT: form bisa dilewati oleh import, seeder, tinker, atau sekadar dua
 * operator yang menyimpan bersamaan.
 */
class CattleEartagUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CattleClass $class;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator Kandang',
            'username' => 'operator_eartag',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->supplier = Supplier::create([
            'name' => 'FEEDLOT JAYA',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);

        $this->class = CattleClass::create(['name' => 'BALI', 'is_active' => true]);
    }

    private function makeReceiving(): CattleReceiving
    {
        $po = PurchaseCattle::create([
            'supplier_id' => $this->supplier->id,
            'shipping_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        return CattleReceiving::create([
            'purchase_cattle_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);
    }

    private function addCattle(CattleReceiving $receiving, string $eartag): CattleReceivingItem
    {
        return $receiving->items()->create([
            'cattle_class_id' => $this->class->id,
            'eartag' => $eartag,
            'initial_weight' => 400,
        ]);
    }

    /** Penjagaannya harus hidup di database, bukan cuma di form. */
    public function test_the_database_itself_refuses_a_duplicate_eartag(): void
    {
        $this->assertTrue(
            collect(Schema::getIndexes('cattle_receiving_items'))
                ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['eartag']),
            'eartag tidak punya unique index; validasi form saja bisa dilewati import, seeder, atau tinker.',
        );
    }

    public function test_the_same_eartag_cannot_appear_twice_in_one_document(): void
    {
        $receiving = $this->makeReceiving();
        $this->addCattle($receiving, 'ID-0001');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->addCattle($receiving, 'ID-0001');
    }

    /** Dan tidak boleh muncul lagi di dokumen penerimaan mana pun berikutnya. */
    public function test_the_same_eartag_cannot_reappear_in_another_document(): void
    {
        $this->addCattle($this->makeReceiving(), 'ID-0002');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->addCattle($this->makeReceiving(), 'ID-0002');
    }

    /**
     * Huruf kecil dan besar adalah sapi yang SAMA.
     *
     * Kolom eartag di form memakai `text-transform: uppercase`, yang hanya
     * mengubah tampilan: operator mengetik `a001`, layar menunjukkan `A001`,
     * dan yang terkirim tetap `a001`. Tanpa normalisasi di model, apakah
     * duplikatnya tertangkap bergantung pada collation -- MySQL menangkapnya,
     * SQLite tidak. Perilaku yang berbeda antara server dan testing adalah
     * kondisi terburuk: test hijau untuk sesuatu yang tidak berlaku di
     * tempat yang sebenarnya.
     */
    public function test_eartags_are_normalised_so_case_and_spacing_cannot_smuggle_a_duplicate(): void
    {
        $item = $this->addCattle($this->makeReceiving(), '  id-0003 ');

        $this->assertSame('ID-0003', $item->fresh()->eartag);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->addCattle($this->makeReceiving(), 'Id-0003');
    }

    /** Halaman penyimpanan menerjemahkan penolakan itu jadi pesan, bukan halaman error. */
    public function test_the_save_pages_translate_the_violation_into_a_message(): void
    {
        foreach ([
            'CreateCattleReceiving.php',
            'EditCattleReceiving.php',
        ] as $page) {
            $source = file_get_contents(
                app_path('Filament/Admin/Resources/CattleReceivingResource/Pages/'.$page)
            );

            $this->assertStringContainsString('SavesUniqueEartags', $source, $page);
            $this->assertStringContainsString('saveGuardingEartags', $source, $page);
        }

        $trait = file_get_contents(
            app_path('Filament/Admin/Resources/CattleReceivingResource/Concerns/SavesUniqueEartags.php')
        );

        // Penyimpanan harus di dalam transaksi, dan penolakannya ditangkap.
        $this->assertStringContainsString('DB::transaction', $trait);
        $this->assertStringContainsString('UniqueConstraintViolationException', $trait);
    }

    /** Fast-path di form tetap ada -- ia yang memberi pesan spesifik saat mengetik. */
    public function test_the_form_still_checks_duplicates_while_typing(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CattleReceivingResource.php'));

        $this->assertStringContainsString('Duplicate eartag in this form', $source);
        $this->assertStringContainsString('Eartag is already registered', $source);
    }
}
