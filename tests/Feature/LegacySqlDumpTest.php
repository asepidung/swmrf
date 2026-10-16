<?php

namespace Tests\Feature;

use App\Support\LegacySqlDump;
use Tests\TestCase;

/**
 * Parser dump legacy: baris tidak boleh hilang diam-diam (snapshot 16 Okt 2026 kehilangan 6 dari 306 pelanggan).
 */
class LegacySqlDumpTest extends TestCase
{
    private const COLUMNS = '(`id`,`nama`,`alamat`)';

    private function dump(): string
    {
        $c = self::COLUMNS;

        return <<<SQL
CREATE TABLE `pelanggan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
INSERT INTO `pelanggan` {$c} VALUES ('1','BIASA','Jl. Mawar No. 1');
INSERT INTO `pelanggan` {$c} VALUES ('2','TITIK KOMA','KARYADEKA; INDUS. EST KAV. C3');
INSERT INTO `pelanggan` {$c} VALUES ('3','D\'GUNUNGAN MAJU','GRAHA FAMILI; BABATAN');
INSERT INTO `pelanggan` {$c} VALUES ('4','KUTIP GANDA','Jl. O''Brien');
INSERT INTO `pelanggan` {$c} VALUES ('5','GARIS MIRING','C:\\gudang','x');
INSERT INTO `pelanggan` {$c} VALUES ('6','TERAKHIR',NULL);
SQL;
    }

    /** @test */
    public function a_semicolon_or_an_escaped_quote_inside_a_value_does_not_lose_or_cut_a_row(): void
    {
        $rows = collect(LegacySqlDump::parseTable($this->dump(), 'pelanggan'))->keyBy('id');

        $this->assertEquals([1, 2, 3, 4, 6], $rows->keys()->all(), 'Baris 5 sengaja salah jumlah kolom; sisanya lengkap.');
        $this->assertSame('KARYADEKA; INDUS. EST KAV. C3', $rows['2']['alamat']);
        $this->assertSame("D'GUNUNGAN MAJU", $rows['3']['nama']);
        $this->assertSame('GRAHA FAMILI; BABATAN', $rows['3']['alamat']);
        $this->assertSame("Jl. O'Brien", $rows['4']['alamat']);
        $this->assertNull($rows['6']['alamat']);
    }
}
