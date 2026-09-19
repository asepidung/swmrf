<?php

namespace App\Support;

/**
 * Baca baris tabel dari teks dump SQL mysqldump/phpMyAdmin -- TANPA
 * benar-benar mengeksekusi DDL-nya di mesin basis data apa pun.
 *
 * `legacy:import-master` butuh membaca 8 tabel master dari dump legacy,
 * tapi dump itu MySQL murni (AUTO_INCREMENT, ENGINE=, CHARSET=, `KEY`
 * inline) -- menjalankannya apa adanya di SQLite akan gagal di banyak
 * klausanya. Daripada menerjemahkan DDL MySQL -> SQLite (rapuh, dan tetap
 * butuh koneksi nyata untuk sesuatu yang sebenarnya cuma pembacaan teks),
 * kelas ini membaca CREATE TABLE hanya untuk urutan namA kolomnya, lalu
 * mem-parse setiap tuple INSERT VALUES sendiri.
 */
class LegacySqlDump
{
    /**
     * Seluruh baris sebuah tabel dari teks dump, sebagai array asosiatif
     * kolom => nilai (nilai `NULL` SQL menjadi `null` PHP, yang lain string).
     *
     * @return array<int, array<string, string|null>>
     */
    public static function parseTable(string $sql, string $table): array
    {
        $columns = self::columnsFor($sql, $table);

        if ($columns === []) {
            return [];
        }

        $rows = [];
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'`\s*\(([^)]*)\)\s*VALUES\s*(.*?);/s';
        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $insertColumns = array_map(
                fn (string $c): string => trim($c, " `"),
                explode(',', $match[1])
            );

            foreach (self::splitTuples($match[2]) as $tuple) {
                $values = self::parseTuple($tuple);

                // Baris yang jumlah nilainya tidak cocok dengan daftar
                // kolomnya sendiri dilewati diam-diam DI SINI -- bukan
                // konflik bisnis, melainkan tanda dump-nya tidak terbaca
                // benar oleh pemindai ini. Ditangkap tes fixture, bukan
                // sesuatu yang perlu dilaporkan tiap import.
                if (count($values) !== count($insertColumns)) {
                    continue;
                }

                $rows[] = array_combine($insertColumns, $values);
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    private static function columnsFor(string $sql, string $table): array
    {
        $pattern = '/CREATE TABLE `'.preg_quote($table, '/').'`\s*\((.*?)\n\)\s*(?:ENGINE|;)/s';

        if (! preg_match($pattern, $sql, $match)) {
            return [];
        }

        $columns = [];

        foreach (explode("\n", $match[1]) as $line) {
            $line = trim($line, " ,\t\r");

            // Baris PRIMARY KEY/UNIQUE KEY/KEY/CONSTRAINT tidak dimulai
            // dengan nama kolom bertik balik -- satu-satunya penanda yang
            // dipakai di sini, sengaja tidak menebak dari kata kuncinya
            // supaya kolom yang kebetulan bernama mirip tidak ikut terbuang.
            if (! str_starts_with($line, '`')) {
                continue;
            }

            if (preg_match('/^`([^`]+)`/', $line, $col)) {
                $columns[] = $col[1];
            }
        }

        return $columns;
    }

    /**
     * Pecah blob `(...),(...),(...)` jadi daftar isi tuple (tanpa kurung
     * pembungkusnya), menghormati tanda kutip dan kurung yang ada DI DALAM
     * nilai string (mis. alamat yang mengandung koma).
     *
     * @return array<int, string>
     */
    private static function splitTuples(string $blob): array
    {
        $tuples = [];
        $depth = 0;
        $inQuote = false;
        $current = '';
        $length = strlen($blob);

        for ($i = 0; $i < $length; $i++) {
            $char = $blob[$i];

            if ($inQuote) {
                if ($char === "'") {
                    // mysqldump menggandakan kutip untuk meng-escape-nya
                    // di dalam string ('' -> satu tanda kutip literal).
                    if ($i + 1 < $length && $blob[$i + 1] === "'") {
                        $current .= "''";
                        $i++;

                        continue;
                    }
                    $inQuote = false;
                }
                $current .= $char;

                continue;
            }

            if ($char === "'") {
                $inQuote = true;
                $current .= $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
                if ($depth === 1) {
                    $current = '';

                    continue;
                }
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $current;

                    continue;
                }
            }

            if ($depth >= 1) {
                $current .= $char;
            }
        }

        return $tuples;
    }

    /**
     * Satu tuple ("'1','PRIME CUT'" atau "'1',NULL,'X'") jadi daftar nilai.
     *
     * NULL SQL yang TANPA kutip harus dibedakan dari string `'NULL'` yang
     * BERKUTIP -- ditandai sentinel dulu, sebelum kutipnya sendiri dilepas.
     *
     * @return array<int, string|null>
     */
    private static function parseTuple(string $tuple): array
    {
        $sentinel = "\x01NULL\x01";
        $tuple = (string) preg_replace('/(?<=^|,)\s*NULL\s*(?=,|$)/', $sentinel, $tuple);

        $fields = str_getcsv($tuple, ',', "'", '\\');

        return array_map(
            fn ($field) => $field === $sentinel ? null : $field,
            $fields
        );
    }
}
