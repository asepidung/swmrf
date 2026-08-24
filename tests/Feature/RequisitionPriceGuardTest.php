<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ApproveFinanceProductRequisition;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Barang tanpa harga tidak boleh lolos ke tahap berikutnya.
 *
 * Keputusan Project Owner: purchasing-lah yang mengisi harga, karena dialah
 * yang tahu harga supplier. Pemohon tidak dipantulkan bolak-balik hanya
 * karena harga kosong. Finance dikunci sebagai lapis kedua — PO bernilai nol
 * akan menciptakan utang palsu dan mengacaukan perhitungan TOP.
 */
class RequisitionPriceGuardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer',
            'username' => 'programmer_guard',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'H DONI',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);

        $this->product = Product::create([
            'name' => 'CUBEROLL',
            'code' => '100100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    protected function makeRequisition(string $status, float $price): ProductRequisition
    {
        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);

        $requisition->items()->create([
            'product_id' => $this->product->id,
            'qty' => 300,
            'price' => $price,
            'subtotal' => 300 * $price,
        ]);

        return $requisition;
    }

    /** @test */
    public function it_blocks_purchasing_approval_while_an_item_has_no_price()
    {
        $requisition = $this->makeRequisition('Requested', 0);

        Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $this->assertSame(
            'Requested',
            $requisition->fresh()->status,
            'Dokumen berharga nol tidak boleh berpindah ke Pending Finance.'
        );
    }

    /** @test */
    public function it_names_the_item_that_is_missing_a_price()
    {
        $requisition = $this->makeRequisition('Requested', 0);

        Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $titles = collect(session('filament.notifications', []))->pluck('title')->all();
        $bodies = collect(session('filament.notifications', []))->pluck('body')->all();

        $this->assertNotEmpty($titles, 'Tidak ada notifikasi peringatan sama sekali.');
        $this->assertStringContainsString(
            'CUBEROLL',
            implode(' ', $bodies),
            'Pesan wajib menyebut barang mana yang harganya kosong.'
        );
    }

    /** @test */
    public function it_lets_purchasing_approve_once_every_item_has_a_price()
    {
        $requisition = $this->makeRequisition('Requested', 250000);

        Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $this->assertSame('Pending Finance', $requisition->fresh()->status);
    }

    /**
     * Kolom harga ditandai wajib HANYA di halaman review purchasing, sehingga
     * kesalahan muncul langsung di kolomnya, bukan sekadar toast setelah
     * menekan Approve. Di form pemohon kolom itu tetap opsional.
     *
     * @test
     */
    public function it_marks_price_as_required_only_on_the_purchasing_review_page()
    {
        $requisition = $this->makeRequisition('Requested', 0);

        $page = Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->instance();

        $price = null;
        foreach ($page->form->getFlatFields(withHidden: true) as $key => $field) {
            if (str_ends_with($key, 'price')) {
                $price = $field;
                break;
            }
        }

        $this->assertNotNull($price, 'Kolom price tidak ditemukan di halaman review.');
        $this->assertTrue($price->isRequired(), 'Harga wajib ditandai required di review purchasing.');
    }

    /**
     * Di form pemohon harga tetap boleh kosong; itu tanggung jawab purchasing.
     *
     * @test
     */
    public function it_keeps_price_optional_on_the_requester_form()
    {
        $page = Livewire::actingAs($this->user)
            ->test(\App\Filament\Admin\Resources\ProductRequisitionResource\Pages\CreateProductRequisition::class)
            ->instance();

        $price = null;
        foreach ($page->form->getFlatFields(withHidden: true) as $key => $field) {
            if (str_ends_with($key, 'price')) {
                $price = $field;
                break;
            }
        }

        if ($price === null) {
            $this->markTestSkipped('Baris item belum terbentuk di form create.');
        }

        $this->assertFalse($price->isRequired(), 'Harga tidak boleh wajib di form pemohon.');
    }

    /**
     * Penjagaan harga tidak ada gunanya bila ada jalur pintas yang melewatinya.
     *
     * Dulu halaman View memuat modal Approve/Reject yang menulis status
     * langsung tanpa memeriksa harga sama sekali — dan justru itulah jalur
     * yang dipakai operator. Halaman View kini murni baca-saja: tombolnya
     * mengarahkan ke halaman Review dan Finance Approval.
     *
     * @test
     *
     * @dataProvider viewPages
     */
    public function it_keeps_view_pages_free_of_decision_shortcuts(string $file)
    {
        $source = file_get_contents(app_path($file));

        foreach (["'Pending Finance'", "'PO Created'"] as $status) {
            $writes = preg_match_all("/'status'\s*=>\s*" . preg_quote($status, '/') . "/", $source);

            $this->assertSame(
                0,
                $writes,
                "Halaman View {$file} menulis status {$status} langsung. "
                . 'Itu jalur pintas yang melewati seluruh validasi harga.'
            );
        }
    }

    /** @return array<string, array<int, string>> */
    public static function viewPages(): array
    {
        return [
            'Request Beef' => ['Filament/Admin/Resources/ProductRequisitionResource/Pages/ViewProductRequisition.php'],
            'Request Material' => ['Filament/Admin/Resources/MaterialRequisitionResource/Pages/ViewMaterialRequisition.php'],
        ];
    }

    /**
     * Halaman Finance Approval sempat TIDAK terdaftar di getPages(), sehingga
     * tidak punya rute sama sekali dan penjagaan di dalamnya menjadi kode mati.
     * Seluruh persetujuan finance mengalir lewat modal di halaman View.
     *
     * @test
     */
    public function it_registers_a_route_for_the_finance_approval_page()
    {
        foreach (['product-requisitions', 'material-requisitions'] as $slug) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has("filament.admin.resources.{$slug}.approve-finance"),
                "Halaman Finance Approval untuk {$slug} tidak punya rute, jadi penjagaannya tidak pernah terpakai."
            );
        }
    }

    /**
     * Lapis kedua: meski purchasing sudah dikunci, finance tetap memeriksa.
     * Data lama atau perubahan langsung lewat database bisa lolos dari lapis
     * pertama.
     *
     * @test
     */
    public function it_blocks_finance_approval_while_an_item_has_no_price()
    {
        $requisition = $this->makeRequisition('Pending Finance', 0);

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $this->assertSame(
            'Pending Finance',
            $requisition->fresh()->status,
            'PO tidak boleh terbit dari dokumen berharga nol.'
        );

        $this->assertSame(
            0,
            \App\Models\PurchaseProduct::where('product_requisition_id', $requisition->id)->count(),
            'PO bernilai nol tidak boleh terbentuk.'
        );
    }
}
