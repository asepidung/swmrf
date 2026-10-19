<?php

namespace App\Console\Commands;

use App\Models\CustomerGroup;
use App\Support\LooseName;
use Illuminate\Console\Command;

/**
 * Data awal GRUP PELANGGAN TAMBAHAN dari Owner (19 Oktober 2026). Di luar grup legacy (`legacy:import-master --only=grup`), jalankan SESUDAHNYA supaya ID grup legacy tidak tergeser. Dry-run bawaan; `--apply` menulis; aman diulang (nama dibandingkan longgar).
 */
class SeedGroups extends Command
{
    protected $signature = 'master:seed-groups {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Isi data awal grup pelanggan tambahan dari Owner (dry-run bawaan, aman diulang)';

    /** @var array<int, string> */
    public const GROUPS = ['KARYAWAN', 'WARGA +62', 'GRATISAN'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $created = $same = [];

        foreach (self::GROUPS as $name) {
            $existing = CustomerGroup::all(['id', 'name'])->first(fn (CustomerGroup $s) => LooseName::key($s->name) === LooseName::key($name));

            if ($existing) {
                $same[] = "{$name}: sudah ada (id {$existing->id}).";

                continue;
            }

            $created[] = $name;

            if ($apply) {
                CustomerGroup::create(['name' => $name]);
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
