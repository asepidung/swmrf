<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ApproveFinanceProductRequisition;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batas uang muka, dan toast yang muncul dua kali.
 *
 * Uang muka yang melebihi tagihan tidak menimbulkan error apa pun. Ia baru
 * terasa jauh kemudian: saat utang dihitung, kelebihannya tidak akan pernah
 * terpakai habis, lalu menggantung selamanya sebagai uang muka semu di
 * pembukuan supplier.
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
    protected function makeRequisition(string $status = 'Pending Finance'): ProductRequisition
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
            'price' => 250000,
            'subtotal' => 75000000,
        ]);

        $requisition->updateTotalAmount();

        return $requisition;
    }

    /** @test */
    public function it_rejects_an_advance_payment_larger_than_the_bill()
    {
        $requisition = $this->makeRequisition();

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => '80.000.000'])
            ->assertHasActionErrors(['payment_amount']);

        $this->assertSame('Pending Finance', $requisition->fresh()->status);
        $this->assertSame(0, SupplierPayment::count());
    }

    /** @test */
    public function it_accepts_an_advance_payment_equal_to_the_bill()
    {
        $requisition = $this->makeRequisition();

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => '75.000.000', 'payment_method' => 'cash'])
            ->assertHasNoActionErrors();

        $this->assertSame('PO Created', $requisition->fresh()->status);
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
        $requisition = $this->makeRequisition();

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => '1.000.000', 'payment_method' => 'cash'])
            ->assertHasNoActionErrors();

        $this->assertEquals(1000000, SupplierPayment::sum('amount'));
    }

    /** @test */
    public function it_masks_the_payment_field_with_thousand_separators()
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/ProductRequisitionResource/Pages/ApproveFinanceProductRequisition.php',
        ));

        $this->assertStringContainsString('->mask(RawJs::make(', $source);

        // Pemisah ribuan '.' dan desimal ',' -- format yang dikenali
        // parseNumber(). Format gaya Inggris ("1,000,000") justru akan terbaca
        // sebagai 1,0 dan uang mukanya menyusut tanpa error apa pun.
        $this->assertStringContainsString(
            "\$money(\$input, ',', '.', 0)",
            stripslashes($source),
            'Mask uang tidak memakai format Indonesia, nilainya akan salah terbaca saat disimpan.',
        );
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
        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $titles = collect(session('filament.notifications') ?? [])
            ->pluck('title')
            ->all();

        $this->assertNotContains('Saved', $titles, 'Toast "Saved" masih ikut muncul.');
    }
}
