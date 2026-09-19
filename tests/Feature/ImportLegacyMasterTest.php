<?php

namespace Tests\Feature;

use App\Models\CattleClass;
use App\Models\Grade;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Langkah 1 dari issue #471: infrastruktur parser dump + empat mapping
 * tanpa dependensi (cuts, grade, supplier, cattle_class). Fixture-nya
 * dikarang sendiri, kecil, meniru bentuk snapshot legacy -- dump ASLI
 * tidak pernah disentuh test (gitignored, ada data pelanggan).
 */
class ImportLegacyMasterTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = <<<'SQL'
DROP TABLE IF EXISTS `cuts`;
CREATE TABLE `cuts` (
  `idcut` int(11) NOT NULL AUTO_INCREMENT,
  `nmcut` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`idcut`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
INSERT INTO `cuts` (`idcut`,`nmcut`) VALUES ('1','PRIME CUT');
INSERT INTO `cuts` (`idcut`,`nmcut`) VALUES ('2','SECONDARY CUT');
DROP TABLE IF EXISTS `grade`;
CREATE TABLE `grade` (
  `idgrade` int(11) NOT NULL AUTO_INCREMENT,
  `nmgrade` char(3) DEFAULT NULL,
  PRIMARY KEY (`idgrade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `grade` (`idgrade`,`nmgrade`) VALUES ('1','J01');
INSERT INTO `grade` (`idgrade`,`nmgrade`) VALUES ('2','');
DROP TABLE IF EXISTS `supplier`;
CREATE TABLE `supplier` (
  `idsupplier` int(11) NOT NULL AUTO_INCREMENT,
  `nmsupplier` varchar(100) DEFAULT NULL,
  `jenis_usaha` varchar(100) DEFAULT NULL,
  `alamat` varchar(200) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `npwp` varchar(20) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  PRIMARY KEY (`idsupplier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`jenis_usaha`,`alamat`,`telepon`,`npwp`,`iduser`) VALUES ('1','H. DONI','SAPI','RPH TAPOS, DEPOK','081225834627',NULL,NULL);
INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`jenis_usaha`,`alamat`,`telepon`,`npwp`,`iduser`) VALUES ('2','UNGGUL HARAPAN PANGAN','DAGING','BOGOR','','95.150.770.6-436.000',NULL);
DROP TABLE IF EXISTS `cattle_class`;
CREATE TABLE `cattle_class` (
  `idclass` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`idclass`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `cattle_class` (`idclass`,`class_name`,`created_at`,`updated_at`) VALUES ('1','STEER','2026-01-02 03:48:31',NULL);
INSERT INTO `cattle_class` (`idclass`,`class_name`,`created_at`,`updated_at`) VALUES ('2','BULL','2026-01-02 03:48:31',NULL);
SQL;

    private string $sourcePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = tempnam(sys_get_temp_dir(), 'legacy-fixture-').'.sql';
        file_put_contents($this->sourcePath, self::FIXTURE);
    }

    protected function tearDown(): void
    {
        if (is_file($this->sourcePath)) {
            unlink($this->sourcePath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_requires_a_source_option(): void
    {
        $this->artisan('legacy:import-master')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_rejects_a_missing_source_file(): void
    {
        $this->artisan('legacy:import-master', ['--source' => '/does/not/exist.sql'])
            ->assertExitCode(1);
    }

    /** @test */
    public function dry_run_reports_without_writing_anything(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath])
            ->assertExitCode(0);

        $this->assertSame(0, ProductCategory::count());
        $this->assertSame(0, Grade::count());
        $this->assertSame(0, Supplier::count());
        $this->assertSame(0, CattleClass::count());
    }

    /** @test */
    public function apply_actually_creates_the_records(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('product_categories', ['name' => 'PRIME CUT', 'prefix' => 1]);
        $this->assertDatabaseHas('product_categories', ['name' => 'SECONDARY CUT', 'prefix' => 2]);
        $this->assertDatabaseHas('grades', ['name' => 'J01']);
        $this->assertDatabaseHas('suppliers', ['name' => 'H. DONI', 'pic' => '-', 'top_days' => 0]);
        $this->assertDatabaseHas('suppliers', ['name' => 'UNGGUL HARAPAN PANGAN']);
        $this->assertDatabaseHas('cattle_classes', ['name' => 'STEER']);
        $this->assertDatabaseHas('cattle_classes', ['name' => 'BULL']);
    }

    /** @test */
    public function running_apply_twice_does_not_duplicate_anything(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(1, Grade::count()); // baris kedua namanya kosong, dilewati sebagai konflik
        $this->assertSame(2, Supplier::count());
        $this->assertSame(2, CattleClass::count());
    }

    /** @test */
    public function an_existing_record_is_matched_by_name_and_skipped(): void
    {
        ProductCategory::create(['name' => 'PRIME CUT', 'prefix' => 99]);

        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(2, ProductCategory::count());
    }

    /** @test */
    public function a_blank_name_is_reported_as_a_conflict_not_guessed(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('idgrade=2: nama kosong, dilewati.');

        $this->assertSame(1, Grade::count());
    }

    /** @test */
    public function a_prefix_already_used_by_a_different_category_is_reported_as_a_conflict(): void
    {
        ProductCategory::create(['name' => 'SOMETHING ELSE', 'prefix' => 1]);

        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('PRIME CUT: prefix 1 (dari idcut) sudah dipakai kategori lain, dilewati.');

        $this->assertDatabaseMissing('product_categories', ['name' => 'PRIME CUT']);
    }

    /** @test */
    public function a_supplier_npwp_is_reported_as_a_conflict_since_there_is_no_destination_column(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('NPWP legacy "95.150.770.6-436.000" tidak diimpor');
    }
}
