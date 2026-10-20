<?php

namespace App\Console\Commands;

use App\Support\MasterSnapshot;
use Illuminate\Console\Command;

/**
 * Menulis salinan master data basis data INI ke berkas JSON (untuk disalin ke basis data lain lewat
 * `master:import-snapshot`). Hanya membaca basis data. Berkas berisi data pelanggan: jangan masuk git.
 */
class ExportMasterSnapshot extends Command
{
    protected $signature = 'master:export {--path= : Berkas tujuan (bawaan storage/app/master-snapshot-<waktu>.json)}';

    protected $description = 'Tulis salinan master data (id dipertahankan) ke berkas JSON';

    public function handle(): int
    {
        $snapshot = MasterSnapshot::export();
        $path = $this->option('path') ?: storage_path('app/master-snapshot-'.now()->format('Ymd-His').'.json');

        file_put_contents($path, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Snapshot ditulis: {$path}");

        foreach ($snapshot['meta']['counts'] as $table => $count) {
            $this->line(sprintf('  %-22s %d', $table, $count));
        }

        $this->line('checksum: '.$snapshot['meta']['checksum']);

        return self::SUCCESS;
    }
}
