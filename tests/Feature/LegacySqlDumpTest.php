<?php

namespace Tests\Feature;

use App\Support\LegacySqlDump;
use Tests\TestCase;

/**
 * `LegacySqlDump` membaca TEKS dump mysqldump/phpMyAdmin tanpa pernah
 * mengeksekusinya. Fixture di sini kecil dan dikarang sendiri (BUKAN
 * potongan dump asli -- itu gitignored dan berisi data pelanggan), tapi
 * meniru persis bentuk yang dipakai snapshot legacy: `CREATE TABLE`
 * dengan `KEY`/`CONSTRAINT` di sela kolom, lalu satu INSERT per baris.
 */
class LegacySqlDumpTest extends TestCase
{
    private const FIXTURE = <<<'SQL'
-- snapshot master legacy produksi (contoh)
SET NAMES utf8mb4;
DROP TABLE IF EXISTS `cuts`;
CREATE TABLE `cuts` (
  `idcut` int(11) NOT NULL AUTO_INCREMENT,
  `nmcut` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`idcut`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `cuts` (`idcut`,`nmcut`) VALUES ('1','PRIME CUT');
INSERT INTO `cuts` (`idcut`,`nmcut`) VALUES ('2','SECONDARY CUT');
DROP TABLE IF EXISTS `supplier`;
CREATE TABLE `supplier` (
  `idsupplier` int(11) NOT NULL AUTO_INCREMENT,
  `nmsupplier` varchar(100) DEFAULT NULL,
  `alamat` varchar(200) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  PRIMARY KEY (`idsupplier`),
  KEY `iduser` (`iduser`),
  CONSTRAINT `supplier_ibfk_1` FOREIGN KEY (`iduser`) REFERENCES `users` (`idusers`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`alamat`,`iduser`) VALUES ('1','ESTIKA TATA TIARA PT','JL. RAYA, BLOK M NO. 11, JAKARTA',NULL);
INSERT INTO `supplier` (`idsupplier`,`nmsupplier`,`alamat`,`iduser`) VALUES ('2','O''CONNOR TRADING',NULL,NULL);
DROP TABLE IF EXISTS `grade`;
CREATE TABLE `grade` (
  `idgrade` int(11) NOT NULL AUTO_INCREMENT,
  `nmgrade` char(3) DEFAULT NULL,
  PRIMARY KEY (`idgrade`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `grade` (`idgrade`,`nmgrade`) VALUES ('1','J01'),('2','J02'),('5','J03');
SQL;

    /** @test */
    public function it_parses_a_simple_two_column_table(): void
    {
        $rows = LegacySqlDump::parseTable(self::FIXTURE, 'cuts');

        $this->assertCount(2, $rows);
        $this->assertSame(['idcut' => '1', 'nmcut' => 'PRIME CUT'], $rows[0]);
        $this->assertSame(['idcut' => '2', 'nmcut' => 'SECONDARY CUT'], $rows[1]);
    }

    /** @test */
    public function it_turns_bare_sql_null_into_php_null_without_touching_quoted_text(): void
    {
        $rows = LegacySqlDump::parseTable(self::FIXTURE, 'supplier');

        $this->assertNull($rows[0]['iduser']);
        $this->assertNull($rows[1]['alamat']);
    }

    /** @test */
    public function it_keeps_commas_inside_a_quoted_value_intact(): void
    {
        $rows = LegacySqlDump::parseTable(self::FIXTURE, 'supplier');

        $this->assertSame('JL. RAYA, BLOK M NO. 11, JAKARTA', $rows[0]['alamat']);
    }

    /** @test */
    public function it_unescapes_a_doubled_quote_inside_a_quoted_value(): void
    {
        $rows = LegacySqlDump::parseTable(self::FIXTURE, 'supplier');

        $this->assertSame("O'CONNOR TRADING", $rows[1]['nmsupplier']);
    }

    /** @test */
    public function it_parses_a_multi_row_values_clause(): void
    {
        $rows = LegacySqlDump::parseTable(self::FIXTURE, 'grade');

        $this->assertCount(3, $rows);
        $this->assertSame('J01', $rows[0]['nmgrade']);
        $this->assertSame('J02', $rows[1]['nmgrade']);
        $this->assertSame('J03', $rows[2]['nmgrade']);
    }

    /** @test */
    public function an_unknown_table_returns_no_rows(): void
    {
        $this->assertSame([], LegacySqlDump::parseTable(self::FIXTURE, 'does_not_exist'));
    }
}
