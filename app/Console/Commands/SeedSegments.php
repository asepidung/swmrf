<?php

namespace App\Console\Commands;

use App\Models\CustomerSegment;
use App\Support\LooseName;
use Illuminate\Console\Command;

/**
 * Data awal SEGMEN PELANGGAN dari Owner (19 Oktober 2026). Tidak ada padanannya di legacy (tabel `segment` legacy adalah
 * rekening bank penagihan, bukan kelompok pelanggan). Dry-run bawaan; `--apply` menulis; aman diulang (nama dibandingkan longgar).
 */
class SeedSegments extends Command
{
    protected $signature = 'master:seed-segments {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Isi data awal segmen pelanggan dari Owner (dry-run bawaan, aman diulang)';

    /** @var array<int, string> */
    public const SEGMENTS = ['MODERN MARKET', 'HOREKA', 'DISTRIBUTOR', 'RETAIL', 'INDUSTRI'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $created = $same = [];

        foreach (self::SEGMENTS as $name) {
            $existing = CustomerSegment::all(['id', 'name'])->first(fn (CustomerSegment $s) => LooseName::key($s->name) === LooseName::key($name));

            if ($existing) {
                $same[] = "{$name}: sudah ada (id {$existing->id}).";

                continue;
            }

            $created[] = $name;

            if ($apply) {
                CustomerSegment::create(['name' => $name]);
            }
        }

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line('dibuat: '.count($created).', sudah ada (dilewati): '.count($same));

        foreach ($created as $item) {
            $this->line("  + {$item}");
        }
        foreach ($same as $item) {
            $this->line("  = {$item}");
        }

        return self::SUCCESS;
    }
}
