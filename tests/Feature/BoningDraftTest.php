<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\DraftBoning;
use App\Models\Boning;
use App\Models\Carcass;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Draft Boning: karkas yang menunggu boning dipilih beberapa sekaligus; karkas hasil impor legacy tidak ikut (Owner, 22 Oktober 2026).
 */
class BoningDraftTest extends TestCase
{
    use RefreshDatabase;

    private int $weighing;

    private function user(string ...$permissions): User
    {
        $user = User::create(['name' => 'Staf', 'username' => 'staf_'.uniqid(), 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        foreach ($permissions as $name) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['module_name' => 'Test', 'description' => $name])->id);
        }

        return $user;
    }

    private function carcass(string $number, bool $imported = false): Carcass
    {
        $id = DB::table('carcasses')->insertGetId([
            'carcass_number' => $number, 'cattle_weighing_id' => $this->weighing, 'kill_date' => now()->toDateString(),
            'legacy_boned' => $imported ? 1 : 0, 'created_by' => User::first()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Carcass::findOrFail($id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $this->user();
        $supplier = DB::table('suppliers')->insertGetId(['name' => 'PT SUPPLIER', 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $po = DB::table('purchase_cattles')->insertGetId(['document_number' => 'CPO-1', 'supplier_id' => $supplier, 'shipping_date' => '2026-10-18', 'created_by' => User::first()->id, 'created_at' => $now, 'updated_at' => $now]);
        $rc = DB::table('cattle_receivings')->insertGetId(['receiving_number' => 'RCV-1', 'purchase_cattle_id' => $po, 'supplier_id' => $supplier, 'receive_date' => '2026-10-18', 'created_by' => User::first()->id, 'created_at' => $now, 'updated_at' => $now]);
        $this->weighing = DB::table('cattle_weighings')->insertGetId(['weighing_number' => 'WGH-1', 'cattle_receiving_id' => $rc, 'weighing_date' => '2026-10-18', 'created_by' => User::first()->id, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_only_carcasses_that_are_not_imported_and_not_yet_in_a_boning_wait_for_boning(): void
    {
        $waiting = $this->carcass('CC#26001');
        $imported = $this->carcass('CRC-0100', true);
        $taken = $this->carcass('CC#26002');

        $boning = Boning::create(['boning_date' => now()->toDateString(), 'status' => 'OPEN', 'created_by' => User::first()->id]);
        $boning->carcasses()->create(['carcass_id' => $taken->id]);

        $this->assertSame([$waiting->id], Carcass::waitingForBoning()->pluck('id')->all());

        Livewire::actingAs($this->user('view_bonings', 'create_bonings'))
            ->test(DraftBoning::class)
            ->assertCanSeeTableRecords(collect([$waiting]))
            ->assertCanNotSeeTableRecords(collect([$imported, $taken]));
    }

    public function test_several_carcasses_become_one_boning_in_a_single_action(): void
    {
        $a = $this->carcass('CC#26001');
        $b = $this->carcass('CC#26002');
        $c = $this->carcass('CC#26003');

        Livewire::actingAs($this->user('view_bonings', 'create_bonings'))
            ->test(DraftBoning::class)
            ->callTableBulkAction('create_boning', collect([$a, $b]), ['boning_date' => '2026-10-23', 'note' => 'dua ekor'])
            ->assertHasNoTableBulkActionErrors();

        $boning = Boning::first();
        $this->assertSame('OPEN', $boning->status);
        $this->assertSame('2026-10-23', $boning->boning_date->toDateString());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $boning->carcasses()->pluck('carcass_id')->all());
        $this->assertSame([$c->id], Carcass::waitingForBoning()->pluck('id')->all(), 'yang tidak dipilih tetap menunggu');
    }

    public function test_a_carcass_taken_meanwhile_never_ends_up_in_a_second_boning(): void
    {
        $a = $this->carcass('CC#26001');
        $b = $this->carcass('CC#26002');

        $page = Livewire::actingAs($this->user('view_bonings', 'create_bonings'))->test(DraftBoning::class);

        // Orang lain mengambil karkas B lebih dulu (daftar di layar masih lama).
        $other = Boning::create(['boning_date' => now()->toDateString(), 'status' => 'OPEN', 'created_by' => User::first()->id]);
        $other->carcasses()->create(['carcass_id' => $b->id]);

        $page->callTableBulkAction('create_boning', collect([$a, $b]), ['boning_date' => '2026-10-23']);

        // Filament membuang baris yang sudah tidak ada di query tabel; pemeriksaan ulang di dalam transaksi menjaga sisanya.
        $this->assertSame(1, DB::table('boning_carcasses')->where('carcass_id', $b->id)->count(), 'karkas B hanya di boning orang lain');
        $this->assertSame([], Carcass::waitingForBoning()->pluck('id')->all(), 'A kini di boning baru, B di boning lain');
    }

    public function test_the_draft_page_needs_the_create_permission(): void
    {
        Livewire::actingAs($this->user('view_bonings'))->test(DraftBoning::class)->assertForbidden();
    }

    public function test_an_imported_carcass_is_opened_for_boning_only_with_the_permission_and_a_reason(): void
    {
        $imported = $this->carcass('CRC-0100', true);

        try {
            $imported->reopenForBoning('sapi dipotong 1 Nov', $this->user('view_carcasses'));
            $this->fail('tanpa izin harus ditolak');
        } catch (AuthorizationException) {
            $this->assertTrue($imported->fresh()->legacy_boned);
        }

        $by = $this->user('reopen_carcass_for_boning');

        try {
            $imported->reopenForBoning('  ', $by);
            $this->fail('tanpa alasan harus ditolak');
        } catch (\RuntimeException) {
            $this->assertTrue($imported->fresh()->legacy_boned);
        }

        $imported->reopenForBoning('sapi dipotong 1 Nov, boning 2 Nov', $by);

        $this->assertFalse($imported->fresh()->legacy_boned);
        $this->assertSame([$imported->id], Carcass::waitingForBoning()->pluck('id')->all(), 'kini muncul di Draft Boning');
        $this->assertDatabaseHas('activity_log', ['subject_id' => $imported->id, 'subject_type' => Carcass::class, 'causer_id' => $by->id]);

        $this->expectException(\RuntimeException::class);
        $imported->fresh()->reopenForBoning('lagi', $by);
    }
}
