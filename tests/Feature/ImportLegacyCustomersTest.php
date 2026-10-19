<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Support\LegacySqlDump;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `legacy:import-master` -- bagian customers (#535 A.1): id legacy, grup, TOP, diskon LION, segmen
 * tunggal "UMUM", dokumen wajib. Fixture dikarang sendiri (dump asli tidak pernah disentuh test).
 */
class ImportLegacyCustomersTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = '(`idcustomer`,`nama_customer`,`alamat1`,`idsegment`,`top`,`sales_referensi`,`pajak`,`telepon`,`email`,`catatan`,`tanggal_update`,`tukarfaktur`,`alamat2`,`alamat3`,`idgroup`,`invoice`,`nkv`,`halal`,`sv`,`joss`,`phd`,`ujilab`)';

    private string $sourcePath;

    private function fixture(): string
    {
        $c = self::COLUMNS;

        return <<<SQL
CREATE TABLE `groupcs` (
  `idgroup` int(11) NOT NULL AUTO_INCREMENT,
  `nmgroup` varchar(100) DEFAULT NULL,
  `terms` int(11) DEFAULT NULL,
  PRIMARY KEY (`idgroup`)
) ENGINE=InnoDB;
INSERT INTO `groupcs` (`idgroup`,`nmgroup`,`terms`) VALUES ('3','CARREFOUR',NULL);
INSERT INTO `groupcs` (`idgroup`,`nmgroup`,`terms`) VALUES ('15','LION',NULL);
CREATE TABLE `customers` (
  `idcustomer` int(11) NOT NULL AUTO_INCREMENT,
  `nama_customer` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`idcustomer`)
) ENGINE=InnoDB;
INSERT INTO `customers` {$c} VALUES ('2','carrefour cempaka putih','Jl. Jend. A. Yani No.83		 Cempaka Putih','1','14',NULL,'NO','021-555','','','2023-07-17 08:17:33','YES','Lantai 2','','3','1','0','1','0','0','0','1');
INSERT INTO `customers` {$c} VALUES ('6','LION DCA SUPERINDO','KARYADEKA; INDUS. EST KAV. C3','3','4',NULL,'NO','-','','','2023-07-31 20:14:10','NO','','','15','0','0','0','0','0','0','0');
INSERT INTO `customers` {$c} VALUES ('176','D\'GUNUNGAN MAJU','GRAHA FAMILI','1','99',NULL,'NO','','-','','2024-07-23 15:08:47','NO',NULL,NULL,'77','0','0','0','0','0','0','0');
INSERT INTO `customers` {$c} VALUES ('32','','TANPA NAMA','1','7',NULL,'NO','','','','2023-08-02 11:07:18','NO','','',NULL,'0','0','0','0','0','0','0');
SQL;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = tempnam(sys_get_temp_dir(), 'legacy-customers-').'.sql';
        file_put_contents($this->sourcePath, $this->fixture());
    }

    protected function tearDown(): void
    {
        if (is_file($this->sourcePath)) {
            unlink($this->sourcePath);
        }

        parent::tearDown();
    }

    private function importer(bool $apply): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('legacy:import-master', ['--source' => $this->sourcePath] + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function the_parser_reads_a_semicolon_and_a_backslash_escaped_quote_inside_a_value(): void
    {
        $rows = collect(LegacySqlDump::parseTable($this->fixture(), 'customers'))->keyBy('idcustomer');

        $this->assertCount(4, $rows, 'Tidak ada baris yang hilang diam-diam.');
        $this->assertSame('KARYADEKA; INDUS. EST KAV. C3', $rows['6']['alamat1']);
        $this->assertSame("D'GUNUNGAN MAJU", $rows['176']['nama_customer']);
    }

    /** @test */
    public function a_dry_run_writes_no_customer_and_no_segment(): void
    {
        $this->importer(false)->assertSuccessful();

        $this->assertSame(0, Customer::count());
        $this->assertSame(0, CustomerSegment::count());
    }

    /** @test */
    public function apply_imports_customers_with_their_legacy_ids_group_top_discount_and_documents(): void
    {
        $this->importer(true)->assertSuccessful();

        $carrefour = Customer::findOrFail(2);
        $this->assertSame('CARREFOUR CEMPAKA PUTIH', $carrefour->name);
        $this->assertSame('JL. JEND. A. YANI NO.83 CEMPAKA PUTIH, LANTAI 2', $carrefour->address, 'Alamat digabung dan spasi dirapikan.');
        $this->assertSame(3, $carrefour->customer_group_id);
        $this->assertSame(14, $carrefour->top);
        $this->assertSame(0, $carrefour->default_discount);
        $this->assertSame('021-555', $carrefour->phone);
        $this->assertTrue($carrefour->invoice_exchange);
        $this->assertTrue($carrefour->is_active);
        $this->assertEqualsCanonicalizing(['Invoice', 'Sertifikat Halal', 'Uji Lab'], $carrefour->required_documents);

        $lion = Customer::findOrFail(6);
        $this->assertSame(2, $lion->default_discount, 'Grup LION (dikenali dari namanya) -> diskon 2%.');
        $this->assertNull($lion->phone, '"-" bukan telepon.');
        $this->assertNull($lion->required_documents);
        $this->assertFalse($lion->invoice_exchange);

        // Segmen menurut grup: Carrefour (grup 3) dan LION (grup 15) modern market; selain itu RETAIL.
        $this->assertSame('MODERN MARKET', Customer::find(2)->segment->name);
        $this->assertSame('MODERN MARKET', Customer::find(6)->segment->name);
        $this->assertSame('RETAIL', Customer::find(176)->segment->name);
    }

    /** @test */
    public function an_unknown_group_and_an_out_of_range_top_are_cleaned_and_a_nameless_row_is_skipped(): void
    {
        $this->importer(true)->assertSuccessful();

        $unknown = Customer::findOrFail(176);
        $this->assertNull($unknown->customer_group_id, 'Grup legacy 77 tidak ada -> tanpa grup.');
        $this->assertSame(0, $unknown->top, 'TOP 99 di luar 1-60 -> 0.');

        $this->assertNull(Customer::find(32), 'Baris tanpa nama tidak diimpor.');
    }

    /** @test */
    public function running_it_twice_is_idempotent_and_a_different_customer_on_the_same_id_is_refused(): void
    {
        $this->importer(true)->assertSuccessful();
        $this->importer(true)->assertSuccessful();

        $this->assertSame(3, Customer::count());

        Customer::where('id', 6)->update(['name' => 'ORANG LAIN']);

        $this->importer(true)->assertFailed();
        $this->assertSame('ORANG LAIN', Customer::find(6)->name, 'Tidak ditimpa.');
    }

    /** @test */
    public function same_name_customers_merge_into_the_newest_and_cipta_boga_stays_three(): void
    {
        $c = self::COLUMNS;
        $row = fn (int $id, string $name, string $updated) => "INSERT INTO `customers` {$c} VALUES ('{$id}','{$name}','ALAMAT','1','14',NULL,'NO','','','','{$updated}','NO','','',NULL,'0','0','0','0','0','0','0');
";
        $ddl = "CREATE TABLE `customers` (
  `idcustomer` int(11) NOT NULL AUTO_INCREMENT,
  `nama_customer` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`idcustomer`)
);
";
        $sql = $ddl
            .$row(121, 'HPM CYBERPARK KRWC', '2023-01-01 00:00:00')
            .$row(204, 'HPM CYBERPARK KRWC', '2024-05-01 00:00:00')
            .$row(218, 'ABUBA - CIPETE', '2024-05-01 00:00:00')
            .$row(219, 'ABUBA - CIPETE', '2024-05-01 00:00:00')
            .$row(135, 'CIPTA BOGA PRIMA PT', '2023-01-01 00:00:00')
            .$row(182, 'CIPTA BOGA PRIMA PT', '2023-01-01 00:00:00')
            .$row(188, 'CIPTA BOGA PRIMA PT', '2023-01-01 00:00:00');
        file_put_contents($this->sourcePath, $sql);

        $this->assertSame([121 => 204, 218 => 219], \App\Console\Commands\ImportLegacyMaster::customerIdMap($sql));

        $this->importer(true)->assertExitCode(0);

        $this->assertNull(Customer::find(121), 'Yang lama digabung.');
        $this->assertSame('HPM CYBERPARK KRWC', Customer::find(204)->name);
        $this->assertNull(Customer::find(218), 'Tanggal sama -> id terbesar dipakai.');
        $this->assertNotNull(Customer::find(219));
        $this->assertSame('CIPTA BOGA PRIMA PT (PASAR REBO)', Customer::find(135)->name);
        $this->assertSame('CIPTA BOGA PRIMA PT (GEDANGAN)', Customer::find(182)->name);
        $this->assertSame('CIPTA BOGA PRIMA PT (VIA EKSPEDISI ELVANO)', Customer::find(188)->name);
    }
}
