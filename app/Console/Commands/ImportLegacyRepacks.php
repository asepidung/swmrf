<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * REPACK legacy (`repack` + `detailbahan` + `detailhasil`) -> `repacks` + `repack_materials` (bahan) + `repack_results` (hasil), SELURUH riwayat yang tidak dihapus
 * (keputusan Ayah, 21 Oktober 2026). Dry-run bawaan; `--apply` menulis; aman diulang (nomor repack yang sudah ada dilewati beserta isinya); satu penolakan membatalkan
 * semuanya. Ditulis lewat DB::table: TIDAK mengurangi/menambah stok, tidak membuat pergerakan stok, valuasi (HPP), atau kerugian finansial.
 *
 * Pemasangan:
 *  - repacks: doc_no = norepack (kembar diberi -2, -3 menurut idrepack); repack_date = tglrepack; SEMUA riwayat dibawa LOCKED + kunci 1 (keputusan Ayah, 21 Oktober 2026; di legacy hampir semuanya tidak pernah dikunci);
 *    created_by = idusers bila akun ada, selain itu `--fallback-user`; created_at = dibuat; note = note + catatan hasil (kolom `note` baris hasil legacy; aplikasi baru
 *    tidak punya kolom itu di hasil);
 *  - repack_materials (bahan): barcode apa adanya; product_id = idbarang; gudang + grade lewat `LegacyGradeMap` (grade KOSONG -> J01); weight = qty; qty_pcs = pcs;
 *    pack_date = pod (kosong/0000-00-00 -> tanggal scan); origin = digit `origin` (0/kosong -> digit awal barcode) diterjemahkan seperti stok
 *    (`ImportLegacyStock::ORIGIN_NAMES`); status IN_STOCK (seperti salinan status stok saat bahan dimasukkan);
 *  - repack_results (hasil): barcode = kdbarcode; product_id; gudang + grade lewat peta; weight = qty; qty_pcs = pcs (kosong -> 0); pack_date = packdate; exp_date = exp bila terisi;
 *    batch_no tidak dibawa (legacy tidak mencatatnya).
 * Barcode yang sama boleh muncul sebagai bahan di dua repack (82 kasus: bahan dibatalkan lalu dipakai lagi) dan sebagai hasil lalu bahan repack berikutnya (rantai repack).
 */
class ImportLegacyRepacks extends Command
{
    protected $signature = 'legacy:import-repacks
                            {--source= : Berkas snapshot SQL repack (legacy/tarik/tarik.sh repack)}
                            {--fallback-user= : Username akun pengganti untuk pembuat yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat Repack legacy beserta bahan dan hasilnya (dry-run bawaan)';

    private const EMPTY_GRADE = 1;

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path || ! is_file($path)) {
            $this->error('--source wajib diisi dengan berkas snapshot SQL repack yang ada.');

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
        $repacks = collect(LegacySqlDump::parseTable($sql, 'repack'))->sortBy(fn ($r) => (int) $r['idrepack'])->values();
        $materials = collect(LegacySqlDump::parseTable($sql, 'detailbahan'))->groupBy('idrepack');
        $results = collect(LegacySqlDump::parseTable($sql, 'detailhasil'))->groupBy('idrepack');
        unset($sql);

        $existing = DB::table('repacks')->pluck('id', 'doc_no');
        $productIds = Product::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();

        $stats = ['created' => 0, 'skipped' => 0, 'materials' => 0, 'results' => 0, 'kgIn' => 0.0, 'kgOut' => 0.0, 'suffix' => 0, 'emptyGrade' => 0, 'zeroDate' => 0, 'originFromBarcode' => 0, 'noProduct' => 0, 'noMaterial' => 0, 'noResult' => 0, 'notes' => 0, 'fallback' => 0];
        $perStatus = $refused = $conflicts = $examples = [];
        $numberSeen = [];

        $line = function (array $row, string $number, string $kind, ?string $barcodeKey) use ($productIds, &$stats, &$refused, &$conflicts): ?array {
            $barcode = trim((string) $row[$barcodeKey ?? 'barcode']);
            $productId = (int) $row['idbarang'];

            if (! isset($productIds[$productId])) {
                $stats['noProduct']++;
                $conflicts[] = "{$number}: {$kind} {$barcode} idbarang={$productId} bukan produk impor, dilewati.";

                return null;
            }

            $gradeRaw = trim((string) ($row['idgrade'] ?? ''));

            if ($gradeRaw === '') {
                $stats['emptyGrade']++;
                $gradeRaw = (string) self::EMPTY_GRADE;
            }

            if (! LegacyGradeMap::knows((int) $gradeRaw)) {
                $refused[] = "{$number}: {$kind} {$barcode} grade legacy {$gradeRaw} di luar tabel terjemahan -- DITOLAK.";

                return null;
            }

            $place = LegacyGradeMap::resolve((int) $gradeRaw);

            return ['barcode' => $barcode, 'product_id' => $productId, 'warehouse_id' => $place['warehouse_id'], 'grade_id' => $place['grade_id']];
        };

        $run = function () use ($repacks, $materials, $results, $existing, $userIds, $fallback, $apply, $line, &$stats, &$perStatus, &$refused, &$conflicts, &$examples, &$numberSeen): void {
            foreach ($repacks as $row) {
                $base = trim((string) $row['norepack']);
                $numberSeen[$base] = ($numberSeen[$base] ?? 0) + 1;
                $number = $numberSeen[$base] > 1 ? $base.'-'.$numberSeen[$base] : $base;
                $stats['suffix'] += $numberSeen[$base] > 1 ? 1 : 0;

                if (isset($existing[$number])) {
                    $stats['skipped']++;
                    $this->remember('repack', (int) $row['idrepack'], (int) $existing[$number], $apply);

                    continue;
                }

                $bahan = [];

                foreach ($materials->get($row['idrepack'], collect()) as $m) {
                    $item = $line($m, $number, 'bahan', 'barcode');

                    if (! $item) {
                        continue;
                    }

                    $digit = trim((string) ($m['origin'] ?? ''));

                    if (! isset(ImportLegacyStock::ORIGIN_NAMES[$digit])) {
                        $digit = substr($item['barcode'], 0, 1);
                        $stats['originFromBarcode']++;
                    }

                    if (! isset(ImportLegacyStock::ORIGIN_NAMES[$digit])) {
                        $refused[] = "{$number}: bahan {$item['barcode']} kode asal tidak dikenal -- DITOLAK.";

                        continue;
                    }

                    $pod = trim((string) ($m['pod'] ?? ''));

                    if ($pod === '' || str_starts_with($pod, '0000')) {
                        $pod = substr((string) $m['creatime'], 0, 10);
                        $stats['zeroDate']++;
                    }

                    $at = $m['creatime'] ?: now();
                    $bahan[] = $item + [
                        'weight' => round((float) $m['qty'], 2), 'qty_pcs' => (int) $m['pcs'], 'ph_level' => null, 'pack_date' => $pod, 'exp_date' => null,
                        'origin' => ImportLegacyStock::ORIGIN_NAMES[$digit], 'status' => 'IN_STOCK', 'created_at' => $at, 'updated_at' => $at,
                    ];
                    $stats['kgIn'] += (float) $m['qty'];
                }

                $hasil = [];
                $resultNotes = [];

                foreach ($results->get($row['idrepack'], collect()) as $h) {
                    $item = $line($h, $number, 'hasil', 'kdbarcode');

                    if (! $item) {
                        continue;
                    }

                    $pcs = trim((string) ($h['pcs'] ?? ''));
                    $exp = trim((string) ($h['exp'] ?? ''));
                    $at = $h['creatime'] ?: now();
                    $hasil[] = $item + [
                        'weight' => round((float) $h['qty'], 2), 'qty_pcs' => $pcs === '' ? 0 : (int) $pcs, 'ph_level' => null, 'pack_date' => $h['packdate'],
                        'exp_date' => ($exp === '' || str_starts_with($exp, '0000')) ? null : $exp, 'created_at' => $at, 'updated_at' => $at,
                    ];
                    $stats['kgOut'] += (float) $h['qty'];

                    if (trim((string) ($h['note'] ?? '')) !== '') {
                        $resultNotes[] = $item['barcode'].': '.trim((string) $h['note']);
                        $stats['notes']++;
                    }
                }

                $stats['noMaterial'] += $bahan === [] ? 1 : 0;
                $stats['noResult'] += $hasil === [] ? 1 : 0;

                $note = trim((string) ($row['note'] ?? ''));

                if ($resultNotes !== []) {
                    $note = trim($note.' | Catatan hasil legacy: '.implode('; ', $resultNotes), ' |');
                }

                $userId = (int) $row['idusers'];

                if (! isset($userIds[$userId])) {
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $locked = true; // keputusan Ayah (21 Okt 2026): semua riwayat repack ditutup (LOCKED), apa pun nilai `kunci` legacy
                $header = [
                    'doc_no' => $number, 'repack_date' => $row['tglrepack'], 'status' => $locked ? 'LOCKED' : 'OPEN', 'kunci' => $locked ? 1 : 0, 'note' => $note ?: null, 'created_by' => $userId,
                ];
                $perStatus[$header['status']] = ($perStatus[$header['status']] ?? 0) + 1;
                $stats['created']++;
                $stats['materials'] += count($bahan);
                $stats['results'] += count($hasil);

                if (count($examples) < (int) $this->option('examples') && $bahan !== [] && $hasil !== []) {
                    $examples[] = [$row, $materials->get($row['idrepack'])->take(1)->all(), $results->get($row['idrepack'])->take(1)->all(), $header, array_slice($bahan, 0, 1), array_slice($hasil, 0, 1)];
                }

                if ($apply) {
                    $at = $row['dibuat'] ?: now();
                    $id = DB::table('repacks')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]);
                    $this->remember('repack', (int) $row['idrepack'], $id, true);

                    foreach (array_chunk($bahan, 500) as $chunk) {
                        DB::table('repack_materials')->insert(array_map(fn ($i) => $i + ['repack_id' => $id], $chunk));
                    }

                    foreach (array_chunk($hasil, 500) as $chunk) {
                        DB::table('repack_results')->insert(array_map(fn ($i) => $i + ['repack_id' => $id], $chunk));
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

        $this->report($apply, $stats, $perStatus, $refused, $conflicts, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    private function remember(string $entity, int $legacyId, int $newId, bool $apply): void
    {
        if ($apply && $newId > 0) {
            DB::table('legacy_import_map')->insertOrIgnore(['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function report(bool $apply, array $stats, array $perStatus, array $refused, array $conflicts, array $examples): void
    {
        $fmt = fn (float $n) => number_format($n, 0, ',', '.');

        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("repacks -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Bahan: {$stats['materials']} barcode, ".$fmt($stats['kgIn'])." kg. Hasil: {$stats['results']} barcode, ".$fmt($stats['kgOut']).' kg.');
        $this->line('Status: '.collect($perStatus)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').". Nomor kembar diberi akhiran: {$stats['suffix']}. Repack tanpa bahan: {$stats['noMaterial']}, tanpa hasil: {$stats['noResult']}.");
        $this->line("Grade kosong -> J01: {$stats['emptyGrade']}. Tanggal kemas bahan kosong/0000 -> tanggal scan: {$stats['zeroDate']}. Asal dari digit barcode: {$stats['originFromBarcode']}. Catatan hasil digabung ke note: {$stats['notes']}. Produk bukan impor dilewati: {$stats['noProduct']}. Pembuat diganti akun pengganti: {$stats['fallback']}.");

        foreach ($examples as [$row, $oldM, $oldH, $header, $newM, $newH]) {
            $this->line('');
            $this->line("Contoh {$row['norepack']}:");
            $this->line("  LAMA  repack: norepack={$row['norepack']} tglrepack={$row['tglrepack']} kunci={$row['kunci']} idusers={$row['idusers']}");

            foreach ($oldM as $m) {
                $this->line("        detailbahan: barcode={$m['barcode']} idbarang={$m['idbarang']} idgrade={$m['idgrade']} qty={$m['qty']} pcs={$m['pcs']} origin={$m['origin']}");
            }

            foreach ($oldH as $h) {
                $this->line("        detailhasil: kdbarcode={$h['kdbarcode']} idbarang={$h['idbarang']} idgrade={$h['idgrade']} qty={$h['qty']} pcs={$h['pcs']}");
            }

            $this->line("  BARU  repacks: doc_no={$header['doc_no']} repack_date={$header['repack_date']} status={$header['status']} kunci={$header['kunci']}");

            foreach ($newM as $m) {
                $this->line("        repack_materials: barcode={$m['barcode']} product_id={$m['product_id']} warehouse_id={$m['warehouse_id']} grade_id={$m['grade_id']} weight={$m['weight']} qty_pcs={$m['qty_pcs']} origin={$m['origin']}");
            }

            foreach ($newH as $h) {
                $this->line("        repack_results: barcode={$h['barcode']} product_id={$h['product_id']} warehouse_id={$h['warehouse_id']} grade_id={$h['grade_id']} weight={$h['weight']} qty_pcs={$h['qty_pcs']}");
            }
        }

        $this->line('');
        $this->line('Catatan: ditulis tanpa event model (tidak ada gerak stok, valuasi/HPP, atau kerugian finansial).');

        foreach (array_slice($refused, 0, 6) as $text) {
            $this->line("  ! {$text}");
        }
    }
}
