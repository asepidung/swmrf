<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Saldo stok MATERIAL legacy -> `material_stocks` (satu baris per material: qty). Keputusan Ayah, 21 Oktober 2026; rumus DIKOREKSI 23 Oktober 2026.
 *
 * Rumus saldo = persis yang dipakai layar stok legacy (`stockraw/index.php`): jumlah penerimaan (`grrawdetail` dari GR yang tidak dihapus)
 * dikurangi pengeluaran (`raw_stock_out_detail` dari dokumen yang tidak dihapus). Satuannya satuan BELI (Box, Pcs, Ikat), sama dengan aplikasi baru.
 * Versi pertama salah memakai tabel `stockraw` (buku lain, angkanya dalam pcs: DRY LOG 50.435 padahal layar legacy 17 Box; 88 dari 227 material meleset),
 * dikurangi `raw_usage`. Layar legacy tidak memakai keduanya.
 *
 * Butuh DUA snapshot: `--source` (tarik.sh stokmaterial: pengeluaran, nama material) dan `--grs` (tarik.sh belanja: penerimaan GR).
 * Saldo negatif (selisih kecil) dibawa sebagai 0 dan dilaporkan. Material di luar material impor dilaporkan sebagai teks.
 *
 * Dry-run bawaan; `--apply` menulis; aman diulang (material yang sudah punya baris stok dilewati, TIDAK ditimpa: stok berubah oleh operasional).
 * Ditulis tanpa event model dan tanpa catatan pergerakan: stok awal sebelum buku besar (sama dengan stok daging).
 */
class ImportLegacyMaterialStock extends Command
{
    protected $signature = 'legacy:import-material-stock
                            {--source= : Berkas snapshot SQL pengeluaran dan nama material (legacy/tarik/tarik.sh stokmaterial)}
                            {--grs= : Berkas snapshot SQL penerimaan GR material (legacy/tarik/tarik.sh belanja)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Pindahkan saldo stok material legacy ke material_stocks (dry-run bawaan)';

    public function handle(): int
    {
        $path = $this->option('source');
        $grsPath = $this->option('grs');

        if (! $path || ! is_file($path) || ! $grsPath || ! is_file($grsPath)) {
            $this->error('--source (snapshot stokmaterial) dan --grs (snapshot belanja) wajib diisi dengan berkas yang ada.');

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $grsSql = file_get_contents($grsPath);
        $apply = (bool) $this->option('apply');
        $table = fn (string $name) => collect(LegacySqlDump::parseTable($sql, $name));
        $grTable = fn (string $name) => collect(LegacySqlDump::parseTable($grsSql, $name));

        $liveGrs = $grTable('grraw')->filter(fn ($r) => (int) ($r['is_deleted'] ?? 0) === 0)->pluck('idgr')->flip();
        $in = $grTable('grrawdetail')->filter(fn ($d) => isset($liveGrs[$d['idgr']]) && (int) ($d['is_deleted'] ?? 0) === 0)
            ->groupBy('idrawmate')->map(fn ($g) => $g->sum(fn ($r) => (float) $r['qty']));
        $liveOuts = $table('raw_stock_out')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->pluck('idstockout')->flip();
        $out = $table('raw_stock_out_detail')->filter(fn ($d) => isset($liveOuts[$d['idstockout']]))
            ->groupBy('idrawmate')->map(fn ($g) => $g->sum(fn ($r) => (float) $r['qty']));
        $names = $table('rawmate')->pluck('nmrawmate', 'idrawmate');

        $balances = $in->keys()->merge($out->keys())->unique()
            ->mapWithKeys(fn ($id) => [(int) $id => round(($in[$id] ?? 0) - ($out[$id] ?? 0), 2)]);

        $materialIds = Material::query()->pluck('id')->flip();
        $existing = DB::table('material_stocks')->pluck('qty', 'material_id');

        $created = $same = 0;
        $totalQty = 0.0;
        $clamped = $outOfScope = $zero = $rows = [];

        $run = function () use ($balances, $materialIds, $existing, $names, $apply, &$created, &$same, &$totalQty, &$clamped, &$outOfScope, &$zero, &$rows): void {
            foreach ($balances as $id => $balance) {
                if (! isset($materialIds[$id])) {
                    if (abs($balance) > 0.0001) {
                        $outOfScope[] = ($names[$id] ?? "bahan {$id}").' '.$balance;
                    }

                    continue;
                }

                if ($balance < 0) {
                    $clamped[] = ($names[$id] ?? "bahan {$id}").' '.$balance;
                    $balance = 0.0;
                }

                if ($balance == 0) {
                    $zero[] = $id;

                    continue;
                }

                if (isset($existing[$id])) {
                    $same++;

                    continue;
                }

                $created++;
                $totalQty += $balance;
                $rows[] = [$id, $balance];

                if ($apply) {
                    DB::table('material_stocks')->insert(['material_id' => $id, 'qty' => $balance, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        };

        $apply ? DB::transaction($run) : $run();

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("material_stocks -- dibuat: {$created}, sudah ada (dilewati): {$same}, saldo nol (tanpa baris): ".count($zero).', total qty: '.number_format($totalQty, 2, ',', '.'));
        $this->line('Terbesar: '.collect($rows)->sortByDesc(fn ($r) => $r[1])->take(6)->map(fn ($r) => ($names[$r[0]] ?? $r[0]).' '.$r[1])->implode(' | '));
        $this->line('Saldo negatif di legacy dibawa sebagai 0: '.($clamped === [] ? '-' : implode('; ', $clamped)));
        $this->line('Material dengan saldo di luar material impor ('.count($outOfScope).', tidak dibawa): '.($outOfScope === [] ? '-' : implode('; ', $outOfScope)));
        $this->line('Catatan: tanpa catatan pergerakan stok (stok awal sebelum buku besar); baris stok yang sudah ada tidak ditimpa.');

        return self::SUCCESS;
    }
}
