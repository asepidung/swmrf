<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MutationResource\Pages\ReceiveMutation;
use App\Filament\Admin\Resources\MutationResource\Pages\ScanMutation;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Grade;
use App\Models\Mutation;
use App\Models\MutationItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tab basi tidak boleh menggerakkan stok mutasi (audit pra-rilis 14 November 2026, S-01..S-03).
 *
 * `mount()` kedua halaman mutasi memeriksa status hanya sekali, saat halaman dibuka. Tab yang masih
 * terbuka sesudah mutasinya berpindah status di sesi lain dulu tetap bisa menekan tombolnya:
 * penerimaan kedua membuat stok gudang tujuan dua kali, dan pindaian ke mutasi yang sudah selesai
 * mengeluarkan stok dari gudang asal tanpa pernah sampai ke mana pun.
 */
class MutationStaleTabTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Warehouse $from;

    private Warehouse $to;

    private Grade $grade;

    private User $seedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUser = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->from = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->to = Warehouse::create(['code' => 'PRM', 'name' => 'PERUM', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_mutations']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Mutations', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function mutation(string $status): Mutation
    {
        return Mutation::create([
            'mutation_date' => now()->format('Y-m-d'), 'from_warehouse_id' => $this->from->id,
            'to_warehouse_id' => $this->to->id, 'status' => $status, 'created_by' => $this->seedUser->id,
        ]);
    }

    private function item(Mutation $mutation, string $barcode, bool $received = false): MutationItem
    {
        return MutationItem::create([
            'mutation_id' => $mutation->id, 'barcode' => $barcode, 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(),
            'origin' => 'BONING', 'is_received' => $received,
        ]);
    }

    /** @test */
    public function finishing_the_same_receiving_twice_creates_the_destination_stock_only_once(): void
    {
        $mutation = $this->mutation('SENT');
        $this->item($mutation, 'FX-TWICE', received: true);
        $user = $this->employee();

        // Dua tab dibuka selagi mutasinya masih SENT.
        $tabPertama = Livewire::actingAs($user)->test(ReceiveMutation::class, ['record' => $mutation]);
        $tabKedua = Livewire::actingAs($user)->test(ReceiveMutation::class, ['record' => $mutation]);

        $tabPertama->callAction('finish');
        $tabKedua->callAction('finish');

        $this->assertSame('COMPLETED', $mutation->fresh()->status);
        $this->assertSame(1, BeefStock::where('barcode', 'FX-TWICE')->count(), 'Stok gudang tujuan tidak boleh dibuat dua kali.');
        $this->assertSame(
            1,
            BeefStockMovement::where('barcode', 'FX-TWICE')->where('transaction_type', 'MUTATION_IN')->count(),
            'Kartu stok tidak boleh mencatat penerimaan yang sama dua kali.'
        );
    }

    /** @test */
    public function a_stale_tab_cannot_change_receiving_marks_on_a_finished_mutation(): void
    {
        $mutation = $this->mutation('SENT');
        $diterima = $this->item($mutation, 'FX-DONE', received: true);
        $kurang = $this->item($mutation, 'FX-SHORT', received: false);
        $user = $this->employee();

        $tabBasi = Livewire::actingAs($user)->test(ReceiveMutation::class, ['record' => $mutation]);

        // Diselesaikan di sesi lain.
        Mutation::whereKey($mutation->id)->update(['status' => 'COMPLETED']);

        $tabBasi->callTableAction('cancel_receive', $diterima->id);
        $tabBasi->callAction('receive_all');
        $tabBasi->set('barcode', 'FX-SHORT')->call('addBarcode');

        $this->assertTrue((bool) $diterima->fresh()->is_received);
        $this->assertFalse((bool) $kurang->fresh()->is_received);
    }

    /** @test */
    public function a_stale_scan_tab_cannot_pull_stock_into_a_mutation_that_already_left(): void
    {
        $mutation = $this->mutation('DRAFT');
        BeefStock::create([
            'barcode' => 'FX-LATE', 'product_id' => $this->product->id, 'warehouse_id' => $this->from->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1,
            'pack_date' => now(), 'origin' => 'BONING', 'status' => 'IN_STOCK',
        ]);

        $tabBasi = Livewire::actingAs($this->employee(['edit_mutations']))
            ->test(ScanMutation::class, ['record' => $mutation]);

        Mutation::whereKey($mutation->id)->update(['status' => 'COMPLETED']);

        $tabBasi->set('barcode', 'FX-LATE')->call('addBarcode');

        $this->assertDatabaseHas('beef_stocks', ['barcode' => 'FX-LATE']);
        $this->assertSame(0, MutationItem::where('mutation_id', $mutation->id)->count());
    }

    /** @test */
    public function a_stale_scan_tab_cannot_send_a_finished_mutation_again(): void
    {
        $mutation = $this->mutation('DRAFT');
        $this->item($mutation, 'FX-RESEND', received: true);

        $tabBasi = Livewire::actingAs($this->employee(['edit_mutations']))
            ->test(ScanMutation::class, ['record' => $mutation]);

        Mutation::whereKey($mutation->id)->update(['status' => 'COMPLETED']);

        $tabBasi->callAction('finish');

        $this->assertSame('COMPLETED', $mutation->fresh()->status, 'Mutasi yang sudah selesai tidak boleh kembali menjadi SENT.');
    }

    /** @test */
    public function a_draft_mutation_is_still_sent_and_received_normally(): void
    {
        $mutation = $this->mutation('DRAFT');
        $this->item($mutation, 'FX-NORMAL');
        $user = $this->employee(['edit_mutations']);

        Livewire::actingAs($user)->test(ScanMutation::class, ['record' => $mutation])->callAction('finish');
        $this->assertSame('SENT', $mutation->fresh()->status);

        Livewire::actingAs($user)->test(ReceiveMutation::class, ['record' => $mutation->fresh()])
            ->set('barcode', 'FX-NORMAL')->call('addBarcode')
            ->callAction('finish');

        $this->assertSame('COMPLETED', $mutation->fresh()->status);
        $this->assertSame(1, BeefStock::where('barcode', 'FX-NORMAL')->where('warehouse_id', $this->to->id)->count());
    }
}
