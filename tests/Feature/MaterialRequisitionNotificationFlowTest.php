<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ApproveFinanceMaterialRequisition;
use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ReviewMaterialRequisition;
use App\Models\Material;
use App\Models\MaterialRequisition;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\TaskAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Perjalanan pemberitahuan Request Material, disamakan dengan Request Beef.
 *
 * Modul ini sebelumnya tidak punya notifikasi sama sekali: orang yang harus
 * bertindak tidak pernah tahu ada dokumen menunggunya, dan pelakunya sendiri
 * tidak mendapat umpan balik apa pun setelah menekan Approve.
 *
 * Yang dijaga adalah SIAPA yang menerima di tiap tahap. Salah sasaran tidak
 * menimbulkan error apa pun -- dokumennya tetap tersimpan.
 */
class MaterialRequisitionNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $pemohon;

    protected User $purchasing;

    protected User $finance;

    protected Supplier $supplier;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pemohon = $this->makeUser('pemohon', [
            'view_material_requisitions',
            'create_material_requisitions',
        ]);

        $this->purchasing = $this->makeUser('purchasing', [
            'view_material_requisitions',
            'edit_material_requisitions',
            'review_material_requisitions',
        ]);

        $this->finance = $this->makeUser('finance', [
            'view_material_requisitions',
            'edit_material_requisitions',
            'approve_material_requisitions',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'CV KEMASAN JAYA',
            'address' => 'Bogor',
            'pic' => 'Rudi',
            'top_days' => 30,
        ]);

        $this->material = Material::create([
            'code' => 'MTR001',
            'name' => 'PLASTIK VACUUM',
            'material_category_id' => \App\Models\MaterialCategory::create(['name' => 'KEMASAN'])->id,
            'material_unit_id' => \App\Models\MaterialUnit::create(['name' => 'PCS'])->id,
            'is_active' => true,
        ]);
    }

    protected function makeUser(string $username, array $permissions): User
    {
        $user = User::create([
            'name' => ucfirst($username),
            'username' => 'mat_' . $username,
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Material Requisition', 'description' => $name],
            );

            $user->permissions()->attach($permission->id);
        }

        // Tanpa langganan, TaskNotifier membuang penerimanya sebelum mengirim.
        $user->updatePushSubscription(
            'https://push.example.test/mat-' . $username,
            'p256dh-mat-' . $username,
            'auth-mat-' . $username,
        );

        return $user;
    }

    protected function makeRequisition(string $status): MaterialRequisition
    {
        $requisition = MaterialRequisition::create([
            'supplier_id' => (\App\Models\Supplier::first() ?? \App\Models\Supplier::create(['name' => 'Supplier Test ' . uniqid(), 'address' => 'Bogor', 'pic' => 'Test', 'top_days' => 30, 'is_active' => true]))->id,
            'user_id' => $this->pemohon->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);

        $requisition->items()->create([
            'material_id' => $this->material->id,
            'qty' => 100,
            'price' => 15000,
            'subtotal' => 1500000,
        ]);

        $requisition->updateTotalAmount();

        return $requisition;
    }

    protected function assertAlerted(User $user, string $title): void
    {
        Notification::assertSentTo(
            $user,
            TaskAlert::class,
            function (TaskAlert $alert) use ($user, $title) {
                return ($alert->toWebPush($user, $alert)->toArray()['title'] ?? null) === $title;
            },
        );
    }

    /** @test */
    public function it_tells_the_requester_when_purchasing_rejects()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->purchasing)
            ->test(ReviewMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('reject', ['reject_note' => 'Barangnya salah']);

        $this->assertSame('Rejected', $requisition->fresh()->status);
        $this->assertAlerted($this->pemohon, __('Material Request Rejected'));
    }

    /** @test */
    public function it_tells_finance_when_purchasing_approves()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->purchasing)
            ->test(ReviewMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $this->assertSame('Pending Finance', $requisition->fresh()->status);
        $this->assertAlerted($this->finance, __('Material Request Awaiting Approval'));

        // Pemohon IKUT diberi tahu bahwa request-nya maju, keputusan Project
        // Owner 27 Agustus 2026. Sebelumnya ia tidak dilibatkan di tahap ini
        // dan tidak pernah tahu nasib pengajuannya sampai PO terbit.
        $this->assertAlerted($this->pemohon, __('Material Request Approved'));
    }

    /** @test */
    public function it_tells_purchasing_when_finance_returns_the_request()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->finance)
            ->test(ApproveFinanceMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('reject', ['reject_note' => 'Harga terlalu tinggi']);

        $this->assertSame('Returned to Purchasing', $requisition->fresh()->status);
        $this->assertAlerted($this->purchasing, __('Material Request Returned'));
    }

    /** @test */
    public function it_tells_purchasing_when_finance_approves_and_the_po_is_issued()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->finance)
            ->test(ApproveFinanceMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => 0]);

        $this->assertSame('PO Created', $requisition->fresh()->status);
        $this->assertAlerted($this->purchasing, __('Material Request Approved'));
    }

    /** @test */
    public function typed_prices_with_thousand_separators_are_stored_at_face_value()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Requested');
        $item = $requisition->items()->first();

        foreach (['80.000' => 80000.0, '80000' => 80000.0, '43.500' => 43500.0, '1.250.000' => 1250000.0, '80,5' => 80.5, '1.250.000,50' => 1250000.5] as $typed => $expected) {
            $this->assertSame($expected, \App\Filament\Admin\Resources\MaterialRequisitionResource::parseNumber($typed), "ketik {$typed}");
        }

        $page = Livewire::actingAs($this->purchasing)->test(ReviewMaterialRequisition::class, ['record' => $requisition->id]);
        $key = array_key_first($page->get('data.items'));
        $page->set("data.items.{$key}.price", '80.000')->callAction('approve');

        $this->assertSame(80000.0, (float) $requisition->items()->first()->price, 'harga yang diketik 80.000 harus tersimpan 80000, bukan 80');
        $this->assertSame(100 * 80000.0, (float) $requisition->items()->first()->subtotal);
    }

    /** @test */
    public function purchasing_cannot_send_a_request_with_a_zero_price_to_finance()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Requested');
        $requisition->items()->update(['price' => 0, 'subtotal' => 0]);

        Livewire::actingAs($this->purchasing)
            ->test(ReviewMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $this->assertSame('Requested', $requisition->fresh()->status, 'harga 0 menahan request di Purchasing');
        $this->assertNull($requisition->fresh()->reviewed_by);
        Notification::assertNotSentTo($this->finance, TaskAlert::class);
    }

    /** @test */
    public function one_zero_priced_line_among_priced_lines_is_enough_to_hold_the_request()
    {
        Notification::fake();

        $requisition = $this->makeRequisition('Requested');
        $second = Material::create(['code' => 'MTR002', 'name' => 'KARTON 20 KG', 'material_category_id' => $this->material->material_category_id, 'material_unit_id' => $this->material->material_unit_id, 'is_active' => 1]);
        $requisition->items()->create(['material_id' => $second->id, 'qty' => 10, 'price' => 0, 'subtotal' => 0]);

        Livewire::actingAs($this->purchasing)
            ->test(ReviewMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('approve');

        $this->assertSame('Requested', $requisition->fresh()->status);
    }

    /**
     * save() punya argumen kedua yang mengatur toast "Saved". Tanpa dimatikan,
     * pengguna melihat dua toast sekaligus.
     *
     * @test
     */
    public function it_never_leaves_the_saved_toast_switched_on()
    {
        $base = app_path('Filament/Admin/Resources/MaterialRequisitionResource/Pages/');

        foreach (['ReviewMaterialRequisition.php', 'ApproveFinanceMaterialRequisition.php'] as $file) {
            $this->assertStringNotContainsString(
                '$this->save(false);',
                file_get_contents($base . $file),
                $file . ' masih memunculkan toast "Saved" berbarengan dengan pesan aksinya.',
            );
        }
    }
}
