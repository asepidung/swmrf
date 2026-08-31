<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PayableResource;
use App\Filament\Admin\Resources\PayableResource\Pages\ListPayables;
use App\Models\CattleReceiving;
use App\Models\GoodsReceiptMaterial;
use App\Models\GoodsReceiptProduct;
use App\Models\Payable;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daftar hutang: ramping, berkategori, dan bisa disaring per jenis pembelian.
 *
 * Keputusan Project Owner. Yang dicari orang di daftar hutang adalah siapa,
 * berapa SISA, dan kapan jatuh tempo -- Total dan Paid bisa dihitung ulang
 * dari sisa dan tempatnya di halaman detail.
 *
 * Yang justru tidak ada sebelumnya: jenis pembeliannya. Tanpa itu, hutang
 * pembelian sapi tidak bisa dipisahkan dari pembelian daging atau barang
 * sama sekali.
 */
class PayableCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Finance',
            'username' => 'finance_category',
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
    }

    private function makePayable(string $type, string $number): Payable
    {
        return Payable::create([
            'payableable_type' => $type,
            'payableable_id' => 1,
            'supplier_id' => $this->supplier->id,
            'document_number' => $number,
            'amount' => 1_000_000,
            'paid_amount' => 0,
            'balance' => 1_000_000,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);
    }

    /** Ketiga jenis pembelian punya sebutan yang bisa dibaca manusia. */
    public function test_every_purchase_type_has_a_readable_label(): void
    {
        $this->assertSame('Cattle Purchase', Payable::sourceLabel(CattleReceiving::class));
        $this->assertSame('Meat Purchase', Payable::sourceLabel(GoodsReceiptProduct::class));
        $this->assertSame('Goods Purchase', Payable::sourceLabel(GoodsReceiptMaterial::class));

        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        $this->assertSame('Pembelian Sapi', $id['Cattle Purchase']);
        $this->assertSame('Pembelian Daging', $id['Meat Purchase']);
        $this->assertSame('Pembelian Barang', $id['Goods Purchase']);
    }

    /**
     * Jenis yang belum terdaftar dikembalikan apa adanya.
     *
     * Sengaja BUKAN tanda hubung: kalau suatu saat ada sumber hutang baru
     * yang lupa didaftarkan, nama kelasnya di layar adalah petunjuk yang
     * menunjukkan persis apa yang harus ditambahkan. Tanda hubung akan
     * menyembunyikannya.
     */
    public function test_an_unregistered_source_shows_itself_instead_of_hiding(): void
    {
        $this->assertSame('App\Models\SomethingNew', Payable::sourceLabel('App\Models\SomethingNew'));
    }

    /** Kolom kategori ada di daftar, dan bisa diurutkan. */
    public function test_the_list_shows_the_category_and_can_sort_by_it(): void
    {
        $this->makePayable(CattleReceiving::class, 'CR#26001');

        Livewire::test(ListPayables::class)
            ->assertTableColumnExists('payableable_type')
            ->assertCanRenderTableColumn('payableable_type')
            ->assertTableColumnStateSet('payableable_type', CattleReceiving::class, Payable::first());
    }

    /** Dan bisa disaring, supaya hutang sapi bisa dipisahkan. */
    public function test_the_list_can_be_filtered_down_to_one_purchase_type(): void
    {
        $cattle = $this->makePayable(CattleReceiving::class, 'CR#26001');
        $meat = $this->makePayable(GoodsReceiptProduct::class, 'GRB#26001');
        $goods = $this->makePayable(GoodsReceiptMaterial::class, 'GRM#26001');

        Livewire::test(ListPayables::class)
            ->filterTable('payableable_type', CattleReceiving::class)
            ->assertCanSeeTableRecords([$cattle])
            ->assertCanNotSeeTableRecords([$meat, $goods]);
    }

    /** Total dan Paid tidak lagi di daftar -- keduanya tetap ada di detail. */
    public function test_the_list_drops_the_two_intermediate_amounts_but_keeps_them_in_the_detail(): void
    {
        $this->makePayable(CattleReceiving::class, 'CR#26001');

        Livewire::test(ListPayables::class)
            ->assertTableColumnDoesNotExist('amount')
            ->assertTableColumnDoesNotExist('paid_amount')
            // Sisa hutang justru yang paling dicari, jadi ia tetap.
            ->assertTableColumnExists('balance');

        $form = file_get_contents(app_path('Filament/Admin/Resources/PayableResource.php'));

        $this->assertStringContainsString("TextInput::make('amount')", $form);
        $this->assertStringContainsString("TextInput::make('paid_amount')", $form);
    }

    /**
     * Petanya cuma satu, dipakai kolom, filter, dan halaman detail.
     *
     * Sebelumnya `payableable_type` dipetakan langsung di form dan hanya
     * untuk GR Material, sehingga GR Beef dan penerimaan sapi tampil sebagai
     * nama kelas mentah di layar pengguna.
     */
    public function test_the_detail_page_no_longer_maps_the_types_on_its_own(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/PayableResource.php'));

        $this->assertStringNotContainsString("'App\\Models\\GoodsReceiptMaterial' =>", $source);
        $this->assertStringContainsString('Payable::sourceLabel', $source);
    }
}
