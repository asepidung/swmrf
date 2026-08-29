<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ApproveFinanceProductRequisition;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\CreateProductRequisition;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\TaskAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Seluruh perjalanan pemberitahuan sebuah Request Beef, dari diajukan sampai
 * PO terbit atau ditolak.
 *
 * Yang dijaga bukan sekadar "ada notifikasi terkirim", melainkan SIAPA yang
 * menerimanya di tiap tahap. Salah sasaran tidak menimbulkan error apa pun:
 * dokumen tetap tersimpan, dan orang yang seharusnya bertindak cuma tidak
 * pernah tahu ada yang menunggunya.
 *
 * Tiap tahap dipicu lewat aksi UI yang sebenarnya, bukan dengan menirukan
 * logikanya.
 */
class RequisitionNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $pemohon;

    protected User $purchasing;

    protected User $finance;

    protected Supplier $supplier;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pemohon = $this->makeUser('pemohon', [
            'view_product_requisitions',
            'create_product_requisitions',
        ]);

        $this->purchasing = $this->makeUser('purchasing', [
            'view_product_requisitions',
            'edit_product_requisitions',
            'review_product_requisitions',
        ]);

        $this->finance = $this->makeUser('finance', [
            'view_product_requisitions',
            'edit_product_requisitions',
            'approve_product_requisitions',
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

    protected function makeUser(string $username, array $permissions): User
    {
        $user = User::create([
            'name' => ucfirst($username),
            'username' => 'notif_' . $username,
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Product Requisition', 'description' => $name],
            );

            $user->permissions()->attach($permission->id);
        }

        // Tanpa langganan, TaskNotifier membuang penerimanya sebelum mengirim.
        $user->updatePushSubscription(
            'https://push.example.test/' . $username,
            'p256dh-' . $username,
            'auth-' . $username,
        );

        return $user;
    }

    protected function makeRequisition(string $status): ProductRequisition
    {
        $requisition = ProductRequisition::create([
            'user_id' => $this->pemohon->id,
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

    /**
     * Judul TaskAlert yang benar-benar dikirim ke seorang pengguna.
     *
     * Dibaca dari payload yang dihasilkan toWebPush(), bukan dari properti
     * kelasnya. Selain karena propertinya protected, cara ini sekaligus
     * memastikan payload-nya memang terbentuk -- termasuk ikon yang tanpa itu
     * membuat Android menampilkan avatar huruf.
     */
    protected function assertAlerted(User $user, string $title): void
    {
        Notification::assertSentTo(
            $user,
            TaskAlert::class,
            function (TaskAlert $alert) use ($user, $title) {
                $payload = $alert->toWebPush($user, $alert)->toArray();

                return ($payload['title'] ?? null) === $title;
            },
        );
    }

    /**
     * Tahap 1: diajukan -> purchasing yang berhak me-review.
     *
     * @test
     */
    public function it_tells_purchasing_when_a_request_is_created()
    {
        Notification::fake();

        Livewire::actingAs($this->pemohon)
            ->test(CreateProductRequisition::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'due_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => '300', 'price' => '250.000', 'note' => null],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertAlerted($this->purchasing, __('New Beef Request'));

        // Pemohon adalah pelakunya sendiri, jadi tidak perlu diberi tahu.
        Notification::assertNotSentTo($this->pemohon, TaskAlert::class);
    }

    /**
     * Tahap 2a: ditolak purchasing -> kembali ke PEMOHON, bukan ke peran lain.
     *
     * @test
     */
    public function it_tells_the_requester_when_purchasing_rejects()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->purchasing)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->callAction('reject', ['reject_note' => 'Barangnya salah']);

        $this->assertSame('Rejected', $requisition->fresh()->status);
        $this->assertAlerted($this->pemohon, __('Beef Request Rejected'));
    }

    /**
     * Tahap 2b: disetujui purchasing -> finance yang berhak approve.
     *
     * @test
     */
    public function it_tells_finance_when_purchasing_approves()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->purchasing)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $this->assertSame('Pending Finance', $requisition->fresh()->status);
        $this->assertAlerted($this->finance, __('Beef Request Awaiting Approval'));

        // Pemohon IKUT diberi tahu bahwa request-nya maju, keputusan Project
        // Owner 27 Agustus 2026. Sebelumnya ia tidak dilibatkan di tahap ini
        // dan tidak pernah tahu nasib pengajuannya sampai PO terbit.
        $this->assertAlerted($this->pemohon, __('Beef Request Approved'));
    }

    /**
     * Tahap 3a: dikembalikan finance -> purchasing, yang harus memperbaikinya.
     *
     * @test
     */
    public function it_tells_purchasing_when_finance_returns_the_request()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->finance)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->callAction('reject', ['reject_note' => 'Harga terlalu tinggi']);

        $this->assertSame('Returned to Purchasing', $requisition->fresh()->status);
        $this->assertAlerted($this->purchasing, __('Beef Request Returned'));
    }

    /**
     * Tahap 3b: disetujui finance -> purchasing diberi tahu PO sudah terbit.
     *
     * @test
     */
    public function it_tells_purchasing_when_finance_approves_and_the_po_is_issued()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->finance)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => 0]);

        $this->assertSame('PO Created', $requisition->fresh()->status);
        $this->assertSame(1, PurchaseProduct::where('product_requisition_id', $requisition->id)->count());
        $this->assertAlerted($this->purchasing, __('Beef Request Approved'));
    }

    /**
     * Notifikasi tidak boleh menggagalkan aksi bisnisnya.
     *
     * Kalau layanan push mati, dokumennya tetap harus tersimpan dan PO tetap
     * harus terbit.
     *
     * @test
     */
    public function it_still_issues_the_po_when_the_push_service_is_down()
    {
        Notification::fake();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('push mati'));

        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->finance)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => 0]);

        $this->assertSame('PO Created', $requisition->fresh()->status);
        $this->assertSame(1, PurchaseProduct::where('product_requisition_id', $requisition->id)->count());
    }
}
