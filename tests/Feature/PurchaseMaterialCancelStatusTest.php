<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tombol "Cancel" PO material menulis status `canceled`; kolomnya harus menerimanya.
 */
class PurchaseMaterialCancelStatusTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_status_column_accepts_canceled(): void
    {
        $user = \App\Models\User::create(['name' => 'U', 'username' => 'u_cancel', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        DB::table('suppliers')->insert(['id' => 1, 'name' => 'S', 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $mr = DB::table('material_requisitions')->insertGetId(['document_number' => 'MR#26001', 'user_id' => $user->id, 'supplier_id' => 1, 'status' => 'PO Created', 'due_date' => now()->toDateString(), 'total_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $po = DB::table('purchase_materials')->insertGetId(['po_number' => 'PO#26001', 'material_requisition_id' => $mr, 'supplier_id' => 1, 'approved_by' => $user->id, 'po_date' => now()->toDateString(), 'total_amount' => 0, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('purchase_materials')->where('id', $po)->update(['status' => 'canceled']);

        $this->assertSame('canceled', DB::table('purchase_materials')->where('id', $po)->value('status'));
    }
}
