<?php

namespace App\Console\Commands;

use App\Support\MasterSnapshot;
use Illuminate\Console\Command;

/**
 * Memasukkan snapshot master data (dari `master:export`) ke basis data INI, dengan id yang sama.
 * Dry-run bawaan: semuanya dijalankan lalu dibatalkan, jadi tabrakan terlihat tanpa menulis apa pun.
 * Satu penolakan membatalkan seluruh penulisan. Aman diulang: baris yang sama persis dilewati.
 */
class ImportMasterSnapshot extends Command
{
    protected $signature = 'master:import-snapshot {file : Berkas snapshot JSON} {--apply : Benar-benar menulis (default hanya dry-run)}';

    protected $description = 'Salin master data dari snapshot (id dipertahankan; dry-run bawaan)';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $snapshot = json_decode((string) file_get_contents($file), true);

        if (! is_array($snapshot)) {
            $this->error('Berkas bukan JSON yang sah.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $result = MasterSnapshot::import($snapshot, $apply);

        $this->line($apply && ! $result['refused'] ? 'DITERAPKAN (--apply)' : ($result['refused'] ? 'DITOLAK -- tidak ada yang ditulis.' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.'));

        foreach ($result['report'] as $table => $r) {
            $this->line(sprintf('%-22s dibuat: %d, sama (dilewati): %d, DITOLAK: %d', $table, $r['created'], $r['same'], count($r['refused'])));

            foreach (array_slice($r['refused'], 0, 10) as $line) {
                $this->line("  ! {$line}");
            }

            if (count($r['refused']) > 10) {
                $this->line('  ! ... dan '.(count($r['refused']) - 10).' lainnya');
            }
        }

        return $result['refused'] ? self::FAILURE : self::SUCCESS;
    }
}
