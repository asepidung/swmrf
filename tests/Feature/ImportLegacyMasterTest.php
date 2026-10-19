<?php

namespace Tests\Feature;

use App\Models\CattleClass;
use App\Models\CustomerGroup;
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
INSERT INTO `grade` (`idgrade`,`nmgrade`) VALUES ('2','J02');
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
INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`jenis_usaha`,`alamat`,`telepon`,`npwp`,`iduser`) VALUES ('4','CV.GARI-SETIAWAN MAKMUR','SAPI','PANDEGLANG','','',NULL);
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
DROP TABLE IF EXISTS `groupcs`;
CREATE TABLE `groupcs` (
  `idgroup` int(11) NOT NULL AUTO_INCREMENT,
  `nmgroup` varchar(20) DEFAULT NULL,
  `terms` char(3) DEFAULT NULL,
  PRIMARY KEY (`idgroup`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
INSERT INTO `groupcs` (`idgroup`,`nmgroup`,`terms`) VALUES ('12','HPM',NULL);
INSERT INTO `groupcs` (`idgroup`,`nmgroup`,`terms`) VALUES ('15','LION',NULL);
INSERT INTO `groupcs` (`idgroup`,`nmgroup`,`terms`) VALUES ('20','',NULL);
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

    private function apply(): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true]);
    }

    /** @test */
    public function only_runs_the_named_step(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--apply' => true, '--only' => 'kelas-sapi'])
            ->assertSuccessful();

        $this->assertGreaterThan(0, \App\Models\CattleClass::count());
        $this->assertSame(0, \App\Models\Supplier::count());
        $this->assertSame(0, \App\Models\Product::count());
    }

    /** @test */
    public function an_unknown_only_step_is_rejected(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath, '--only' => 'salah'])->assertFailed();
    }

    /** @test */
    public function dry_run_reports_without_writing_anything(): void
    {
        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertSame(0, ProductCategory::count());
        $this->assertSame(0, Supplier::count());
        $this->assertSame(0, CattleClass::count());
        $this->assertSame(0, CustomerGroup::count());
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Material::count());
    }

    /** @test */
    public function apply_creates_the_records(): void
    {
        $this->apply()->assertExitCode(0);

        $this->assertDatabaseHas('product_categories', ['name' => 'PRIME CUT', 'prefix' => 1]);
        $this->assertDatabaseHas('product_categories', ['name' => 'SECONDARY CUT', 'prefix' => 2]);
        $this->assertDatabaseHas('suppliers', ['name' => 'H. DONI', 'pic' => '-', 'top_days' => 0]);
        $this->assertDatabaseHas('suppliers', ['name' => 'UNGGUL HARAPAN PANGAN']);
        $this->assertDatabaseHas('cattle_classes', ['name' => 'STEER']);
        $this->assertDatabaseHas('cattle_classes', ['name' => 'BULL']);
        $this->assertDatabaseHas('customer_groups', ['name' => 'LION']);
    }

    // =========================================================================
    // ID LEGACY DIPERTAHANKAN (keputusan Ayah, 13 Oktober 2026)
    // =========================================================================

    /** @test */
    public function every_row_keeps_its_legacy_id(): void
    {
        $this->apply()->assertExitCode(0);

        $this->assertSame('PRIME CUT', ProductCategory::find(1)->name);
        $this->assertSame('SECONDARY CUT', ProductCategory::find(2)->name);
        $this->assertSame('H. DONI', Supplier::find(1)->name);
        $this->assertSame('UNGGUL HARAPAN PANGAN', Supplier::find(2)->name);
        $this->assertSame('STEER', CattleClass::find(1)->name);
        $this->assertSame('BULL', CattleClass::find(2)->name);
        $this->assertSame(15, (int) CustomerGroup::where('name', 'LION')->value('id'));
        $this->assertSame(12, (int) CustomerGroup::where('name', 'HPM')->value('id'));

        $this->assertSame('TENDERLOIN', Product::find(1)->name);
        $this->assertSame('TENDERLOIN BUTT', Product::find(2)->name);
        $this->assertSame('KARTON TOP TEST', Material::find(1)->name);
        $this->assertSame('KARTON', MaterialCategory::find(2)->name);
        $this->assertSame('PLASTIK', MaterialCategory::find(3)->name);
    }

    /** @test */
    public function foreign_keys_use_the_legacy_ids_as_they_are(): void
    {
        $this->apply()->assertExitCode(0);

        $this->assertSame(1, (int) Product::find(1)->category_id, 'products.category_id = idcut.');
        $this->assertSame(2, (int) Material::find(1)->material_category_id, 'materials.material_category_id = idrawcategory.');
        $this->assertSame(3, (int) Material::find(2)->material_category_id);

        $bom = ProductMaterial::find(1);
        $this->assertSame([1, 1], [(int) $bom->product_id, (int) $bom->material_id], 'product_materials: idbom, idbarang, idrawmate.');
        $this->assertSame([1, 2], [(int) ProductMaterial::find(2)->product_id, (int) ProductMaterial::find(2)->material_id]);
    }

    /** @test */
    public function the_auto_increment_continues_after_the_highest_legacy_id(): void
    {
        $this->apply()->assertExitCode(0);

        $supplier = Supplier::create(['name' => 'SUPPLIER BARU', 'address' => '-', 'pic' => '-', 'top_days' => 0]);
        $group = CustomerGroup::create(['name' => 'GRUP BARU']);

        $this->assertSame(5, $supplier->id, 'Suppliers legacy 1..4, berikutnya 5.');
        $this->assertSame(16, $group->id, 'Grup legacy sampai 15, berikutnya 16.');
    }

    /** @test */
    public function skipped_legacy_rows_leave_a_gap_in_the_ids_and_nothing_else(): void
    {
        $this->apply()->assertExitCode(0);

        $this->assertNull(Product::find(3), 'idbarang 3 (kode "-") dilewati.');
        $this->assertNull(Product::find(5), 'idbarang 5 (kategori tidak ada) dilewati.');
        $this->assertNotNull(Product::find(6));
        $this->assertNull(Material::find(3), 'ATK di luar cakupan.');
        $this->assertNull(Material::find(4), 'Tanpa satuan.');
        $this->assertNull(MaterialCategory::find(1));
        $this->assertNull(MaterialCategory::find(26));
    }

    /** @test */
    public function an_id_already_used_with_different_content_is_refused_and_nothing_at_all_is_written(): void
    {
        Supplier::unguarded(fn () => Supplier::create(['id' => 1, 'name' => 'ORANG LAIN', 'address' => '-', 'pic' => '-', 'top_days' => 0]));

        $this->apply()
            ->expectsOutputToContain('DIBATALKAN')
            ->expectsOutputToContain('id 1: sudah berisi [name=ORANG LAIN], legacy [H. DONI] -- DITOLAK')
            ->assertExitCode(1);

        $this->assertSame('ORANG LAIN', Supplier::find(1)->name, 'Tidak ditimpa.');
        $this->assertSame(0, ProductCategory::count(), 'Tabel lain pun tidak ditulis.');
        $this->assertSame(0, Product::count());
        $this->assertSame(0, CustomerGroup::count());
    }

    /** @test */
    public function the_dry_run_also_reports_the_refusal_and_fails(): void
    {
        ProductCategory::unguarded(fn () => ProductCategory::create(['id' => 1, 'name' => 'KATEGORI LAIN', 'prefix' => 77]));

        $this->artisan('legacy:import-master', ['--source' => $this->sourcePath])
            ->expectsOutputToContain('--apply akan DIBATALKAN')
            ->assertExitCode(1);

        $this->assertSame(1, ProductCategory::count());
    }

    /** @test */
    public function an_id_already_used_with_the_same_content_is_skipped_not_refused(): void
    {
        ProductCategory::unguarded(fn () => ProductCategory::create(['id' => 1, 'name' => 'PRIME CUT', 'prefix' => 1]));

        $this->apply()->assertExitCode(0);

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame('SECONDARY CUT', ProductCategory::find(2)->name);
    }

    /** @test */
    public function matching_by_name_never_decides_the_id(): void
    {
        // Nama yang sama di id LAIN: tidak dipakai sebagai id; nilai unik yang sama persis tidak bisa dimasukkan dua kali.
        ProductCategory::unguarded(fn () => ProductCategory::create(['id' => 9, 'name' => 'PRIME CUT', 'prefix' => 90]));

        $this->apply()
            ->expectsOutputToContain('name "PRIME CUT" sama persis dengan id 9, tidak diimpor')
            ->assertExitCode(0);

        $this->assertNull(ProductCategory::find(1), 'Id 1 tidak diisi dengan baris yang namanya sudah dipakai id lain.');
        $this->assertSame('PRIME CUT', ProductCategory::find(9)->name);
    }

    /** @test */
    public function running_apply_twice_does_not_duplicate_anything(): void
    {
        $this->apply()->assertExitCode(0);
        $this->apply()->expectsOutputToContain('sama (dilewati)')->assertExitCode(0);

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(0, Grade::count()); // grade legacy tidak diimpor sebagai baris
        $this->assertSame(4, Supplier::count()); // keempat diimpor; yang beda spasi dilaporkan sebagai konflik
        $this->assertSame(2, CattleClass::count());
        $this->assertSame(2, CustomerGroup::count());
        $this->assertSame(4, Product::count()); // idbarang 1,2,6,7
        $this->assertSame(4, Material::count()); // idrawmate 1,2,5,6
        $this->assertSame(2, ProductMaterial::count()); // idbom 1 dan 2
    }

    // =========================================================================
    // Konflik yang sudah dikenal
    // =========================================================================

    /** @test */
    public function legacy_grades_are_not_imported_as_rows_but_verified_against_the_translation_table(): void
    {
        $this->apply()
            ->expectsOutputToContain('J01 (idgrade 1) -> gudang JGL + grade CHILL')
            ->expectsOutputToContain('J02 (idgrade 2) -> gudang JGL + grade FROZEN')
            ->assertExitCode(0);

        $this->assertSame(0, Grade::count(), 'Grade legacy menggabungkan gudang + kondisi; tidak diimpor sebagai baris.');
    }

    /** @test */
    public function a_legacy_grade_outside_the_translation_table_is_refused_and_nothing_is_written(): void
    {
        file_put_contents($this->sourcePath, self::FIXTURE."\nINSERT INTO `grade` (`idgrade`,`nmgrade`) VALUES ('9','X99');");

        $this->apply()->expectsOutputToContain('idgrade=9 (X99): di luar tabel terjemahan')->assertExitCode(1);

        $this->assertSame(0, Supplier::count(), 'Satu penolakan membatalkan seluruh penulisan.');
    }

    /** @test */
    public function a_prefix_already_used_by_another_category_id_is_reported_as_a_conflict(): void
    {
        ProductCategory::unguarded(fn () => ProductCategory::create(['id' => 5, 'name' => 'SOMETHING ELSE', 'prefix' => 1]));

        $this->apply()->expectsOutputToContain('prefix "1" sama persis dengan id 5');

        $this->assertDatabaseMissing('product_categories', ['name' => 'PRIME CUT']);
    }

    /** @test */
    public function a_supplier_npwp_is_reported_as_a_conflict_since_there_is_no_destination_column(): void
    {
        $this->apply()->expectsOutputToContain('NPWP legacy "95.150.770.6-436.000" tidak diimpor');
    }

    /** @test */
    public function a_customer_group_with_a_blank_name_is_reported(): void
    {
        $this->apply()->expectsOutputToContain('idgroup=20: nama kosong, dilewati.');

        $this->assertNull(CustomerGroup::find(20));
    }

    // =========================================================================
    // Duplikat beda spasi/format: KEDUANYA diimpor, dilaporkan (keputusan Ayah)
    // =========================================================================

    /** @test */
    public function a_supplier_that_only_differs_by_spacing_is_imported_twice_and_reported(): void
    {
        $this->apply()
            ->expectsOutputToContain('mirip "GARI SETIAWAN MAKMUR CV" (id 3)')
            ->expectsOutputToContain('KEDUANYA diimpor')
            ->assertExitCode(0);

        $this->assertSame('GARI SETIAWAN MAKMUR CV', Supplier::find(3)->name);
        $this->assertSame('GARI-SETIAWAN MAKMUR CV', Supplier::find(4)->name);
    }

    /** @test */
    public function merged_suppliers_are_not_created_and_their_goods_move_to_the_survivor(): void
    {
        $sql = "DROP TABLE IF EXISTS `supplier`;
CREATE TABLE `supplier` (
  `idsupplier` int(11) NOT NULL AUTO_INCREMENT,
  `nmsupplier` varchar(100) DEFAULT NULL,
  `jenis_usaha` varchar(100) DEFAULT NULL,
  `alamat` varchar(100) DEFAULT NULL,
  `telepon` varchar(30) DEFAULT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  PRIMARY KEY (`idsupplier`)
);
"
            ."INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`jenis_usaha`,`alamat`,`telepon`,`npwp`,`iduser`) VALUES ('14','SINERGY INDOPACK MAKMUR PT','DRY LOCK','TANGERANG','',NULL,NULL);
"
            ."INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`jenis_usaha`,`alamat`,`telepon`,`npwp`,`iduser`) VALUES ('26','Sinergy Indopack Makmur','Plastik 350x500','Tanggerang','',NULL,NULL);
";
        file_put_contents($this->sourcePath, $sql);

        $this->apply()->assertExitCode(0);

        $this->assertNull(Supplier::find(14));
        $this->assertSame('PLASTIK 350X500; DRY LOCK', Supplier::find(26)->supplied_goods);
    }

    /** @test */
    public function material_code_is_mtr_plus_the_legacy_id(): void
    {
        $this->apply()->assertExitCode(0);

        foreach (\App\Models\Material::all() as $material) {
            $this->assertSame('MTR'.str_pad((string) $material->id, 3, '0', STR_PAD_LEFT), $material->code);
        }
        $this->assertGreaterThan(0, \App\Models\Material::count());
    }

    /** @test */
    public function empty_units_follow_the_agreed_rules(): void
    {
        $ddl = "CREATE TABLE `rawcategory` (
  `idrawcategory` int(11) NOT NULL AUTO_INCREMENT,
  `nmcategory` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`idrawcategory`)
);
"
            ."CREATE TABLE `rawmate` (
  `idrawmate` int(11) NOT NULL AUTO_INCREMENT,
  `kdrawmate` varchar(10) DEFAULT NULL,
  `nmrawmate` varchar(50) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  `idrawcategory` int(11) DEFAULT NULL,
  `stock` tinyint(1) DEFAULT 1,
  `unit` varchar(10) DEFAULT NULL,
  `barmin` int(11) DEFAULT NULL,
  PRIMARY KEY (`idrawmate`)
);
";
        $cat = $ddl."INSERT INTO `rawcategory` (`idrawcategory`,`nmcategory`) VALUES ('24','MEAT PROCESSING');
";
        $row = fn (int $id, string $name) => "INSERT INTO `rawmate` (`idrawmate`,`kdrawmate`,`nmrawmate`,`iduser`,`idrawcategory`,`stock`,`unit`,`barmin`) VALUES ('{$id}','RM{$id}','{$name}',NULL,'24','1',NULL,'0');
";
        file_put_contents($this->sourcePath, $cat.$row(56, 'FOOT SWITCH').$row(114, 'BUMBU SERBAGUNA SAPI').$row(151, 'KECAP MANIS').$row(180, 'TALI KASUR'));

        $this->apply()->assertExitCode(0);

        $this->assertFalse((bool) Material::find(56)->is_active, 'Bukan bahan produksi: nonaktif.');
        $this->assertSame('PCS', Material::find(56)->unit->name);
        $this->assertSame('KG', Material::find(114)->unit->name);
        $this->assertSame('LITER', Material::find(151)->unit->name);
        $this->assertSame('PCS', Material::find(180)->unit->name);
        $this->assertTrue((bool) Material::find(114)->is_active);
        $this->assertSame('MTR114', Material::find(114)->code);
    }

    /** @test */
    public function truncated_group_names_are_restored_and_company_names_are_tidied(): void
    {
        $ddl = "CREATE TABLE `groupcs` (
  `idgroup` int(11) NOT NULL AUTO_INCREMENT,
  `nmgroup` varchar(20) DEFAULT NULL,
  `terms` int(11) DEFAULT NULL,
  PRIMARY KEY (`idgroup`)
);
";
        file_put_contents($this->sourcePath, $ddl
            ."INSERT INTO `groupcs` (`idgroup`,`nmgroup`,`terms`) VALUES ('43','PT. REPUBLIK MANOR P',NULL);
"
            ."INSERT INTO `groupcs` (`idgroup`,`nmgroup`,`terms`) VALUES ('5','CV BERKAH',NULL);
");

        $this->apply()->assertExitCode(0);

        $this->assertSame('REPUBLIK MANOR PROPERTINDO PT', CustomerGroup::find(43)->name);
        $this->assertSame('BERKAH CV', CustomerGroup::find(5)->name);
    }

    /** @test */
    public function a_product_that_only_differs_by_spacing_is_imported_twice_and_reported(): void
    {
        $this->apply()->expectsOutputToContain('mirip "STRIPLOIN CUT A" (id 6)');

        $this->assertSame('STRIPLOIN CUT A', Product::find(6)->name);
        $this->assertSame('STRIPLOIN  CUT A', Product::find(7)->name);
    }

    /** @test */
    public function a_material_that_only_differs_by_spacing_is_imported_twice_and_reported(): void
    {
        $this->apply()->expectsOutputToContain('mirip "KARTON EXTRA TEST" (id 5)');

        $this->assertSame('KARTON EXTRA TEST', Material::find(5)->name);
        $this->assertSame('KARTON  EXTRA TEST', Material::find(6)->name);
    }

    // =========================================================================
    // barang -> products
    // =========================================================================

    /** @test */
    public function apply_creates_valid_products_with_their_legacy_note(): void
    {
        $this->apply()->assertExitCode(0);

        $this->assertDatabaseHas('products', ['code' => '100100', 'name' => 'TENDERLOIN']);

        $withKodeinduk = Product::where('code', '100101')->first();
        $this->assertNotNull($withKodeinduk);
        $this->assertSame('sub', $withKodeinduk->structure_type);
        $this->assertSame(Product::where('code', '100100')->value('id'), $withKodeinduk->parent_id);
    }

    /** @test */
    public function a_non_meat_item_is_not_imported_and_a_dash_code_item_is_a_conflict_or_inactive(): void
    {
        $ddl = "CREATE TABLE `cuts` (
  `idcut` int(11) NOT NULL AUTO_INCREMENT,
  `nmcut` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`idcut`)
);
INSERT INTO `cuts` (`idcut`,`nmcut`) VALUES ('2','SECONDARY CUT');
"
            ."CREATE TABLE `barang` (
  `idbarang` int(11) NOT NULL AUTO_INCREMENT,
  `kdbarang` varchar(10) DEFAULT NULL,
  `kodeinduk` varchar(10) DEFAULT NULL,
  `nmbarang` varchar(50) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  `idcut` int(11) DEFAULT NULL,
  `karton` varchar(20) DEFAULT NULL,
  `drylog` varchar(20) DEFAULT NULL,
  `plastik` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`idbarang`)
);
";
        $row = fn (int $id, string $code, string $name) => "INSERT INTO `barang` (`idbarang`,`kdbarang`,`kodeinduk`,`nmbarang`,`iduser`,`idcut`,`karton`,`drylog`,`plastik`) VALUES ('{$id}','{$code}',NULL,'{$name}',NULL,'2',NULL,NULL,NULL);
";
        file_put_contents($this->sourcePath, $ddl.$row(3, '-', 'ICE GELL').$row(4, '-', 'LUNG').$row(8, '-', 'BARANG ANEH').$row(9, '200100', 'TOPSIDE'));

        $this->apply()
            ->expectsOutputToContain('BARANG ANEH (idbarang=8): kode legacy kosong')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('products', ['name' => 'ICE GELL']);
        $this->assertDatabaseMissing('products', ['name' => 'BARANG ANEH']);
        $lung = Product::where('name', 'LUNG')->first();
        $this->assertSame('TMP-004', $lung->code);
        $this->assertFalse((bool) $lung->is_active);
        $this->assertTrue((bool) Product::where('code', '200100')->first()->is_active);
    }

    /** @test */
    public function a_product_whose_category_is_not_imported_is_a_conflict(): void
    {
        $this->apply()->expectsOutputToContain('idcut=99');

        $this->assertDatabaseMissing('products', ['name' => 'ORPHAN CUT ITEM']);
    }

    // =========================================================================
    // rawcategory/rawmate -> material_categories/materials
    // =========================================================================

    /** @test */
    public function only_the_whitelisted_material_categories_are_imported(): void
    {
        $this->apply()->assertExitCode(0);

        $this->assertDatabaseHas('material_categories', ['name' => 'KARTON']);
        $this->assertDatabaseHas('material_categories', ['name' => 'PLASTIK']);
        $this->assertDatabaseMissing('material_categories', ['name' => 'LAIN-LAIN']);
        $this->assertDatabaseMissing('material_categories', ['name' => 'ATK']);
    }

    /** @test */
    public function a_material_in_a_non_whitelisted_category_is_silently_out_of_scope_not_a_conflict(): void
    {
        $this->apply()->assertExitCode(0);

        $this->assertDatabaseMissing('materials', ['name' => 'ATK PULPEN']);
    }

    /** @test */
    public function a_material_without_a_unit_is_a_conflict_not_a_guess(): void
    {
        $this->apply()->expectsOutputToContain('KARTON NO UNIT (idrawmate=4): satuan kosong di legacy');

        $this->assertDatabaseMissing('materials', ['name' => 'KARTON NO UNIT']);
    }

    /** @test */
    public function a_valid_material_is_created_with_its_unit_and_min_stock(): void
    {
        $this->apply()->assertExitCode(0);

        $material = Material::where('name', 'KARTON TOP TEST')->first();
        $this->assertNotNull($material);
        $this->assertSame(20, $material->min_stock);
        $this->assertSame('IKAT', $material->unit->name);
        $this->assertSame('KARTON', $material->category->name);
        // Kode legacy (RM0001) TIDAK dipertahankan -- Material sudah punya penomoran sendiri.
        $this->assertStringStartsWith('MTR', $material->code);
    }

    // =========================================================================
    // bom_rawmate -> product_materials
    // =========================================================================

    /** @test */
    public function an_active_bom_row_becomes_a_product_material_with_box_basis(): void
    {
        $this->apply()->assertExitCode(0);

        $link = ProductMaterial::where('product_id', 1)->where('material_id', 1)->first();
        $this->assertNotNull($link);
        $this->assertSame(2, $link->quantity);
        $this->assertSame('box', $link->basis);
    }

    /** @test */
    public function an_inactive_bom_row_is_never_imported(): void
    {
        $this->apply()->assertExitCode(0);

        // idbom=5 (idbarang=2, idrawmate=1, is_active=0) tidak boleh menghasilkan apa pun.
        $this->assertNull(ProductMaterial::find(5));
        $this->assertFalse(ProductMaterial::where('product_id', 2)->where('material_id', 1)->exists());
    }

    /** @test */
    public function a_bom_row_pointing_to_an_unimported_product_is_a_conflict(): void
    {
        $this->apply()->expectsOutputToContain('idbom=3: idbarang=3 tidak ada/tidak diimpor sebagai produk');
    }

    /** @test */
    public function a_bom_row_pointing_to_an_out_of_scope_material_is_a_conflict(): void
    {
        $this->apply()->expectsOutputToContain('idbom=4: idrawmate=3 tidak ada/tidak diimpor sebagai material');
    }

    /** @test */
    public function a_plastic_material_bom_row_is_flagged_for_review_but_still_imported_as_box(): void
    {
        $this->apply()->expectsOutputToContain('TINJAU: mungkin per-pcs, bukan per-box');

        $link = ProductMaterial::where('product_id', 1)->where('material_id', 2)->first();
        $this->assertNotNull($link);
        $this->assertSame('box', $link->basis);
    }
}
