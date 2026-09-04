<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesReturnResource\Pages\EditSalesReturn;
use App\Filament\Admin\Resources\SalesReturnResource\Pages\InputReturnItems;
use App\Filament\Admin\Resources\SalesReturnResource\Pages\ViewSalesReturn;
use App\Models\BeefStock;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Retur penjualan: barang yang kembali dari pelanggan.
 *
 * Yang dijaga di sini semuanya berpusat pada satu hal -- STOK. Menyetujui
 * sebuah retur memasukkan tiap barangnya ke gudang, membuka kuncinya menarik
 * mereka kembali keluar. Salah di sini berarti gudang mencatat daging yang
 * tidak ada, atau kehilangan daging yang ada.
 */
class SalesReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Warehouse $jonggol;

    private Warehouse $perum;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Petugas Retur', 'username' => 'petugas_retur',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);

        $this->customer = Customer::create([
            'name' => 'SATE KHAS SENAYAN',
            'customer_segment_id' => $segment->id,
            'address' => 'Sudirman Jakarta',
            'top' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);

        $this->product = Product::create([
            'name' => 'WAGYU RIBEYE', 'code' => 'MT00200',
            'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true,
        ]);

        // Gudang berid 1 sengaja DINONAKTIFKAN. Kalau ada kode yang masih
        // memaku gudang ke angka 1, ia akan memilih gudang yang tidak boleh
        // dipakai -- dan tesnya yang menangkap, bukan penggunanya.
        $this->jonggol = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => false]);
        $this->perum = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);

        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    private function beriIzin(string ...$izin): void
    {
        foreach ($izin as $nama) {
            $this->user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $nama],
                    ['module_name' => 'Sales Returns', 'description' => $nama],
                )->id
            );
        }

        $this->actingAs($this->user->fresh());
    }

    /**
     * Satu rangkaian lengkap: SO -> Tally -> Surat Jalan -> Retur.
     */
    private function suratJalan(string ...$barcode): DeliveryOrder
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'locked']);

        foreach ($barcode as $kode) {
            TallyItem::create([
                'tally_id' => $tally->id,
                'barcode' => $kode,
                'product_id' => $this->product->id,
                // Barangnya berasal dari PERUM, bukan dari gudang berid 1.
                'warehouse_id' => $this->perum->id,
                'grade_id' => $this->grade->id,
                'weight' => 22.5,
                'qty_pcs' => 8,
                'pack_date' => now()->toDateString(),
                'origin' => '1',
            ]);
        }

        return DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'driver' => 'Joko',
            'status' => 'Delivered',
        ]);
    }

    /**
     * Halaman Input Barang dijaga policy resource-nya. Tanpa izin ini ia
     * membalas 403, dan tes penjaga barcode gagal karena alasan yang salah.
     */
    private function bolehMemakaiHalamanInput(): void
    {
        $this->beriIzin('view_sales_returns', 'edit_sales_returns');
    }

    private function retur(?DeliveryOrder $do = null, string $status = 'Draft'): SalesReturn
    {
        return SalesReturn::create([
            'return_date' => now()->toDateString(),
            'delivery_order_id' => $do?->id,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function barang(SalesReturn $retur, string $barcode): SalesReturnItem
    {
        return SalesReturnItem::create([
            'sales_return_id' => $retur->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->perum->id,
            'grade_id' => $this->grade->id,
            'barcode' => $barcode,
            'weight' => 22.5,
            'qty_pcs' => 8,
            'pack_date' => now()->toDateString(),
            'origin' => '1',
        ]);
    }

    // =====================================================================
    // Menyetujui dan membuka kunci
    // =====================================================================

    public function test_approving_puts_every_item_into_stock(): void
    {
        $retur = $this->retur();
        $this->barang($retur, 'BARCODE-A');
        $this->barang($retur, 'BARCODE-B');

        $retur->refresh()->approve();

        $this->assertSame('Approved', $retur->fresh()->status);
        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => 'BARCODE-A',
            'warehouse_id' => $this->perum->id,
            'status' => 'IN_STOCK',
        ]);
        $this->assertDatabaseHas('beef_stock_movements', [
            'barcode' => 'BARCODE-B',
            'transaction_type' => 'SALES_RETURN',
            'reference_document' => $retur->return_number,
        ]);
    }

    public function test_an_empty_return_cannot_be_approved(): void
    {
        $retur = $this->retur();

        $this->expectException(\RuntimeException::class);

        $retur->approve();
    }

    public function test_an_approved_return_cannot_be_approved_twice(): void
    {
        $retur = $this->retur();
        $this->barang($retur, 'BARCODE-A');
        $retur->refresh()->approve();

        $this->expectException(\RuntimeException::class);

        $retur->fresh()->approve();
    }

    public function test_unlocking_pulls_every_item_back_out_of_stock(): void
    {
        $retur = $this->retur();
        $this->barang($retur, 'BARCODE-A');
        $retur->refresh()->approve();

        $retur->fresh()->unlock();

        $this->assertSame('Draft', $retur->fresh()->status);
        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'BARCODE-A']);
        $this->assertDatabaseHas('beef_stock_movements', [
            'barcode' => 'BARCODE-A',
            'transaction_type' => 'CANCEL_SALES_RETURN',
        ]);
    }

    /**
     * Semua barang diperiksa dulu, baru satu pun ditarik.
     *
     * Kalau satu barang sudah terlanjur dikirim lagi, membuka kunci separuh
     * jalan meninggalkan stok yang tidak cocok dengan dokumen mana pun: yang
     * satu sudah ditarik, yang lain masih tercatat masuk, dan returnya sendiri
     * berstatus entah apa.
     */
    public function test_unlocking_reverts_nothing_at_all_when_one_item_is_already_gone(): void
    {
        $retur = $this->retur();
        $this->barang($retur, 'BARCODE-A');
        $this->barang($retur, 'BARCODE-B');
        $retur->refresh()->approve();

        // BARCODE-B sudah dikirim lagi ke pelanggan lain.
        BeefStock::where('barcode', 'BARCODE-B')->delete();

        try {
            $retur->fresh()->unlock();
            $this->fail('Membuka kunci seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('BARCODE-B', $e->getMessage());
        }

        // Yang masih ada TIDAK boleh ikut tertarik, dan statusnya tidak berubah.
        $this->assertDatabaseHas('beef_stocks', ['barcode' => 'BARCODE-A']);
        $this->assertSame('Approved', $retur->fresh()->status);
        $this->assertDatabaseMissing('beef_stock_movements', [
            'transaction_type' => 'CANCEL_SALES_RETURN',
            'reference_document' => $retur->return_number,
        ]);
    }

    // =====================================================================
    // Izin
    // =====================================================================

    public function test_approving_needs_its_own_permission_not_merely_edit(): void
    {
        $retur = $this->retur();
        $this->barang($retur, 'BARCODE-A');

        $this->beriIzin('view_sales_returns', 'edit_sales_returns');

        Livewire::test(EditSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionHidden('Approve Return');

        $this->beriIzin('approve_sales_returns');

        Livewire::test(EditSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionVisible('Approve Return');
    }

    public function test_unlocking_needs_its_own_permission_on_both_pages(): void
    {
        $retur = $this->retur();
        $this->barang($retur, 'BARCODE-A');
        $retur->refresh()->approve();

        $this->beriIzin('view_sales_returns', 'edit_sales_returns', 'approve_sales_returns');

        // Menyetujui saja tidak cukup untuk membatalkan.
        Livewire::test(EditSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionHidden('Unlock Return');
        Livewire::test(ViewSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionHidden('Unlock Return');

        $this->beriIzin('unlock_sales_returns');

        Livewire::test(EditSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionVisible('Unlock Return');
        Livewire::test(ViewSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionVisible('Unlock Return');
    }

    /**
     * Menghapus retur yang sudah disetujui tidak menarik stoknya kembali.
     */
    public function test_an_approved_return_cannot_be_deleted(): void
    {
        $retur = $this->retur();
        $this->barang($retur, 'BARCODE-A');
        $retur->refresh()->approve();

        // Programmer: tanpa ini Force Delete memang selalu tersembunyi, dan
        // penjaga STATUS-nya -- yang sedang diuji -- tidak pernah tersentuh.
        $this->user->update(['role' => 'programmer']);
        $this->beriIzin('view_sales_returns', 'edit_sales_returns', 'delete_sales_returns');

        Livewire::test(EditSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionHidden('delete')
            ->assertActionHidden('forceDelete');

        // Sesudah dibuka kuncinya barulah boleh dihapus.
        $retur->fresh()->unlock();

        Livewire::test(EditSalesReturn::class, ['record' => $retur->getKey()])
            ->assertActionVisible('delete');
    }

    // =====================================================================
    // Tiga pertanyaan sebelum sebuah barcode boleh diretur
    // =====================================================================

    public function test_a_barcode_that_was_never_on_this_delivery_note_is_refused(): void
    {
        $do = $this->suratJalan('BARCODE-A');
        $retur = $this->retur($do);
        $this->bolehMemakaiHalamanInput();

        Livewire::test(InputReturnItems::class, ['record' => $retur])
            ->set('dataScan.barcode', 'BARCODE-ASING')
            ->call('processScan');

        $this->assertDatabaseCount('sales_return_items', 0);
    }

    public function test_an_item_still_sitting_in_the_warehouse_cannot_be_returned(): void
    {
        $do = $this->suratJalan('BARCODE-A');
        $retur = $this->retur($do);
        $this->bolehMemakaiHalamanInput();

        BeefStock::create([
            'barcode' => 'BARCODE-A',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->perum->id,
            'grade_id' => $this->grade->id,
            'weight' => 22.5,
            'qty_pcs' => 8,
            'pack_date' => now()->toDateString(),
            'status' => 'IN_STOCK',
        ]);

        Livewire::test(InputReturnItems::class, ['record' => $retur])
            ->set('dataScan.barcode', 'BARCODE-A')
            ->call('processScan');

        $this->assertDatabaseCount('sales_return_items', 0);
    }

    public function test_a_barcode_already_on_another_draft_return_is_refused(): void
    {
        $do = $this->suratJalan('BARCODE-A');

        $returLain = $this->retur($do);
        $this->barang($returLain, 'BARCODE-A');

        $retur = $this->retur($do);
        $this->bolehMemakaiHalamanInput();

        Livewire::test(InputReturnItems::class, ['record' => $retur])
            ->set('dataScan.barcode', 'BARCODE-A')
            ->call('processScan');

        $this->assertSame(0, $retur->items()->count());
    }

    /**
     * Barang retur mendarat kembali di gudang ASALNYA.
     *
     * Gudangnya dulu dipaku ke angka 1, sehingga tiap retur menumpuk di satu
     * gudang ke mana pun sebenarnya barangnya dikembalikan. Di sini gudang
     * berid 1 bahkan sudah dinonaktifkan.
     */
    public function test_a_scanned_item_lands_back_in_the_warehouse_it_came_from(): void
    {
        $do = $this->suratJalan('BARCODE-A');
        $retur = $this->retur($do);
        $this->bolehMemakaiHalamanInput();

        Livewire::test(InputReturnItems::class, ['record' => $retur])
            ->set('dataScan.barcode', 'BARCODE-A')
            ->call('processScan');

        $this->assertDatabaseHas('sales_return_items', [
            'barcode' => 'BARCODE-A',
            'warehouse_id' => $this->perum->id,
        ]);
        $this->assertNotEquals(1, $this->perum->id, 'Gudang uji tidak boleh kebetulan berid 1.');
    }

    public function test_the_same_barcode_cannot_be_scanned_twice_on_one_return(): void
    {
        $do = $this->suratJalan('BARCODE-A');
        $retur = $this->retur($do);
        $this->bolehMemakaiHalamanInput();

        $halaman = Livewire::test(InputReturnItems::class, ['record' => $retur])
            ->set('dataScan.barcode', 'BARCODE-A')
            ->call('processScan');

        $halaman->set('dataScan.barcode', 'BARCODE-A')->call('processScan');

        $this->assertSame(1, $retur->items()->count());
    }

    // =====================================================================
    // Urutan barcode barang timbang manual
    // =====================================================================

    /**
     * Urutan dibaca dari bagian sesudah awalannya, bukan dari panjang barcode.
     *
     * Yang lama menolak menghitung apa pun yang panjangnya kurang dari 26
     * karakter, lalu memulai dari 1 lagi -- membuat barcode kembar.
     */
    public function test_the_manual_barcode_counter_does_not_restart_on_a_shorter_neighbour(): void
    {
        $retur = $this->retur();
        $this->bolehMemakaiHalamanInput();

        $prefix = '4'.now()->format('dmy');
        $this->barang($retur, $prefix.'PENDEK0007');

        Livewire::test(InputReturnItems::class, ['record' => $retur])
            ->set('dataWeigh.warehouse_id', $this->perum->id)
            ->set('dataWeigh.product_id', $this->product->id)
            ->set('dataWeigh.grade_id', $this->grade->id)
            ->set('dataWeigh.pack_date', now()->toDateString())
            ->set('dataWeigh.qty_pcs_combined', '22.5/8')
            ->call('processWeigh');

        $baru = $retur->items()->where('is_repacked', true)->first();

        $this->assertNotNull($baru, 'Barang timbang manual gagal tersimpan.');
        $this->assertSame('0008', substr($baru->barcode, -4));
    }

    // =====================================================================
    // Penjaga pola
    // =====================================================================

    /**
     * Rutin stok retur hanya boleh tinggal di satu tempat.
     *
     * Ia pernah disalin utuh ke TIGA halaman -- Edit, View, dan Input Barang --
     * masing-masing dengan penjagaan izin yang berbeda, sehingga menambal yang
     * satu meninggalkan dua lainnya terbuka.
     */
    public function test_no_sales_return_page_moves_stock_on_its_own(): void
    {
        $halaman = glob(app_path('Filament/Admin/Resources/SalesReturnResource/Pages/*.php'));

        $this->assertNotEmpty($halaman);

        foreach ($halaman as $berkas) {
            $isi = file_get_contents($berkas);

            $this->assertStringNotContainsString(
                'BeefStockMovement::create',
                $isi,
                basename($berkas).' menggerakkan stok sendiri; pakai SalesReturn::approve()/unlock().'
            );
        }
    }
}
