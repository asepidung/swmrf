<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Support\LooseName;
use Illuminate\Console\Command;

/**
 * Menonaktifkan item material yang BUKAN item Request, menurut file pemilahan Hafizh
 * (`RAW MATERIAL - dipilah.xlsx`, jalur OPERASIONAL/ASET/HAPUS), keputusan Owner 26 Oktober 2026:
 * batas Request vs Expenses = isi master Material -- hanya item REQUEST yang aktif; barang operasional
 * (ATK, alat sablon, perawatan armada, aset) masuk kategori Expense. Dinonaktifkan, BUKAN dihapus
 * (PO/riwayat lama menunjuknya). Nama dicocokkan longgar. Dry-run bawaan; `--apply` menulis; aman diulang.
 * Hasilnya ikut ke lingkungan lain lewat snapshot master (`is_active` ikut tabel `materials`).
 */
class DeactivateNonRequestMaterials extends Command
{
    protected $signature = 'materials:deactivate-non-request {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Nonaktifkan item material non-Request (operasional/aset) menurut file pemilahan (dry-run bawaan, aman diulang)';

    /** @var array<int, string> */
    public const NAMES = [
        // OPERASIONAL (56)
        'ALAT KELISTRIKAN',
        'ALAT POLES MOBIL',
        'BESI SIKU',
        'BINDEX',
        'BROSUR',
        'CARTRIDGE PRINTER',
        'Cat Epoxy',
        'CETAKAN',
        'COATING MOBIL',
        'DOUBLE TIP KERTAS',
        'Epoxy Ruang Perutan',
        'Epoxy Ruang Potong',
        'Epoxy Ruang Produksi',
        'FOOT SWITCH HNYOUNG HY-103N',
        'HOSTING 1 TAHUN',
        'JOSS',
        'KANEBO',
        'KARTU',
        'KAWAT LAS',
        'KERTAS A4',
        'KERTAS DO',
        'KERTAS DO 5 PLAY',
        'KERTAS LEGAL',
        'KLIP MASTER',
        'KOMPON MOBIL',
        'LAMPU FLYCATCHER',
        'LAMPU LED',
        'LEM KERTAS',
        'M4 SABLON',
        'MATERAI 10000',
        'PADMAN',
        'PAJAK L300',
        'PAKET DATA GPS',
        'PAPER CLIP',
        'Pemasangan Epoxy',
        'PEMBOLONG KERTAS',
        'PENUNJANG INTERNET',
        'PLASTIK BINDEX',
        'PLAT BESI',
        'PULPEN',
        'RACUN LALAT',
        'RAM',
        'RENTAL CDD LONG',
        'SCREEN SABLON',
        'SEMIR BAN MOBIL',
        'SPIDOL NON PERMANENT',
        'SPIDOL PERMANENT',
        'STAPLER',
        'STRONG CLEANER MOBIL',
        'TINTA PRINTER BIRU',
        'TINTA PRINTER HITAM',
        'TINTA PRINTER HP deskjet 2300',
        'TINTA PRINTER MAGENTA',
        'TINTA PRINTER YELLOW',
        'TINTA SABLON',
        'UJI LAB',
        // ASET (9)
        'BAK PEREBUSAN BAKSO',
        'BOX CONTAINER 2002',
        'BOX CONTAINER 3003',
        'KAMERA CCTV',
        'LAPTOP',
        'MIKROTIK',
        'PANEL',
        'PRINTER',
        'ROUTER',
        // HAPUS (10)
        'LAIN-LAIN',
        'MOBIL CANTER',
        'MOBIL DUTRO (DINAS)',
        'MOBIL FORTUNER',
        'MOBIL INNOVA',
        'MOBIL L300',
        'MOBIL PANTHER',
        'Sapi',
        'XKARTON',
        'XPLASTIK',

    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $wanted = collect(self::NAMES)->mapWithKeys(fn (string $name) => [LooseName::key($name) => $name]);
        $changed = $already = [];
        $matchedKeys = [];

        foreach (Material::all(['id', 'name', 'is_active']) as $material) {
            $key = LooseName::key($material->name);

            if (! $wanted->has($key)) {
                continue;
            }

            $matchedKeys[$key] = true;

            if (! $material->is_active) {
                $already[] = $material->name;

                continue;
            }

            $changed[] = $material->name;

            if ($apply) {
                $material->update(['is_active' => false]);
            }
        }

        $missing = $wanted->reject(fn ($name, $key) => isset($matchedKeys[$key]))->values();

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line('dinonaktifkan: '.count($changed).', sudah nonaktif: '.count($already).', tidak ada di master: '.$missing->count());

        foreach ($changed as $name) {
            $this->line("  - {$name}");
        }

        if ($missing->isNotEmpty()) {
            $this->line('Tidak ada di master (tidak dibuat): '.$missing->implode(', '));
        }

        return self::SUCCESS;
    }
}
