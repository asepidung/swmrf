<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Warehouse;
use App\Support\LegacyGradeMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Terjemahan grade legacy -> (gudang, grade) swmrf (keputusan Owner 16 Oktober 2026, #535).
 */
class LegacyGradeMapTest extends TestCase
{
    use RefreshDatabase;

    private function seedAppSide(): void
    {
        foreach (['CHILL', 'FROZEN', 'A', 'B', 'R'] as $name) {
            Grade::unguarded(fn () => Grade::create(['id' => LegacyGradeMap::APP_GRADE_IDS[$name], 'name' => $name, 'is_active' => true]));
        }

        Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        Warehouse::create(['code' => 'PRM', 'name' => 'PERUM', 'is_active' => true]);
    }

    /** @test */
    public function all_six_legacy_grades_translate_to_their_warehouse_and_grade(): void
    {
        $this->seedAppSide();
        $jonggol = Warehouse::where('code', 'JGL')->value('id');
        $perum = Warehouse::where('code', 'PRM')->value('id');

        $expected = [
            1 => [$jonggol, 1],   // J01 -> JONGGOL + CHILL
            2 => [$jonggol, 2],   // J02 -> JONGGOL + FROZEN
            5 => [$jonggol, 3],   // J03 -> JONGGOL + A
            3 => [$perum, 1],     // P01 -> PERUM + CHILL
            4 => [$perum, 2],     // P02 -> PERUM + FROZEN
            6 => [$perum, 3],     // P03 -> PERUM + A
        ];

        foreach ($expected as $legacyId => [$warehouseId, $gradeId]) {
            $this->assertSame(
                ['warehouse_id' => $warehouseId, 'grade_id' => $gradeId],
                LegacyGradeMap::resolve($legacyId),
                "idgrade legacy {$legacyId}"
            );
        }

        $this->assertSame([], LegacyGradeMap::appSideProblems());
    }

    /** @test */
    public function the_legacy_names_follow_the_ids(): void
    {
        $this->assertSame('J01', LegacyGradeMap::legacyName(1));
        $this->assertSame('J03', LegacyGradeMap::legacyName(5));
        $this->assertSame('P02', LegacyGradeMap::legacyName(4));
        $this->assertNull(LegacyGradeMap::legacyName(9));
    }

    /** @test */
    public function a_legacy_grade_outside_the_table_is_refused(): void
    {
        $this->seedAppSide();

        $this->assertFalse(LegacyGradeMap::knows(9));
        $this->expectException(\InvalidArgumentException::class);

        LegacyGradeMap::resolve(9);
    }

    /** @test */
    public function a_missing_warehouse_or_grade_is_reported_not_guessed(): void
    {
        $this->seedAppSide();
        Warehouse::where('code', 'PRM')->delete();

        $this->assertContains('warehouse code PRM does not exist yet', LegacyGradeMap::appSideProblems());

        $this->expectException(\InvalidArgumentException::class);
        LegacyGradeMap::resolve(3);
    }

    /** @test */
    public function a_grade_whose_id_is_not_the_locked_one_is_reported(): void
    {
        $this->seedAppSide();
        Grade::where('name', 'A')->update(['name' => 'X']);
        Grade::unguarded(fn () => Grade::create(['id' => 9, 'name' => 'A', 'is_active' => true]));

        $this->assertContains('grade A has id 9, should be 3 (locked id)', LegacyGradeMap::appSideProblems());
    }
}
