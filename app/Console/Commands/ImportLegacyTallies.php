<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * TALLY legacy (`tally` + `tallydetail`, satu baris per karton yang discan ke SO) -> `tallies` + `tally_items`, SELURUH riwayat yang tidak dihapus
 * (keputusan Ayah, 21 Oktober 2026). Langkah 2 dari SO -> Tally -> DO -> Invoice; butuh `legacy:import-sales-orders` lebih dulu (penghubung lewat `legacy_import_map`).
 * Dry-run bawaan; `--apply` menulis; aman diulang (nomor tally yang sudah ada dilewati); satu penolakan membatalkan semuanya. Ditulis lewat DB::table (tanpa event model: tidak ada gerak stok).
 *
 * Pemasangan:
 *  - tallies: sales_order_id = SO hasil impor (peta `sales_order`; cadangan: sonumber); tally_number = notally (nomor kembar diberi akhiran -2, -3 menurut urutan idtally); seal_number = sealnumb;
 *    status: DO -> do (Tally::STATUS_DELIVERED), Rejected -> locked, kosong -> processing; created_by = `--fallback-user` (legacy tidak mencatat pembuat tally); created_at = creatime.
 *    Satu SO hanya boleh punya satu tally di aplikasi baru: tally KOSONG (tanpa karton) pada SO yang punya tally lain dilewati; dua tally berisi pada satu SO = DITOLAK;
 *  - tally_items: barcode apa adanya; product_id = idbarang; gudang + grade lewat `LegacyGradeMap`, grade KOSONG (data awal 2023) -> J01 (JGL + CHILL); weight; qty_pcs = pcs; ph_level (kosong tetap kosong);
 *    pack_date = pod; exp_date = exp; origin: digit `origin` (kosong -> digit awal barcode) diterjemahkan seperti stok (`ImportLegacyStock::ORIGIN_NAMES`).
 *  - barcode KEMBAR di dua tally (karton yang discan ulang): aplikasi baru mensyaratkan barcode unik, jadi yang lebih BARU diberi akhiran "-2" dan barcode aslinya disimpan di `original_barcode`;
 *  - pack_date legacy kosong/0000-00-00 -> tanggal scan (creatime);
 *  - batch_no tidak dibawa (legacy tidak mencatatnya pada tally).
 */
class ImportLegacyTallies extends Command
{
    protected $signature = 'legacy:import-tallies
                            {--source= : Berkas snapshot SQL tally (legacy/tarik/tarik.sh tally)}
                            {--fallback-user= : Username pembuat tally (legacy tidak mencatat pembuat)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat Tally legacy ke tallies + tally_items (dry-run bawaan)';

    public const STATUS_MAP = ['DO' => 'do', 'Rejected' => 'locked', '' => 'processing'];

    public const EMPTY_GRADE = 1;

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path || ! is_file($path)) {
            $this->error('--source wajib diisi dengan berkas snapshot SQL tally yang ada.');

            return self::FAILURE;
        }

        $fallback = $this->option('fallback-user') ? User::withoutGlobalScopes()->where('username', $this->option('fallback-user'))->first() : null;

        if (! $fallback) {
            $this->error('--fallback-user wajib diisi dengan username yang ada.');

            return self::FAILURE;
        }

        $problems = LegacyGradeMap::appSideProblems();

        if ($problems !== []) {
            $this->error('Terjemahan grade belum bisa dipakai: '.implode('; ', $problems));

            return self::FAILURE;
        }

        ini_set('memory_limit', '2048M');

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');
        $tallies = collect(LegacySqlDump::parseTable($sql, 'tally'))->sortBy(fn ($r) => (int) $r['idtally'])->values();
        $details = collect(LegacySqlDump::parseTable($sql, 'tallydetail'))->groupBy('idtally');
        unset($sql);

        $soMap = DB::table('legacy_import_map')->where('entity', 'sales_order')->pluck('new_id', 'legacy_id');
        $soByNumber = DB::table('sales_orders')->pluck('id', 'so_number');
        $existing = DB::table('tallies')->pluck('id', 'tally_number');
        $tallyOfSo = DB::table('tallies')->pluck('id', 'sales_order_id');
        $taken = DB::table('tally_items')->pluck('id', 'barcode')->all();
        $productIds = Product::query()->pluck('id')->flip();

        $stats = ['created' => 0, 'skipped' => 0, 'items' => 0, 'kg' => 0.0, 'suffixNumber' => 0, 'suffixBarcode' => 0, 'emptyGrade' => 0, 'blankPh' => 0, 'emptyDup' => 0, 'noSo' => 0, 'noProduct' => 0, 'zeroDate' => 0, 'emptyTallies' => 0];
        $perStatus = $perOrigin = $refused = $conflicts = $examples = [];
        $numberSeen = [];
        $filledSo = $tallies->filter(fn ($r) => $details->has($r['idtally']))->countBy('idso');

        $run = function () use ($tallies, $details, $filledSo, $soMap, $soByNumber, &$existing, &$tallyOfSo, &$taken, $productIds, $fallback, $apply, &$stats, &$perStatus, &$perOrigin, &$refused, &$conflicts, &$examples, &$numberSeen): void {
            foreach ($tallies as $row) {
                $legacyId = (int) $row['idtally'];
                $base = trim((string) $row['notally']);
                $numberSeen[$base] = ($numberSeen[$base] ?? 0) + 1;
                $number = $numberSeen[$base] > 1 ? $base.'-'.$numberSeen[$base] : $base;
                $stats['suffixNumber'] += $numberSeen[$base] > 1 ? 1 : 0;

                if (isset($existing[$number])) {
                    $stats['skipped']++;
                    $this->remember('tally', $legacyId, (int) $existing[$number], $apply);

                    continue;
                }

                $soId = $soMap[(int) $row['idso']] ?? $soByNumber[trim((string) $row['sonumber'])] ?? null;

                if (! $soId) {
                    $stats['noSo']++;
                    $conflicts[] = "{$number}: Sales Order idso={$row['idso']} tidak ada di aplikasi baru, tally dilewati.";

                    continue;
                }

                $lines = $details->get($row['idtally'], collect());

                if ($lines->isEmpty() && ($filledSo[$row['idso']] ?? 0) > 0) {
                    $stats['emptyDup']++;
                    $conflicts[] = "{$number}: tally kosong, SO {$row['sonumber']} punya tally lain yang berisi, dilewati.";

                    continue;
                }

                if (isset($tallyOfSo[$soId])) {
                    if ($lines->isEmpty()) {
                        $stats['emptyDup']++;
                        $conflicts[] = "{$number}: SO {$row['sonumber']} sudah punya tally dan tally ini kosong, dilewati.";
                    } else {
                        $refused[] = "{$number}: SO {$row['sonumber']} sudah punya tally lain dan tally ini berisi {$lines->count()} karton -- DITOLAK.";
                    }

                    continue;
                }

                $legacyStat = trim((string) $row['stat']);
                $status = self::STATUS_MAP[$legacyStat] ?? null;

                if ($status === null) {
                    $refused[] = "{$number}: status legacy \"{$legacyStat}\" tidak dikenal -- DITOLAK.";

                    continue;
                }

                $items = [];

                foreach ($lines as $line) {
                    $barcode = trim((string) $line['barcode']);
                    $productId = (int) $line['idbarang'];

                    if (! isset($productIds[$productId])) {
                        $stats['noProduct']++;
                        $conflicts[] = "{$number}: karton {$barcode} idbarang={$productId} bukan produk impor, dilewati.";

                        continue;
                    }

                    $gradeRaw = trim((string) ($line['idgrade'] ?? ''));

                    if ($gradeRaw === '') {
                        $stats['emptyGrade']++;
                        $gradeRaw = (string) self::EMPTY_GRADE;
                    }

                    if (! LegacyGradeMap::knows((int) $gradeRaw)) {
                        $refused[] = "{$number}: karton {$barcode} grade legacy {$gradeRaw} di luar tabel terjemahan -- DITOLAK.";

                        continue;
                    }

                    $digit = trim((string) ($line['origin'] ?? ''));
                    $digit = $digit === '' ? substr($barcode, 0, 1) : $digit;

                    if (! isset(ImportLegacyStock::ORIGIN_NAMES[$digit])) {
                        $refused[] = "{$number}: karton {$barcode} kode asal \"{$digit}\" tidak dikenal -- DITOLAK.";

                        continue;
                    }

                    $original = null;

                    if (isset($taken[$barcode])) {
                        $original = $barcode;
                        $barcode .= '-2';
                        $stats['suffixBarcode']++;

                        if (isset($taken[$barcode])) {
                            $refused[] = "{$number}: barcode {$original} muncul lebih dari dua kali -- DITOLAK.";

                            continue;
                        }
                    }

                    $taken[$barcode] = true;
                    $place = LegacyGradeMap::resolve((int) $gradeRaw);
                    $ph = trim((string) ($line['ph'] ?? ''));
                    $stats['blankPh'] += $ph === '' ? 1 : 0;
                    $origin = ImportLegacyStock::ORIGIN_NAMES[$digit];
                    $perOrigin[$origin] = ($perOrigin[$origin] ?? 0) + 1;
                    $packDate = (string) ($line['pod'] ?? '');

                    if ($packDate === '' || str_starts_with($packDate, '0000')) {
                        $packDate = substr((string) $line['creatime'], 0, 10) ?: now()->toDateString();
                        $stats['zeroDate']++;
                    }

                    $weight = round((float) $line['weight'], 2);
                    $stats['kg'] += $weight;

                    $items[(int) $line['idtallydetail']] = [
                        'barcode' => $barcode, 'original_barcode' => $original, 'product_id' => $productId, 'warehouse_id' => $place['warehouse_id'], 'grade_id' => $place['grade_id'],
                        'weight' => $weight, 'qty_pcs' => (int) $line['pcs'], 'ph_level' => $ph === '' ? null : round((float) $ph, 1), 'pack_date' => $packDate,
                        'exp_date' => trim((string) ($line['exp'] ?? '')) === '' ? null : $line['exp'], 'origin' => $origin, 'created_at' => $line['creatime'] ?: now(), 'updated_at' => $line['creatime'] ?: now(),
                    ];
                }

                $header = ['sales_order_id' => (int) $soId, 'tally_number' => $number, 'status' => $status, 'seal_number' => trim((string) $row['sealnumb']) ?: null, 'created_by' => $fallback->id];
                $perStatus["{$legacyStat} -> {$status}"] = ($perStatus["{$legacyStat} -> {$status}"] ?? 0) + 1;
                $stats['created']++;
                $stats['items'] += count($items);
                $stats['emptyTallies'] += $items === [] ? 1 : 0;
                $tallyOfSo[$soId] = true;

                if (count($examples) < (int) $this->option('examples') && $items !== []) {
                    $examples[] = [$row, $lines->take(2)->all(), $header, array_slice($items, 0, 2, true)];
                }

                if ($apply) {
                    $at = $row['creatime'] ?: now();
                    $id = DB::table('tallies')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]);
                    $this->remember('tally', $legacyId, $id, true);

                    foreach (array_chunk($items, 500, true) as $chunk) {
                        $batch = [];

                        foreach ($chunk as $item) {
                            $batch[] = $item + ['tally_id' => $id];
                        }

                        DB::table('tally_items')->insert($batch);
                    }
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

        $this->report($apply, $stats, $perStatus, $perOrigin, $refused, $conflicts, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    private function remember(string $entity, int $legacyId, int $newId, bool $apply): void
    {
        if ($apply && $newId > 0) {
            DB::table('legacy_import_map')->insertOrIgnore(['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function report(bool $apply, array $stats, array $perStatus, array $perOrigin, array $refused, array $conflicts, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("tallies -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Karton: {$stats['items']} dibawa; berat total ".number_format($stats['kg'], 0, ',', '.')." kg. Tally tanpa karton: {$stats['emptyTallies']}.");
        $this->line('Status: '.collect($perStatus)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').'. Asal: '.collect($perOrigin)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').'.');
        $this->line("Nomor tally kembar diberi akhiran: {$stats['suffixNumber']}. Barcode kembar diberi -2: {$stats['suffixBarcode']}. Grade kosong -> J01: {$stats['emptyGrade']}. pH kosong: {$stats['blankPh']}. Tanggal kemas 0000-00-00 -> tanggal scan: {$stats['zeroDate']}.");
        $this->line("Dilewati: SO tidak ada {$stats['noSo']}, tally kosong pada SO yang punya tally lain {$stats['emptyDup']}, produk bukan impor {$stats['noProduct']} karton.");

        foreach ($examples as [$row, $lines, $header, $items]) {
            $this->line('');
            $this->line("Contoh {$row['notally']}:");
            $this->line("  LAMA  tally: notally={$row['notally']} idso={$row['idso']} stat=\"{$row['stat']}\" sealnumb=\"{$row['sealnumb']}\"");

            foreach ($lines as $l) {
                $this->line("        tallydetail: barcode={$l['barcode']} idgrade=".($l['idgrade'] ?? '(kosong)')." idbarang={$l['idbarang']} weight={$l['weight']} pcs={$l['pcs']} pod={$l['pod']} origin={$l['origin']}");
            }

            $this->line("  BARU  tallies: tally_number={$header['tally_number']} sales_order_id={$header['sales_order_id']} status={$header['status']} seal_number=\"".($header['seal_number'] ?? '')."\"");

            foreach ($items as $i) {
                $this->line("        tally_items: barcode={$i['barcode']} product_id={$i['product_id']} warehouse_id={$i['warehouse_id']} grade_id={$i['grade_id']} weight={$i['weight']} qty_pcs={$i['qty_pcs']} origin={$i['origin']}");
            }
        }

        $this->line('');
        $this->line('Catatan: ditulis tanpa event model (tidak ada gerak stok). DO dan Invoice dibawa di langkah berikutnya.');

        foreach (array_slice($refused, 0, 6) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
