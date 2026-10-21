<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * BONING legacy (`boning` + `labelboning`, satu label = satu barcode) -> `bonings` + `boning_items`, SELURUH riwayat yang tidak dihapus.
 * Langkah terakhir kelompok sapi (keputusan Ayah, 21 Oktober 2026). Dry-run bawaan; `--apply` menulis; aman diulang (nomor boning yang sudah ada
 * dilewati beserta labelnya); satu penolakan membatalkan semuanya. Ditulis lewat DB::table: TIDAK membuat stok, pergerakan stok, atau pemakaian material.
 *
 * Label ditarik per tahun (`tarik.sh boninglabel2023|2024|2025|2026`) dan dibaca satu berkas demi satu lewat `--labels=<berkas1,berkas2,...>`; berkas `--source`
 * memuat tabel `boning`.
 *
 * Pemasangan:
 *  - doc_no = batchboning apa adanya; boning_date = tglboning; kunci = kunci; status = LOCKED bila kunci = 1, selain itu OPEN; created_by = iduser bila akun ada, selain itu
 *    `--fallback-user`; created_at = dibuat; note = keterangan + "Jumlah sapi: <qtysapi>" (aplikasi baru tidak punya kolom jumlah sapi);
 *  - item: barcode = kdbarcode apa adanya; product_id = idbarang; gudang + grade lewat `LegacyGradeMap`; weight = qty; qty_pcs = pcs (kosong -> 0); ph_level = ph;
 *    pack_date = packdate; exp_date = exp bila terisi; batch_no = nomor boning; created_by = iduser label bila akun ada, selain itu akun pengganti;
 *  - HUBUNGAN boning ke karkas (`boning_carcasses`) TIDAK dibuat: di legacy tidak ada tabel yang menyimpannya dan pencocokan menurut supplier, tanggal, dan jumlah ekor
 *    hanya tepat untuk sebagian kecil boning (menebak lebih berbahaya daripada kosong).
 */
class ImportLegacyBonings extends Command
{
    protected $signature = 'legacy:import-bonings
                            {--source= : Berkas snapshot SQL yang memuat tabel boning (tarik.sh sapi)}
                            {--labels= : Berkas label boning, pisah koma (tarik.sh boninglabel2023 ... boninglabel2026)}
                            {--fallback-user= : Username akun pengganti untuk pembuat yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat boning legacy beserta labelnya (dry-run bawaan)';

    public function handle(): int
    {
        $path = $this->option('source');
        $labelFiles = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('labels')))));

        if (! $path || ! is_file($path) || $labelFiles === []) {
            $this->error('--source (tabel boning) dan --labels (berkas label, pisah koma) wajib diisi dengan berkas yang ada.');

            return self::FAILURE;
        }

        foreach ($labelFiles as $file) {
            if (! is_file($file)) {
                $this->error("Berkas label tidak ditemukan: {$file}");

                return self::FAILURE;
            }
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

        $apply = (bool) $this->option('apply');
        $bonings = collect(LegacySqlDump::parseTable(file_get_contents($path), 'boning'))->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idboning')->values();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $productIds = Product::query()->pluck('id')->flip();
        $existing = DB::table('bonings')->pluck('id', 'doc_no');
        $docById = $bonings->mapWithKeys(fn ($r) => [(int) $r['idboning'] => trim((string) $r['batchboning'])]);

        $stats = ['created' => 0, 'skipped' => 0, 'items' => 0, 'itemsIn' => 0, 'kg' => 0.0, 'pcsBlank' => 0, 'zero' => 0, 'fallback' => 0, 'orphans' => 0, 'noProduct' => 0];
        $perStore = $examples = $conflicts = [];
        $refused = [];
        $newDocs = [];

        $run = function () use ($bonings, $docById, $labelFiles, $userIds, $productIds, $existing, $fallback, $apply, &$stats, &$perStore, &$examples, &$conflicts, &$refused, &$newDocs): void {
            foreach ($bonings as $row) {
                $docNo = trim((string) $row['batchboning']);

                if (isset($existing[$docNo])) {
                    $stats['skipped']++;
                    $this->remember('boning', (int) $row['idboning'], (int) $existing[$docNo], $apply);

                    continue;
                }

                $note = trim((string) ($row['keterangan'] ?? ''));
                $qtySapi = (int) round((float) ($row['qtysapi'] ?? 0));
                $note = trim($note.($qtySapi > 0 ? ' | Jumlah sapi: '.$qtySapi : ''), ' |');
                $userId = (int) $row['iduser'];

                if (! isset($userIds[$userId])) {
                    $note = trim($note.' | Pembuat legacy: iduser '.$userId, ' |');
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $locked = (int) ($row['kunci'] ?? 0) === 1;
                $at = $row['dibuat'] ?: $row['tglboning'].' 00:00:00';
                $header = [
                    'doc_no' => $docNo, 'boning_date' => $row['tglboning'], 'status' => $locked ? 'LOCKED' : 'OPEN', 'kunci' => $locked ? 1 : 0,
                    'note' => $note ?: null, 'created_by' => $userId,
                ];

                $id = $apply ? DB::table('bonings')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]) : -1;
                $this->remember('boning', (int) $row['idboning'], $id, $apply);
                $newDocs[(int) $row['idboning']] = ['id' => $id, 'doc' => $docNo, 'at' => $at, 'user' => $userId];
                $stats['created']++;

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[(int) $row['idboning']] = [$row, $header, []];
                }
            }

            foreach ($labelFiles as $file) {
                $labels = LegacySqlDump::parseTable(file_get_contents($file), 'labelboning');
                $chunks = [];

                foreach ($labels as $label) {
                    if ((int) $label['is_deleted'] !== 0) {
                        continue;
                    }

                    $stats['itemsIn']++;
                    $doc = $newDocs[(int) $label['idboning']] ?? null;

                    if ($doc === null) {
                        // Label pada boning yang sudah ada (diulang) atau yang dihapus/tidak ada.
                        if (! isset($docById[(int) $label['idboning']])) {
                            $stats['orphans']++;
                        }

                        continue;
                    }

                    if (! isset($productIds[(int) $label['idbarang']])) {
                        $stats['noProduct']++;
                        $conflicts[] = "{$doc['doc']}: {$label['kdbarcode']} produk idbarang={$label['idbarang']} tidak diimpor, label dilewati.";

                        continue;
                    }

                    $legacyGrade = (int) $label['idgrade'];

                    if (! LegacyGradeMap::knows($legacyGrade)) {
                        $refused[] = "{$doc['doc']}: {$label['kdbarcode']} grade legacy {$legacyGrade} di luar tabel terjemahan -- DITOLAK.";

                        continue;
                    }

                    $place = LegacyGradeMap::resolve($legacyGrade);
                    $weight = round((float) $label['qty'], 2);
                    $pcs = trim((string) ($label['pcs'] ?? ''));
                    $ph = trim((string) ($label['ph'] ?? ''));
                    $exp = trim((string) ($label['exp'] ?? ''));
                    $labelUser = (int) $label['iduser'];
                    $stats['pcsBlank'] += $pcs === '' ? 1 : 0;
                    $stats['zero'] += $weight <= 0 ? 1 : 0;
                    $stats['kg'] += $weight;
                    $stats['items']++;
                    $key = "{$legacyGrade} -> gudang ".LegacyGradeMap::MAP[$legacyGrade][1].' + '.LegacyGradeMap::MAP[$legacyGrade][2];
                    $perStore[$key] = ($perStore[$key] ?? 0) + 1;

                    $item = [
                        'boning_id' => $doc['id'], 'product_id' => (int) $label['idbarang'], 'warehouse_id' => $place['warehouse_id'], 'grade_id' => $place['grade_id'],
                        'weight' => $weight, 'qty_pcs' => $pcs === '' ? 0 : (int) $pcs, 'ph_level' => $ph === '' ? null : round((float) $ph, 2),
                        'pack_date' => $label['packdate'] ?: substr((string) $label['creatime'], 0, 10), 'exp_date' => ($exp === '' || str_starts_with($exp, '0000')) ? null : $exp,
                        'barcode' => trim((string) $label['kdbarcode']), 'batch_no' => $doc['doc'],
                        'created_by' => isset($userIds[$labelUser]) ? $labelUser : $fallback->id,
                        'created_at' => $label['creatime'] ?: $doc['at'], 'updated_at' => $label['creatime'] ?: $doc['at'],
                    ];

                    if (isset($examples[(int) $label['idboning']]) && count($examples[(int) $label['idboning']][2]) < 2) {
                        $examples[(int) $label['idboning']][2][] = [$label, $item];
                    }

                    if ($apply) {
                        $chunks[] = $item;

                        if (count($chunks) >= 500) {
                            DB::table('boning_items')->insert($chunks);
                            $chunks = [];
                        }
                    }
                }

                if ($apply && $chunks !== []) {
                    DB::table('boning_items')->insert($chunks);
                }

                unset($labels);
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

        $this->report($apply, $stats, $perStore, $conflicts, $refused, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    private function remember(string $entity, int $legacyId, int $newId, bool $apply): void
    {
        if ($apply && $newId > 0) {
            DB::table('legacy_import_map')->insertOrIgnore(['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function report(bool $apply, array $stats, array $perStore, array $conflicts, array $refused, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("bonings -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("boning_items -- label hidup dibaca: {$stats['itemsIn']}, dibawa: {$stats['items']}, total ".number_format($stats['kg'], 2, ',', '.')." kg. pcs kosong -> 0: {$stats['pcsBlank']}. Berat <= 0: {$stats['zero']}. Label pada boning yang dihapus/tidak ada: {$stats['orphans']}. Produk tidak diimpor: {$stats['noProduct']}.");
        $this->line('Per gudang+grade: '.collect($perStore)->map(fn ($n, $k) => "[{$k}]={$n}")->implode(', ').". Pembuat diganti akun pengganti: {$stats['fallback']} boning.");

        foreach ($examples as [$row, $header, $items]) {
            $this->line('');
            $this->line("Contoh boning {$row['batchboning']}:");
            $this->line("  LAMA  boning: batchboning={$row['batchboning']} tglboning={$row['tglboning']} idsupplier={$row['idsupplier']} qtysapi={$row['qtysapi']} kunci={$row['kunci']} keterangan=\"".($row['keterangan'] ?? '').'"');

            foreach ($items as [$label, $item]) {
                $this->line("        labelboning: kdbarcode={$label['kdbarcode']} idbarang={$label['idbarang']} idgrade={$label['idgrade']} qty={$label['qty']} pcs=".($label['pcs'] === '' ? '(kosong)' : $label['pcs']).' ph='.($label['ph'] ?? '(kosong)')." packdate={$label['packdate']}");
            }

            $this->line("  BARU  bonings: doc_no={$header['doc_no']} boning_date={$header['boning_date']} status={$header['status']} kunci={$header['kunci']} note=\"".($header['note'] ?? '').'"');

            foreach ($items as [$label, $item]) {
                $this->line("        item: barcode={$item['barcode']} product_id={$item['product_id']} gudang_id={$item['warehouse_id']} grade_id={$item['grade_id']} weight={$item['weight']} qty_pcs={$item['qty_pcs']} ph=".($item['ph_level'] ?? '(kosong)')." pack_date={$item['pack_date']} batch={$item['batch_no']}");
            }
        }

        $this->line('');
        $this->line('Catatan: boning_carcasses tidak dibuat (legacy tidak menyimpan hubungan boning ke karkas). Tidak membuat stok, pergerakan stok, atau pemakaian material.');

        foreach (array_slice($conflicts, 0, 6) as $line) {
            $this->line("  ! {$line}");
        }
        foreach (array_slice($refused, 0, 6) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
