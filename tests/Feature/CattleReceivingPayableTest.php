<?php

namespace Tests\Feature;

use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\Payable;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menerima sapi menimbulkan hutang.
 *
 * Keputusan Project Owner: hutang terbit begitu sapi diterima, dan acuannya
 * berat yang diisi operator saat penerimaan -- bukan berat hasil penimbangan
 * ulang. Selisih di penimbangan sudah punya tempatnya sendiri sebagai
 * Financial Loss; ia tidak mengurangi apa yang harus dibayar ke supplier.
 *
 * Sebelum ini `Payable` tidak menyebut Cattle Receiving sama sekali, jadi
 * pembelian sapi hidup tidak pernah masuk daftar utang maupun perhitungan
 * jatuh tempo.
 */
class CattleReceivingPayableTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected CattleClass $bali;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator Kandang',
            'username' => 'operator_payable',
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

        $this->bali = CattleClass::create(['name' => 'BALI', 'is_active' => true]);
    }

    private function makePoWithPrice(?int $pricePerKg = 55000, ?CattleClass $class = null): PurchaseCattle
    {
        $po = PurchaseCattle::create([
            'supplier_id' => $this->supplier->id,
            'shipping_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        if ($pricePerKg !== null) {
            $po->items()->create([
                'cattle_class_id' => ($class ?? $this->bali)->id,
                'qty' => 10,
                'price' => $pricePerKg,
                'created_by' => $this->user->id,
            ]);
        }

        return $po;
    }

    private function receive(PurchaseCattle $po, array $cattle): CattleReceiving
    {
        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        foreach ($cattle as $eartag => $spec) {
            $receiving->items()->create([
                'cattle_class_id' => $spec['class']->id,
                'eartag' => $eartag,
                'initial_weight' => $spec['weight'],
            ]);
        }

        return $receiving->fresh(['items', 'supplier', 'purchaseCattle.items']);
    }

    /** Berat saat terima dikali harga per kg dari PO. */
    public function test_receiving_cattle_creates_a_payable_from_the_weight_recorded_on_arrival(): void
    {
        $po = $this->makePoWithPrice(55000);

        $receiving = $this->receive($po, [
            'ID-1001' => ['class' => $this->bali, 'weight' => 400],
            'ID-1002' => ['class' => $this->bali, 'weight' => 350],
        ]);

        $payable = $receiving->syncPayable();

        $this->assertNotNull($payable, 'Menerima sapi tidak menimbulkan hutang sama sekali.');
        $this->assertEquals((400 + 350) * 55000, $payable->amount);
        $this->assertSame('unpaid', $payable->status);
        $this->assertSame($receiving->receiving_number, $payable->document_number);
    }

    /** Jatuh tempo mengikuti TOP supplier, dihitung dari tanggal terima. */
    public function test_the_due_date_follows_the_suppliers_payment_term(): void
    {
        $receiving = $this->receive($this->makePoWithPrice(), [
            'ID-1003' => ['class' => $this->bali, 'weight' => 400],
        ]);

        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $receiving->syncPayable()->due_date->toDateString(),
        );
    }

    /**
     * Kelas sapi yang tidak ada di PO TIDAK dihitung nol.
     *
     * Form penerimaan tidak membatasi pilihan kelas ke isi PO, jadi kasus ini
     * nyata bisa terjadi. Menghitungnya nol akan menerbitkan hutang yang
     * lebih kecil dari seharusnya -- tanpa error, dan baru ketahuan saat
     * supplier menagih. Lebih baik hutangnya belum terbit dan terlihat.
     */
    public function test_no_payable_is_issued_when_a_cattle_class_has_no_price_on_the_po(): void
    {
        $limosin = CattleClass::create(['name' => 'LIMOSIN', 'is_active' => true]);

        $receiving = $this->receive($this->makePoWithPrice(55000), [
            'ID-1004' => ['class' => $this->bali, 'weight' => 400],
            'ID-1005' => ['class' => $limosin, 'weight' => 500],
        ]);

        $this->assertNull($receiving->syncPayable());
        $this->assertSame(0, Payable::where('document_number', $receiving->receiving_number)->count());

        // Dan operator diberi tahu kelas MANA yang menahannya.
        $this->assertSame(['LIMOSIN'], Payable::unpricedCattleClasses($receiving));
    }

    /** Setelah harga dilengkapi di PO, menyimpan ulang menerbitkan hutangnya. */
    public function test_the_payable_is_issued_once_the_missing_price_is_added(): void
    {
        $limosin = CattleClass::create(['name' => 'LIMOSIN', 'is_active' => true]);
        $po = $this->makePoWithPrice(55000);

        $receiving = $this->receive($po, [
            'ID-1006' => ['class' => $limosin, 'weight' => 500],
        ]);

        $this->assertNull($receiving->syncPayable());

        $po->items()->create([
            'cattle_class_id' => $limosin->id,
            'qty' => 5,
            'price' => 60000,
            'created_by' => $this->user->id,
        ]);

        $payable = $receiving->fresh(['items', 'supplier', 'purchaseCattle.items'])->syncPayable();

        $this->assertNotNull($payable);
        $this->assertEquals(500 * 60000, $payable->amount);
    }

    /** Menyimpan ulang memperbarui hutang yang sama, bukan menerbitkan yang kedua. */
    public function test_saving_again_updates_the_same_payable(): void
    {
        $receiving = $this->receive($this->makePoWithPrice(55000), [
            'ID-1007' => ['class' => $this->bali, 'weight' => 400],
        ]);

        $first = $receiving->syncPayable();

        $receiving->items()->create([
            'cattle_class_id' => $this->bali->id,
            'eartag' => 'ID-1008',
            'initial_weight' => 100,
        ]);

        $second = $receiving->fresh(['items', 'supplier', 'purchaseCattle.items'])->syncPayable();

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(500 * 55000, $second->amount);
        $this->assertSame(1, Payable::withTrashed()->where('document_number', $receiving->receiving_number)->count());
    }

    /** Membatalkan penerimaan ikut melepas hutangnya. */
    public function test_deleting_the_receiving_releases_its_payable(): void
    {
        $receiving = $this->receive($this->makePoWithPrice(55000), [
            'ID-1009' => ['class' => $this->bali, 'weight' => 400],
        ]);

        $receiving->syncPayable();
        $number = $receiving->receiving_number;

        $receiving->delete();

        $this->assertSame(0, Payable::where('document_number', $number)->count());
        $this->assertSame(1, Payable::withTrashed()->where('document_number', $number)->count());
    }

    /** Halaman Create dan Edit benar-benar memanggilnya. */
    public function test_both_save_pages_issue_the_payable(): void
    {
        foreach (['CreateCattleReceiving.php', 'EditCattleReceiving.php'] as $page) {
            $source = file_get_contents(
                app_path('Filament/Admin/Resources/CattleReceivingResource/Pages/'.$page)
            );

            $this->assertStringContainsString('issuePayable', $source, $page);
            $this->assertStringContainsString('Payable not issued yet', $source, $page);
        }
    }
}
