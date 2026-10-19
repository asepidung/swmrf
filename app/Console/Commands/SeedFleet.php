<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Support\LooseName;
use Illuminate\Console\Command;

/**
 * Data awal SUPIR dan ARMADA dari Owner (19 Oktober 2026). Tidak ada padanannya di legacy, jadi datanya ditulis di sini
 * supaya import final ke VPS (31 Okt - 1 Nov) memberi hasil yang sama persis tanpa mengetik ulang.
 *
 * Dry-run bawaan (hanya laporan); `--apply` menulis. Aman diulang: yang sudah ada dilewati. Nama/plat dibandingkan
 * longgar (huruf besar, spasi, dan tanda baca diabaikan: "F8485HD" = "F 8485 HD"). Plat yang sudah dipakai kendaraan
 * dengan JENIS berbeda dilaporkan sebagai konflik dan TIDAK ditimpa.
 */
class SeedFleet extends Command
{
    protected $signature = 'master:seed-fleet {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Isi data awal supir dan armada dari Owner (dry-run bawaan, aman diulang)';

    /** @var array<int, string> */
    public const DRIVERS = ['ACEP NURFADILLAH', 'RESKHA PERMANA'];

    /** @var array<int, array{0: string, 1: string}> jenis kendaraan, plat nomor */
    public const VEHICLES = [
        ['CANTER', 'F 8485 HD'],
        ['TRAGA', 'F 9385 FI'],
        ['L-300', 'F 8489 HV'],
        ['DUTRO', 'F 9201 F'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $created = $same = $conflicts = [];

        foreach (self::DRIVERS as $name) {
            $existing = Driver::all(['id', 'name'])->first(fn (Driver $d) => LooseName::key($d->name) === LooseName::key($name));

            if ($existing) {
                $same[] = "supir {$name}: sudah ada (id {$existing->id}).";

                continue;
            }

            $created[] = "supir {$name}";

            if ($apply) {
                Driver::create(['name' => $name, 'is_active' => true]);
            }
        }

        foreach (self::VEHICLES as [$type, $plate]) {
            $existing = Vehicle::all(['id', 'vehicle_type', 'police_number'])
                ->first(fn (Vehicle $v) => LooseName::key($v->police_number) === LooseName::key($plate));

            if ($existing) {
                if (LooseName::key($existing->vehicle_type) === LooseName::key($type)) {
                    $same[] = "armada {$type} {$plate}: sudah ada (id {$existing->id}).";
                } else {
                    $conflicts[] = "armada {$type} {$plate}: plat sudah dipakai \"{$existing->vehicle_type}\" (id {$existing->id}) -- tidak ditimpa.";
                }

                continue;
            }

            $created[] = "armada {$type} {$plate}";

            if ($apply) {
                Vehicle::create(['vehicle_type' => $type, 'police_number' => $plate, 'is_active' => true]);
            }
        }

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line('dibuat: '.count($created).', sudah ada (dilewati): '.count($same).', konflik: '.count($conflicts));

        foreach ($created as $item) {
            $this->line("  + {$item}");
        }
        foreach ($same as $item) {
            $this->line("  = {$item}");
        }
        foreach ($conflicts as $item) {
            $this->line("  ! {$item}");
        }

        return $conflicts === [] ? self::SUCCESS : self::FAILURE;
    }
}
