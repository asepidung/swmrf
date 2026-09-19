<?php

namespace Tests\Feature;

use App\Models\CattleClass;
use App\Models\Grade;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #471: `legacy:import-master`. Langkah 1 -- infrastruktur parser
 * dump + empat mapping tanpa dependensi (cuts, grade, supplier,
 * cattle_class). Langkah 2 -- barang/products, rawcategory+rawmate/
 * materials, bom_rawmate/product_materials (saling bergantung pada
 * langkah 1). Fixture-nya dikarang sendiri, kecil, meniru bentuk
 * snapshot legacy -- dump ASLI tidak pernah disentuh test (gitignored,
 * ada data pelanggan).
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
INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`jenis_usaha`,`alamat`,`telepon`,`npwp`,`iduser`) VALUES ('3','CV. GARI SETIAWAN MAKMUR','SAPI','PANDEGLANG','','',NULL);
INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`jenis_usaha`,`alamat`,`telepon`,`npwp`,`iduser`) VALUES ('4','CV.GARI SETIAWAN MAKMUR','SAPI','PANDEGLANG','','',NULL);
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
DROP TABLE IF EXISTS `barang`;
CREATE TABLE `barang` (
  `idbarang` int(11) NOT NULL AUTO_INCREMENT,
  `kdbarang` varchar(10) DEFAULT NULL,
  `kodeinduk` int(11) DEFAULT NULL,
  `nmbarang` varchar(30) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  `idcut` int(11) DEFAULT NULL,
  `karton` varchar(10) DEFAULT NULL,
  `drylog` int(11) DEFAULT NULL,
  `plastik` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`idbarang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `barang` (`idbarang`,`kdbarang`,`kodeinduk`,`nmbarang`,`iduser`,`idcut`,`karton`,`drylog`,`plastik`) VALUES ('1','100100',NULL,'TENDERLOIN',NULL,'1','PUTIH','3','200 X 550 MM');
INSERT INTO `barang` (`idbarang`,`kdbarang`,`kodeinduk`,`nmbarang`,`iduser`,`idcut`,`karton`,`drylog`,`plastik`) VALUES ('2','100101','100100','TENDERLOIN BUTT',NULL,'1',NULL,NULL,NULL);
INSERT INTO `barang` (`idbarang`,`kdbarang`,`kodeinduk`,`nmbarang`,`iduser`,`idcut`,`karton`,`drylog`,`plastik`) VALUES ('3','-',NULL,'ICE GELL',NULL,'2',NULL,NULL,NULL);
INSERT INTO `barang` (`idbarang`,`kdbarang`,`kodeinduk`,`nmbarang`,`iduser`,`idcut`,`karton`,`drylog`,`plastik`) VALUES ('5','999900',NULL,'ORPHAN CUT ITEM',NULL,'99',NULL,NULL,NULL);
INSERT INTO `barang` (`idbarang`,`kdbarang`,`kodeinduk`,`nmbarang`,`iduser`,`idcut`,`karton`,`drylog`,`plastik`) VALUES ('6','300100',NULL,'STRIPLOIN CUT A',NULL,'1',NULL,NULL,NULL);
INSERT INTO `barang` (`idbarang`,`kdbarang`,`kodeinduk`,`nmbarang`,`iduser`,`idcut`,`karton`,`drylog`,`plastik`) VALUES ('7','300101',NULL,'STRIPLOIN  CUT A',NULL,'1',NULL,NULL,NULL);
DROP TABLE IF EXISTS `rawcategory`;
CREATE TABLE `rawcategory` (
  `idrawcategory` int(11) NOT NULL AUTO_INCREMENT,
  `nmcategory` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`idrawcategory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `rawcategory` (`idrawcategory`,`nmcategory`) VALUES ('1','LAIN-LAIN');
INSERT INTO `rawcategory` (`idrawcategory`,`nmcategory`) VALUES ('2','KARTON');
INSERT INTO `rawcategory` (`idrawcategory`,`nmcategory`) VALUES ('3','PLASTIK');
INSERT INTO `rawcategory` (`idrawcategory`,`nmcategory`) VALUES ('26','ATK');
DROP TABLE IF EXISTS `rawmate`;
CREATE TABLE `rawmate` (
  `idrawmate` int(11) NOT NULL AUTO_INCREMENT,
  `kdrawmate` varchar(10) DEFAULT NULL,
  `nmrawmate` varchar(50) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  `idrawcategory` int(11) DEFAULT NULL,
  `stock` tinyint(1) DEFAULT 1,
  `unit` varchar(10) DEFAULT NULL,
  `barmin` int(11) DEFAULT 0,
  PRIMARY KEY (`idrawmate`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
INSERT INTO `rawmate` (`idrawmate`,`kdrawmate`,`nmrawmate`,`iduser`,`idrawcategory`,`stock`,`unit`,`barmin`) VALUES ('1','RM0001','KARTON TOP TEST',NULL,'2','1','Ikat','20');
INSERT INTO `rawmate` (`idrawmate`,`kdrawmate`,`nmrawmate`,`iduser`,`idrawcategory`,`stock`,`unit`,`barmin`) VALUES ('2','RM0002','PLASTIK WRAP TEST',NULL,'3','1','Box','10');
INSERT INTO `rawmate` (`idrawmate`,`kdrawmate`,`nmrawmate`,`iduser`,`idrawcategory`,`stock`,`unit`,`barmin`) VALUES ('3','RM0003','ATK PULPEN',NULL,'26','1','Pcs','5');
INSERT INTO `rawmate` (`idrawmate`,`kdrawmate`,`nmrawmate`,`iduser`,`idrawcategory`,`stock`,`unit`,`barmin`) VALUES ('4','RM0004','KARTON NO UNIT',NULL,'2','1',NULL,'0');
INSERT INTO `rawmate` (`idrawmate`,`kdrawmate`,`nmrawmate`,`iduser`,`idrawcategory`,`stock`,`unit`,`barmin`) VALUES ('5','RM0005','KARTON EXTRA TEST',NULL,'2','1','Box','5');
INSERT INTO `rawmate` (`idrawmate`,`kdrawmate`,`nmrawmate`,`iduser`,`idrawcategory`,`stock`,`unit`,`barmin`) VALUES ('6','RM0006','KARTON  EXTRA TEST',NULL,'2','1','Box','5');
DROP TABLE IF EXISTS `bom_rawmate`;
CREATE TABLE `bom_rawmate` (
  `idbom` int(11) NOT NULL AUTO_INCREMENT,
  `idbarang` int(11) NOT NULL,
  `idrawmate` int(11) NOT NULL,
  `qty` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `iduser` int(11) DEFAULT NULL,
  `createtime` timestamp NULL DEFAULT NULL,
  `updatetime` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`idbom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `bom_rawmate` (`idbom`,`idbarang`,`idrawmate`,`qty`,`is_active`,`iduser`,`createtime`,`updatetime`) VALUES ('1','1','1','2','1','1','2025-10-20 09:38:23','2025-10-20 09:38:23');
INSERT INTO `bom_rawmate` (`idbom`,`idbarang`,`idrawmate`,`qty`,`is_active`,`iduser`,`createtime`,`updatetime`) VALUES ('2','1','2','1','1','1','2025-10-20 09:38:23','2025-10-20 09:38:23');
INSERT INTO `bom_rawmate` (`idbom`,`idbarang`,`idrawmate`,`qty`,`is_active`,`iduser`,`createtime`,`updatetime`) VALUES ('3','3','1','1','1','1','2025-10-20 09:38:23','2025-10-20 09:38:23');
INSERT INTO `bom_rawmate` (`idbom`,`idbarang`,`idrawmate`,`qty`,`is_active`,`iduser`,`createtime`,`updatetime`) VALUES ('4','1','3','1','1','1','2025-10-20 09:38:23','2025-10-20 09:38:23');
INSERT INTO `bom_rawmate` (`idbom`,`idbarang`,`idrawmate`,`qty`,`is_active`,`iduser`,`createtime`,`updatetime`) VALUES ('5','2','1','3','0','1','2025-10-20 09:38:23','2025-10-20 09:38:23');
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
        $this->assertSame(3, Supplier::count()); // H. DONI, UNGGUL HARAPAN PANGAN, + satu dari pasangan mirip spasi
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

    // =========================================================================
    // Susulan Hafizh 20 Sep 2026: duplikat beda spasi/format
    // =========================================================================

    /** @test */
    public function a_supplier_that_only_differs_by_spacing_is_a_conflict_not_a_second_row(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('mirip "CV. GARI SETIAWAN MAKMUR" di baris lain sumber ini');

        $this->assertSame(1, Supplier::where('name', 'like', 'CV.%GARI SETIAWAN MAKMUR')->count());
    }

    /** @test */
    public function a_product_that_only_differs_by_spacing_is_a_conflict_not_a_second_row(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('mirip "STRIPLOIN CUT A" di baris lain sumber ini');

        $this->assertSame(1, Product::where('name', 'like', 'STRIPLOIN%CUT A')->count());
    }

    /** @test */
    public function a_material_that_only_differs_by_spacing_is_a_conflict_not_a_second_row(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('mirip "KARTON EXTRA TEST" di baris lain sumber ini');

        $this->assertSame(1, Material::where('name', 'like', 'KARTON%EXTRA TEST')->count());
    }

    /** @test */
    public function an_existing_supplier_with_slightly_different_spacing_is_still_matched_and_skipped(): void
    {
        Supplier::create([
            'name' => 'CV.GARI SETIAWAN MAKMUR', 'address' => 'X', 'pic' => '-', 'top_days' => 0,
        ]);

        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('CV. GARI SETIAWAN MAKMUR (cocok dengan "CV.GARI SETIAWAN MAKMUR" yang sudah ada): sudah ada.');

        $this->assertSame(1, Supplier::where('name', 'like', '%GARI SETIAWAN MAKMUR')->count());
    }

    // =========================================================================
    // Langkah 2: barang -> products
    // =========================================================================

    /** @test */
    public function apply_creates_valid_products_with_their_legacy_note(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('products', ['code' => '100100', 'name' => 'TENDERLOIN']);

        $withKodeinduk = Product::where('code', '100101')->first();
        $this->assertNotNull($withKodeinduk);
        $this->assertStringContainsString('kodeinduk legacy: 100100', $withKodeinduk->legacy_note);
    }

    /** @test */
    public function a_product_with_a_dash_code_is_a_conflict_not_a_guess(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('ICE GELL (idbarang=3): kode legacy kosong/"-", dilewati');

        $this->assertDatabaseMissing('products', ['name' => 'ICE GELL']);
    }

    /** @test */
    public function a_product_whose_category_cannot_be_resolved_is_a_conflict(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('idcut=99');

        $this->assertDatabaseMissing('products', ['name' => 'ORPHAN CUT ITEM']);
    }

    // =========================================================================
    // Langkah 2: rawcategory/rawmate -> material_categories/materials
    // =========================================================================

    /** @test */
    public function only_the_whitelisted_material_categories_are_imported(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('material_categories', ['name' => 'KARTON']);
        $this->assertDatabaseHas('material_categories', ['name' => 'PLASTIK']);
        $this->assertDatabaseMissing('material_categories', ['name' => 'LAIN-LAIN']);
        $this->assertDatabaseMissing('material_categories', ['name' => 'ATK']);
    }

    /** @test */
    public function a_material_in_a_non_whitelisted_category_is_silently_out_of_scope_not_a_conflict(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('materials', ['name' => 'ATK PULPEN']);
    }

    /** @test */
    public function a_material_without_a_unit_is_a_conflict_not_a_guess(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('KARTON NO UNIT (idrawmate=4): satuan kosong di legacy');

        $this->assertDatabaseMissing('materials', ['name' => 'KARTON NO UNIT']);
    }

    /** @test */
    public function a_valid_material_is_created_with_its_unit_and_min_stock(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $material = Material::where('name', 'KARTON TOP TEST')->first();
        $this->assertNotNull($material);
        $this->assertSame(20, $material->min_stock);
        $this->assertSame('IKAT', $material->unit->name);
        $this->assertSame('KARTON', $material->category->name);
        // Kode legacy (RM0001) TIDAK dipertahankan -- Material sudah
        // punya penomoran sendiri.
        $this->assertStringStartsWith('MTR', $material->code);
    }

    // =========================================================================
    // Langkah 2: bom_rawmate -> product_materials
    // =========================================================================

    /** @test */
    public function an_active_bom_row_becomes_a_product_material_with_box_basis(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $product = Product::where('code', '100100')->first();
        $material = Material::where('name', 'KARTON TOP TEST')->first();

        $link = ProductMaterial::where('product_id', $product->id)->where('material_id', $material->id)->first();
        $this->assertNotNull($link);
        $this->assertSame(2, $link->quantity);
        $this->assertSame('box', $link->basis);
    }

    /** @test */
    public function an_inactive_bom_row_is_never_imported(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        // idbom=5 (idbarang=2, idrawmate=1, is_active=0) tidak boleh
        // menghasilkan apa pun -- baik baris maupun konflik.
        $product = Product::where('code', '100101')->first();
        $material = Material::where('name', 'KARTON TOP TEST')->first();
        $this->assertFalse(
            ProductMaterial::where('product_id', $product->id)->where('material_id', $material->id)->exists()
        );
    }

    /** @test */
    public function a_bom_row_pointing_to_an_unimported_product_is_a_conflict(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('idbom=3: idbarang=3 tidak berhasil dipetakan ke produk');
    }

    /** @test */
    public function a_bom_row_pointing_to_an_out_of_scope_material_is_a_conflict(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('idbom=4: idrawmate=3 tidak berhasil dipetakan ke material');
    }

    /** @test */
    public function a_plastic_material_bom_row_is_flagged_for_review_but_still_imported_as_box(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->expectsOutputToContain('TINJAU: mungkin per-pcs, bukan per-box');

        $product = Product::where('code', '100100')->first();
        $material = Material::where('name', 'PLASTIK WRAP TEST')->first();
        $link = ProductMaterial::where('product_id', $product->id)->where('material_id', $material->id)->first();
        $this->assertNotNull($link);
        $this->assertSame('box', $link->basis);
    }

    /** @test */
    public function running_apply_twice_does_not_duplicate_products_materials_or_bom(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(3, Product::count()); // idbarang 1,2,6 valid; 3,5,7 konflik
        $this->assertSame(3, Material::count()); // idrawmate 1,2,5 valid; 3 di luar cakupan, 4,6 konflik
        $this->assertSame(2, ProductMaterial::count()); // idbom 1 dan 2 (3,4 konflik; 5 tidak aktif)
    }
}
