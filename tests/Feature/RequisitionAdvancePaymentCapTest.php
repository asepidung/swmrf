<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition;
use App\Filament\Admin\Resources\PurchaseProductResource\Pages\ViewPurchaseProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batas uang muka DP pada PO Beef, dan toast yang muncul dua kali.
 *
 * Uang muka yang melebihi tagihan tidak menimbulkan error apa pun. Ia baru
 * terasa jauh kemudian: saat utang dihitung, kelebihannya tidak akan pernah
 * terpakai habis, lalu menggantung selamanya sebagai uang muka semu di
 * pembukuan supplier.
 *
 * CATATAN LOKASI: form uang muka DIPINDAHKAN dari halaman Finance Approval ke
 * halaman View PO (27-28 Agustus 2026). Alasannya lurus dengan akuntansi --
 * DP dibayar saat order, dan PO adalah dokumen order itu sendiri. Test ini
 * ikut pindah menargetkan lokasi barunya.
 */
class RequisitionAdvancePaymentCapTest extends TestCase
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
            'username' => 'cap_programmer',
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

    /** Tagihannya 300 x 250.000 = 75.000.000, supplier nonPKP jadi tanpa pajak. */
    protected function makePurchaseOrder(): PurchaseProduct
    {
        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'PO Created',
        ]);

        $requisition->items()->create([
            'product_id' => $this->product->id,
            'qty' => 300,
            'price' => 250000,
            'subtotal' => 75000000,
        ]);

        $requisition->updateTotalAmount();
        $requisition->generatePurchaseOrder();

        return PurchaseProduct::where('product_requisition_id', $requisition->id)->firstOrFail();
    }

    /** @test */
    public function it_rejects_an_advance_payment_larger_than_the_bill()
    {
        $po = $this->makePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseProduct::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '80.000.000',
            ])
            ->assertHasActionErrors(['amount_input']);

        $this->assertSame(0, SupplierPayment::count());
    }

    /** @test */
    public function it_rejects_an_advance_payment_of_zero()
    {
        $po = $this->makePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseProduct::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '0',
            ])
            ->assertHasActionErrors(['amount_input']);

        $this->assertSame(0, SupplierPayment::count());
    }

    /** @test */
    public function it_accepts_an_advance_payment_equal_to_the_bill()
    {
        $po = $this->makePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseProduct::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '75.000.000',
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(75000000, SupplierPayment::sum('amount'));
    }

    /**
     * Nilai berformat WAJIB di-parse sebelum disimpan. Tanpa itu PHP membaca
     * "1.000.000" sebagai 1.0 dan uang mukanya menyusut tanpa error apa pun.
     *
     * @test
     */
    public function it_stores_a_thousand_separated_amount_at_full_value()
    {
        $po = $this->makePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseProduct::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '1.000.000',
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(1000000, SupplierPayment::sum('amount'));
    }

    /** @test */
    public function it_masks_the_payment_field_with_thousand_separators()
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/PurchaseProductResource/Pages/ViewPurchaseProduct.php',
        ));

        $this->assertStringContainsString('amount_input', $source);
        $this->assertStringContainsString('Intl.NumberFormat("id-ID")', $source);
    }

    /**
     * save() punya argumen kedua yang mengatur toast "Saved". Tanpa dimatikan,
     * pengguna melihat DUA toast sekaligus: "Saved" dari penyimpanan, dan pesan
     * hasil aksinya sendiri.
     *
     * @test
     */
    public function it_never_leaves_the_saved_toast_switched_on()
    {
        $base = app_path('Filament/Admin/Resources/ProductRequisitionResource/Pages/');

        foreach (['ReviewProductRequisition.php', 'ApproveFinanceProductRequisition.php'] as $file) {
            $source = file_get_contents($base . $file);

            $this->assertStringNotContainsString(
                '$this->save(false);',
                $source,
                $file . ' masih memunculkan toast "Saved" berbarengan dengan pesan aksinya.',
            );
        }
    }

    /** @test */
    public function it_shows_only_one_toast_after_purchasing_approves()
    {
        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'Requested',
        ]);

        $requisition->items()->create([
            'product_id' => $this->product->id,
            'qty' => 300,
            'price' => 250000,
            'subtotal' => 75000000,
        ]);
        $requisition->updateTotalAmount();

        Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $titles = collect(session('filament.notifications') ?? [])
            ->pluck('title')
            ->all();

        $this->assertNotContains('Saved', $titles, 'Toast "Saved" masih ikut muncul.');
    }
}
