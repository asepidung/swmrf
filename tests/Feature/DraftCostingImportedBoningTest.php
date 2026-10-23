<?php

namespace Tests\Feature;

use App\Models\Boning;
use App\Models\Costing;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Boning hasil impor legacy tidak ikut Draft Costing (Owner, 23 Oktober 2026), kecuali dibuka per boning dengan izin dan alasan.
 */
class DraftCostingImportedBoningTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    private function user(string ...$permissions): User
    {
        $user = User::create(['name' => 'Staf', 'username' => 'staf_'.uniqid(), 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        foreach ($permissions as $name) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['module_name' => 'Test', 'description' => $name])->id);
        }

        return $user;
    }

    private function boning(string $docNo, bool $imported): Boning
    {
        $id = DB::table('bonings')->insertGetId([
            'doc_no' => $docNo, 'boning_date' => '2026-10-20', 'status' => 'LOCKED', 'kunci' => 1, 'legacy_imported' => $imported ? 1 : 0,
            'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('boning_items')->insert([
            'boning_id' => $id, 'barcode' => 'B'.$id, 'product_id' => 21, 'warehouse_id' => 1, 'grade_id' => 1, 'weight' => 10, 'qty_pcs' => 1,
            'pack_date' => '2026-10-20', 'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Boning::findOrFail($id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $this->userId = $this->user()->id;
        DB::table('warehouses')->insert(['id' => 1, 'code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('grades')->insert(['id' => 1, 'name' => 'CHILL', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $category = DB::table('product_categories')->insertGetId(['name' => 'PRIME CUT', 'prefix' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('products')->insert(['id' => 21, 'code' => '100207', 'name' => 'OPERIB WHOLE', 'category_id' => $category, 'structure_type' => 'main', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_imported_bonings_do_not_wait_for_costing_but_new_ones_do(): void
    {
        $imported = $this->boning('BN-LEGACY', true);
        $fresh = $this->boning('BN-26001', false);

        $waiting = Costing::boningsWaiting()->pluck('id')->all();

        $this->assertSame([$fresh->id], $waiting);
        $this->assertNotContains($imported->id, $waiting);
    }

    public function test_an_imported_boning_is_opened_for_costing_only_with_the_permission_and_a_reason(): void
    {
        $imported = $this->boning('BN-LEGACY', true);

        try {
            $imported->reopenForCosting('boning 1 Nov', $this->user('view_bonings'));
            $this->fail('tanpa izin harus ditolak');
        } catch (AuthorizationException) {
            $this->assertTrue($imported->fresh()->legacy_imported);
        }

        $by = $this->user('reopen_boning_for_costing');

        try {
            $imported->reopenForCosting('   ', $by);
            $this->fail('tanpa alasan harus ditolak');
        } catch (\RuntimeException) {
            $this->assertTrue($imported->fresh()->legacy_imported);
        }

        $imported->reopenForCosting('boning 1 November perlu dicosting', $by);

        $this->assertFalse($imported->fresh()->legacy_imported);
        $this->assertSame([$imported->id], Costing::boningsWaiting()->pluck('id')->all(), 'kini muncul di Draft Costing');
        $this->assertDatabaseHas('activity_log', ['subject_id' => $imported->id, 'subject_type' => Boning::class, 'causer_id' => $by->id]);

        $this->expectException(\RuntimeException::class);
        $imported->fresh()->reopenForCosting('lagi', $by);
    }
}
