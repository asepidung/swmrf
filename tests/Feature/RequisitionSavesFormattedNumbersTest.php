<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\CreateProductRequisition;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Membuktikan rantai lengkapnya: nilai yang diketik operator sudah ber-pemisah
 * ribuan ("250.000"), dan yang tersimpan di database tetap 250000 — bukan 250.
 *
 * Inilah jebakan paling berbahaya dari pemformatan hidup. PHP membaca
 * "250.000" sebagai 250.0, jadi harga 250 ribu menyusut jadi 250 tanpa error
 * apa pun. Gejalanya baru ketahuan berhari-hari kemudian lewat laporan yang
 * angkanya janggal.
 */
class RequisitionSavesFormattedNumbersTest extends TestCase
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
            'username' => 'programmer_req',
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

    /** @test */
    public function it_stores_a_thousand_separated_price_at_its_full_value()
    {
        Livewire::actingAs($this->user)
            ->test(CreateProductRequisition::class)
            ->fillForm([
                'due_date' => now()->toDateString(),
                'supplier_id' => $this->supplier->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'qty' => '300,00',
                        'price' => '250.000',
                        'note' => null,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $requisition = ProductRequisition::first();
        $this->assertNotNull($requisition, 'Request gagal dibuat.');

        $item = $requisition->items()->first();
        $this->assertNotNull($item, 'Item request tidak tersimpan.');

        $this->assertEquals(250000, $item->price, 'Harga 250.000 tersimpan menyusut. Nilai berformat tidak di-parse.');
        $this->assertEquals(300, $item->qty, 'Qty 300,00 tidak tersimpan dengan benar.');
        $this->assertEquals(75000000, $item->subtotal, 'Subtotal wajib dihitung dari nilai yang sudah di-parse.');
    }

    /** @test */
    public function it_still_accepts_plain_unformatted_numbers()
    {
        Livewire::actingAs($this->user)
            ->test(CreateProductRequisition::class)
            ->fillForm([
                'due_date' => now()->toDateString(),
                'supplier_id' => $this->supplier->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'qty' => '50',
                        'price' => '50000',
                        'note' => null,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = ProductRequisition::first()->items()->first();

        $this->assertEquals(50000, $item->price);
        $this->assertEquals(50, $item->qty);
        $this->assertEquals(2500000, $item->subtotal);
    }
}
