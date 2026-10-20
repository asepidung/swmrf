<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stok daging legacy (`stock`, satu baris per barcode yang sedang di gudang) -> `beef_stocks`.
 *
 * Bukan modul stok awal: hanya memindahkan DATA barang yang ada di gudang menurut legacy (keputusan Ayah, 20 Oktober 2026).
 * Dry-run bawaan (laporan + contoh berpasangan lama -> baru); `--apply` menulis. Satu penolakan membatalkan seluruh penulisan.
 * Aman diulang: barcode yang sudah ada dengan isi sama dilewati; isi berbeda = DITOLAK.
 *
 * Pemasangan:
 *  - kunci = barcode (unik di kedua tabel; dibawa apa adanya, 18-20 digit);
 *  - product_id = idbarang (produk diimpor ber-ID legacy);
 *  - gudang + grade lewat `LegacyGradeMap` (grade legacy menggabungkan gudang dan kondisi);
 *  - batch, tanggal kedaluwarsa, dan asal dari tabel sumber barcode yang sama (labelboning/boning, detailhasil/repack, grbeefdetail/grbeef);
 *  - origin: digit awal barcode legacy diterjemahkan; 7 (stock-in manual) -> -FOUND (keputusan Ayah, 20 Oktober 2026).
 * `price` legacy kosong semua; nilai awal (HPP) diberikan sesudahnya oleh `BarcodeCosts::valueOpeningStock()`.
 */
class ImportLegacyStock extends Command
{
    protected $signature = 'legacy:import-stock
                            {--source= : Berkas snapshot SQL stok (legacy/tarik/tarik.sh stok)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru per kode asal}';

    protected $description = 'Pindahkan stok daging legacy (per barcode) ke beef_stocks (dry-run bawaan)';

    /** Digit awal barcode legacy -> nama asal di aplikasi baru. 7 = stock-in manual -> -FOUND (keputusan Ayah). */
    public const ORIGIN_NAMES = [
        '1' => 'BONING',
        '2' => 'TRD-LC',
        '3' => 'R-STCK',
        '4' => 'RLB-TL',
        '5' => 'R-IMPT',
        '6' => 'R-RTRN',
        '7' => '-FOUND',
    ];

    public const NOTE = 'Stok awal (pindah dari legacy)';

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path || ! is_file($path)) {
            $this->error('--source wajib diisi dengan berkas snapshot SQL yang ada.');

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');

        $table = fn (string $name) => LegacySqlDump::parseTable($sql, $name);
        $stock = $table('stock');
        $labels = collect($table('labelboning'))->keyBy('kdbarcode');
        $boning = collect($table('boning'))->keyBy('idboning');
        $results = collect($table('detailhasil'))->keyBy('kdbarcode');
        $repack = collect($table('repack'))->keyBy('idrepack');
        $grItems = collect($table('grbeefdetail'))->keyBy('kdbarcode');
        $grs = collect($table('grbeef'))->keyBy('idgr');
        $stockIn = collect($table('stockin'))->keyBy('kdbarcode');

        $problems = LegacyGradeMap::appSideProblems();

        if ($problems !== []) {
            $this->error('Terjemahan grade belum bisa dipakai: '.implode('; ', $problems));

            return self::FAILURE;
        }

        $created = $same = 0;
        $refused = [];
        $conflicts = [];
        $perOrigin = [];
        $perStore = [];
        $examples = [];
        $totalKg = 0.0;
        $notes = ['pcs_blank' => 0, 'no_source' => 0, 'no_ph' => 0];

        $run = function () use (
            $stock, $labels, $boning, $results, $repack, $grItems, $grs, $stockIn, $apply,
            &$created, &$same, &$refused, &$conflicts, &$perOrigin, &$perStore, &$examples, &$totalKg, &$notes
        ): void {
            foreach ($stock as $row) {
                $barcode = trim((string) $row['kdbarcode']);
                $legacyGrade = (int) $row['idgrade'];
                $productId = (int) $row['idbarang'];
                $originDigit = (string) $row['origin'];

                if ($barcode === '') {
                    $conflicts[] = "id {$row['id']}: barcode kosong, dilewati.";

                    continue;
                }

                if (! LegacyGradeMap::knows($legacyGrade)) {
                    $refused[] = "{$barcode}: grade legacy {$legacyGrade} di luar tabel terjemahan -- DITOLAK.";

                    continue;
                }

                $product = Product::query()->find($productId);

                if (! $product) {
                    $conflicts[] = "{$barcode}: produk idbarang={$productId} tidak ada/tidak diimpor, dilewati.";

                    continue;
                }

                if (! isset(self::ORIGIN_NAMES[$originDigit])) {
                    $refused[] = "{$barcode}: kode asal legacy \"{$originDigit}\" tidak dikenal -- DITOLAK.";

                    continue;
                }

                [$batch, $exp, $source] = $this->sourceOf($barcode, $originDigit, $labels, $boning, $results, $repack, $grItems, $grs, $stockIn);
                $place = LegacyGradeMap::resolve($legacyGrade);

                $pcsRaw = trim((string) ($row['pcs'] ?? ''));
                $phRaw = trim((string) ($row['ph'] ?? ''));

                $new = [
                    'barcode' => $barcode,
                    'batch_no' => $batch,
                    'product_id' => $productId,
                    'warehouse_id' => $place['warehouse_id'],
                    'grade_id' => $place['grade_id'],
                    'weight' => round((float) $row['qty'], 2),
                    'qty_pcs' => $pcsRaw === '' ? 0 : (int) $pcsRaw,
                    'ph_level' => $phRaw === '' ? null : round((float) $phRaw, 1),
                    'pack_date' => $row['pod'],
                    'exp_date' => $exp,
                    'origin' => self::ORIGIN_NAMES[$originDigit],
                    'status' => 'IN_STOCK',
                    'note' => self::NOTE,
                ];

                $notes['pcs_blank'] += $pcsRaw === '' ? 1 : 0;
                $notes['no_ph'] += $phRaw === '' ? 1 : 0;
                $notes['no_source'] += $source === null ? 1 : 0;

                $perOrigin[$new['origin']] = ($perOrigin[$new['origin']] ?? 0) + 1;
                $storeKey = "{$legacyGrade} -> gudang ".LegacyGradeMap::MAP[$legacyGrade][1].' + '.LegacyGradeMap::MAP[$legacyGrade][2];
                $perStore[$storeKey] = ($perStore[$storeKey] ?? 0) + 1;
                $totalKg += $new['weight'];

                if (count($examples[$originDigit] ?? []) < (int) $this->option('examples')) {
                    $examples[$originDigit][] = [$row, $new, $product, $source];
                }

                $existing = DB::table('beef_stocks')->where('barcode', $barcode)->first();

                if ($existing) {
                    $same += $this->sameAs((array) $existing, $new) ? 1 : 0;

                    if (! $this->sameAs((array) $existing, $new)) {
                        $refused[] = "{$barcode}: sudah ada di beef_stocks dengan isi berbeda -- DITOLAK.";
                    }

                    continue;
                }

                $created++;

                if ($apply) {
                    DB::table('beef_stocks')->insert($new + ['created_at' => now(), 'updated_at' => now()]);
                }
            }

            if ($apply && $refused !== []) {
                throw new \RuntimeException('refused');
            }
        };

        try {
            $apply ? DB::transaction($run) : $run();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'refused') {
                throw $e;
            }
        }

        $this->report($apply, $refused, $conflicts, $created, $same, $perOrigin, $perStore, $totalKg, $notes, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Batch, tanggal kedaluwarsa, dan asal dokumen sebuah barcode dari tabel sumbernya. Urutan menurut kode asal supaya
     * barcode yang tercatat di dua tabel diambil dari tabel yang sesuai asalnya.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} batch, exp, deskripsi sumber (null = tanpa sumber)
     */
    private function sourceOf(string $barcode, string $origin, $labels, $boning, $results, $repack, $grItems, $grs, $stockIn): array
    {
        $order = match ($origin) {
            '1' => ['label', 'repack', 'gr'],
            '2' => ['gr', 'repack', 'label'],
            default => ['repack', 'gr', 'label'],
        };

        foreach ($order as $kind) {
            if ($kind === 'label' && isset($labels[$barcode])) {
                $label = $labels[$barcode];
                $batch = trim((string) ($boning[$label['idboning']]['batchboning'] ?? '')) ?: null;

                return [$batch, $this->date($label['exp'] ?? null), 'label boning '.($batch ?? $label['idboning'])];
            }

            if ($kind === 'repack' && isset($results[$barcode])) {
                $result = $results[$barcode];
                $batch = trim((string) ($repack[$result['idrepack']]['norepack'] ?? '')) ?: null;

                return [$batch, $this->date($result['exp'] ?? null), 'hasil repack '.($batch ?? $result['idrepack'])];
            }

            if ($kind === 'gr' && isset($grItems[$barcode])) {
                $item = $grItems[$barcode];
                $batch = trim((string) ($grs[$item['idgr']]['grnumber'] ?? '')) ?: null;

                return [$batch, null, 'GR beef '.($batch ?? $item['idgr'])];
            }
        }

        if (isset($stockIn[$barcode])) {
            return [null, null, 'stock-in manual'];
        }

        return [null, null, null];
    }

    private function date(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || str_starts_with($value, '0000')) ? null : $value;
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $new */
    private function sameAs(array $existing, array $new): bool
    {
        foreach ($new as $column => $value) {
            $stored = $existing[$column] ?? null;

            if (is_numeric($value) && is_numeric($stored)) {
                if ((float) $value !== (float) $stored) {
                    return false;
                }

                continue;
            }

            if ((string) $value !== (string) $stored) {
                return false;
            }
        }

        return true;
    }

    private function report(bool $apply, array $refused, array $conflicts, int $created, int $same, array $perOrigin, array $perStore, float $totalKg, array $notes, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("beef_stocks -- dibuat: {$created}, sama (dilewati): {$same}, konflik: ".count($conflicts).', DITOLAK: '.count($refused).', total berat: '.number_format($totalKg, 2, ',', '.').' kg');

        $this->line('Per asal: '.collect($perOrigin)->map(fn ($n, $o) => "{$o}={$n}")->implode(', '));
        $this->line('Per gudang+grade: '.collect($perStore)->map(fn ($n, $k) => "[{$k}]={$n}")->implode(', '));

        foreach ($examples as $digit => $pairs) {
            $this->line('');
            $this->line("Contoh asal legacy {$digit} -> ".self::ORIGIN_NAMES[$digit].':');

            foreach ($pairs as [$old, $new, $product, $source]) {
                $this->line("  LAMA  stock: kdbarcode={$old['kdbarcode']} idbarang={$old['idbarang']} idgrade={$old['idgrade']} qty={$old['qty']} pcs=".($old['pcs'] === '' ? '(kosong)' : $old['pcs']).' ph='.($old['ph'] ?? '(kosong)')." pod={$old['pod']} origin={$old['origin']}");
                $this->line("  BARU  beef_stocks: barcode={$new['barcode']} product={$product->code} {$product->name} gudang_id={$new['warehouse_id']} grade_id={$new['grade_id']} weight={$new['weight']} qty_pcs={$new['qty_pcs']} ph=".($new['ph_level'] ?? '(kosong)')." pack_date={$new['pack_date']} exp=".($new['exp_date'] ?? '(kosong)')." batch=".($new['batch_no'] ?? '(kosong)')." origin={$new['origin']} sumber=".($source ?? '(tanpa sumber)'));
            }
        }

        $this->line('');
        $this->line('Catatan:');
        $this->line("  - qty_pcs kosong di legacy -> 0: {$notes['pcs_blank']} barcode; pH kosong: {$notes['no_ph']}; tanpa tabel sumber (batch kosong): {$notes['no_source']}.");
        $this->line('  - Tanggal kedaluwarsa hanya dibawa bila ada di tabel sumber; di legacy hampir seluruhnya kosong.');
        $this->line('  - Tidak ada catatan pergerakan stok dibuat (stok awal sebelum buku besar). Nilai awal (HPP): jalankan BarcodeCosts::valueOpeningStock() sesudahnya.');

        foreach (array_slice($conflicts, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
        foreach (array_slice($refused, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
