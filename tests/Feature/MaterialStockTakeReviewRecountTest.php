<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialStockTakeResource\Pages\EditMaterialStockTake;
use App\Filament\Admin\Resources\MaterialStockTakeResource\Pages\ManageMaterialStockTakeItems;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStockTake;
use App\Models\MaterialStockTakeItem;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Opname material: REVIEW dan "Minta Hitung Ulang".
 *
 * Keputusan Owner, 17 September 2026:
 *   1. Complete/Finish HANYA dari REVIEW -- tidak ada lagi jalur selesai dari
 *      halaman input hitungan.
 *   2. "Minta Hitung Ulang" di REVIEW: pemeriksa pilih item, dokumen kembali
 *      IN_PROGRESS (statusnya SATU untuk seluruh dokumen), angka lama
 *      disimpan sebagai riwayat, penghitung input ulang buta (field kosong),
 *      item LAIN tetap terkunci (bukan status dokumen yang mengunci).
 *   3. Selisih hanya terlihat di REVIEW oleh pemegang izin
 *      finish_material_stock_takes.
 *   4. Activity log mencatat siapa minta hitung ulang & item mana.
 */
class MaterialStockTakeReviewRecountTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->material = Material::create([
            'code' => 'MTR-01', 'name' => 'KERTAS HVS',
            'material_category_id' => MaterialCategory::create(['name' => 'KERTAS'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'RIM'])->id,
            'min_stock' => 0, 'show_in_stock' => true,
        ]);
    }

    private function opname(string $status = MaterialStockTake::STATUS_IN_PROGRESS): MaterialStockTake
    {
        return MaterialStockTake::create([
            'document_number' => 'MSO-'.uniqid(), 'period' => now()->format('Y-m'),
            'date' => now()->toDateString(), 'status' => $status,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    private function hitungan(MaterialStockTake $opname, float $sistem, ?float $fisik, bool $locked = false): MaterialStockTakeItem
    {
        return MaterialStockTakeItem::create([
            'material_stock_take_id' => $opname->id, 'material_id' => $this->material->id,
            'system_qty' => $sistem, 'physical_qty' => $fisik,
            'difference_qty' => $fisik === null ? null : $fisik - $sistem,
            'is_locked' => $locked,
        ]);
    }

    private function pemeriksa(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_material_stock_takes', 'edit_material_stock_takes']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Material Stock Take', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    // =========================================================================
    // 1. Complete/Finish hanya dari REVIEW
    // =========================================================================

    /** @test */
    public function the_items_page_no_longer_offers_a_finish_action(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_IN_PROGRESS);

        Livewire::actingAs($this->pemeriksa(['finish_material_stock_takes']))
            ->test(ManageMaterialStockTakeItems::class, ['record' => $opname->getRouteKey()])
            ->assertActionDoesNotExist('complete_opname');
    }

    /** @test */
    public function the_review_page_still_offers_complete_only_in_review(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);

        Livewire::actingAs($this->pemeriksa(['finish_material_stock_takes']))
            ->test(EditMaterialStockTake::class, ['record' => $opname->getRouteKey()])
            ->assertActionExists('complete_opname');
    }

    // =========================================================================
    // 2. submitForReview() mengunci semua baris
    // =========================================================================

    /** @test */
    public function submitting_for_review_locks_every_item(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_IN_PROGRESS);
        $item1 = $this->hitungan($opname, 10, 12);
        $item2 = $this->hitungan($opname, 20, 20);

        $this->assertTrue($opname->submitForReview());

        $this->assertSame(MaterialStockTake::STATUS_REVIEW, $opname->fresh()->status);
        $this->assertTrue($item1->fresh()->is_locked);
        $this->assertTrue($item2->fresh()->is_locked);
    }

    /** @test */
    public function submitting_for_review_fails_when_already_past_that_stage(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_COMPLETED);

        $this->assertFalse($opname->submitForReview());
        $this->assertSame(MaterialStockTake::STATUS_COMPLETED, $opname->fresh()->status);
    }

    // =========================================================================
    // 3. requestRecount(): dokumen kembali IN_PROGRESS, item TERPILIH saja
    //    yang terbuka, item lain tetap terkunci
    // =========================================================================

    /** @test */
    public function requesting_a_recount_reopens_only_the_selected_items(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);
        $dibuka = $this->hitungan($opname, 10, 12, locked: true);
        $tetapTerkunci = $this->hitungan($opname, 20, 20, locked: true);

        $this->actingAs(User::factory()->create(['role' => 'programmer', 'is_active' => true]));

        $this->assertTrue($opname->requestRecount([$dibuka->id]));

        // Status SATU untuk seluruh dokumen -- kembali IN_PROGRESS.
        $this->assertSame(MaterialStockTake::STATUS_IN_PROGRESS, $opname->fresh()->status);

        // Item yang diminta ulang: dibuka, kosong.
        $dibukaSegar = $dibuka->fresh();
        $this->assertFalse($dibukaSegar->is_locked);
        $this->assertNull($dibukaSegar->physical_qty);
        $this->assertNull($dibukaSegar->difference_qty);

        // Item lain: TETAP terkunci, angkanya utuh -- bukan status dokumen
        // yang menahannya, tapi baris itu sendiri.
        $tetapSegar = $tetapTerkunci->fresh();
        $this->assertTrue($tetapSegar->is_locked);
        $this->assertSame(20.0, (float) $tetapSegar->physical_qty);
        $this->assertSame(0.0, (float) $tetapSegar->difference_qty);
    }

    /** @test */
    public function requesting_a_recount_fails_when_the_document_is_not_in_review(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_IN_PROGRESS);
        $item = $this->hitungan($opname, 10, 12, locked: true);

        $this->assertFalse($opname->requestRecount([$item->id]));
        $this->assertSame(MaterialStockTake::STATUS_IN_PROGRESS, $opname->fresh()->status);
        $this->assertTrue($item->fresh()->is_locked);
    }

    /** @test */
    public function requesting_a_recount_with_no_valid_items_does_nothing(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);

        $this->assertFalse($opname->requestRecount([999999]));
        $this->assertSame(MaterialStockTake::STATUS_REVIEW, $opname->fresh()->status);
    }

    /** @test */
    public function the_recounted_item_is_editable_again_and_starts_empty(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);
        $item = $this->hitungan($opname, 10, 12, locked: true);

        $this->actingAs(User::factory()->create(['role' => 'programmer', 'is_active' => true]));
        $opname->requestRecount([$item->id]);

        // Halaman input merender tanpa galat untuk baris yang baru dibuka --
        // visibilitas kolom input/teks statisnya per BARIS (lihat $editable
        // di table()), jadi diverifikasi lewat isinya, bukan lewat status
        // kolom global.
        Livewire::test(ManageMaterialStockTakeItems::class, ['record' => $opname->fresh()->getRouteKey()])
            ->assertOk();

        $itemSegar = $item->fresh();
        $this->assertFalse($itemSegar->is_locked);
        $this->assertNull($itemSegar->physical_qty);
    }

    // =========================================================================
    // 4. Activity log: siapa minta hitung ulang & item mana
    // =========================================================================

    /** @test */
    public function requesting_a_recount_is_logged_with_the_previous_numbers(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);
        $item = $this->hitungan($opname, 10, 12, locked: true);
        $pemeriksa = User::factory()->create(['role' => 'programmer', 'is_active' => true, 'name' => 'Pak Reviewer']);
        $this->actingAs($pemeriksa);

        $opname->requestRecount([$item->id]);

        $log = Activity::where('subject_type', MaterialStockTakeItem::class)
            ->where('subject_id', $item->id)
            ->where('description', 'Recount requested')
            ->first();

        $this->assertNotNull($log, 'Permintaan hitung ulang tidak tercatat di activity log.');
        $this->assertSame($pemeriksa->id, $log->causer_id);
        $this->assertSame(12.0, (float) $log->properties['previous_physical_qty']);
        $this->assertSame(2.0, (float) $log->properties['previous_difference_qty']);
    }

    // =========================================================================
    // 5. Selisih hanya terlihat di REVIEW oleh pemegang izin
    // =========================================================================

    /** @test */
    public function the_difference_is_hidden_in_review_from_a_user_without_the_finish_permission(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);
        $this->hitungan($opname, 10, 17, locked: true);

        Livewire::actingAs($this->pemeriksa()) // tanpa finish_material_stock_takes
            ->test(ManageMaterialStockTakeItems::class, ['record' => $opname->getRouteKey()])
            ->assertTableColumnHidden('difference_qty')
            ->assertTableColumnHidden('status');
    }

    /** @test */
    public function the_difference_is_visible_in_review_to_a_user_with_the_finish_permission(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);
        $this->hitungan($opname, 10, 17, locked: true);

        Livewire::actingAs($this->pemeriksa(['finish_material_stock_takes']))
            ->test(ManageMaterialStockTakeItems::class, ['record' => $opname->getRouteKey()])
            ->assertTableColumnVisible('difference_qty')
            ->assertTableColumnVisible('status');
    }

    // =========================================================================
    // Bulk action "Minta Hitung Ulang": hanya di REVIEW, hanya untuk peninjau
    // =========================================================================

    /** @test */
    public function the_recount_bulk_action_is_hidden_outside_review(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_IN_PROGRESS);
        $this->hitungan($opname, 10, 12);

        Livewire::actingAs($this->pemeriksa(['finish_material_stock_takes']))
            ->test(ManageMaterialStockTakeItems::class, ['record' => $opname->getRouteKey()])
            ->assertTableBulkActionHidden('request_recount');
    }

    /** @test */
    public function the_recount_bulk_action_is_hidden_from_a_user_without_the_finish_permission(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);
        $this->hitungan($opname, 10, 12, locked: true);

        Livewire::actingAs($this->pemeriksa()) // tanpa finish_material_stock_takes
            ->test(ManageMaterialStockTakeItems::class, ['record' => $opname->getRouteKey()])
            ->assertTableBulkActionHidden('request_recount');
    }

    /** @test */
    public function the_recount_bulk_action_is_visible_in_review_to_a_reviewer(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_REVIEW);
        $this->hitungan($opname, 10, 12, locked: true);

        Livewire::actingAs($this->pemeriksa(['finish_material_stock_takes']))
            ->test(ManageMaterialStockTakeItems::class, ['record' => $opname->getRouteKey()])
            ->assertTableBulkActionVisible('request_recount');
    }

    // =========================================================================
    // Lapis kedua: baris terkunci tidak bisa ditulis langsung lewat kolom
    // =========================================================================

    /** @test */
    public function a_locked_item_cannot_be_edited_directly_through_the_input_column(): void
    {
        $opname = $this->opname(MaterialStockTake::STATUS_IN_PROGRESS);
        $item = $this->hitungan($opname, 10, 12, locked: true);

        Livewire::actingAs($this->pemeriksa())
            ->test(ManageMaterialStockTakeItems::class, ['record' => $opname->getRouteKey()])
            ->set("tableColumnSearches.{$item->id}", null)
            ->call('updateTableColumnState', 'physical_qty', $item->id, '99');

        $this->assertSame(12.0, (float) $item->fresh()->physical_qty);
    }
}
