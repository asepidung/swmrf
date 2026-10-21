<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LegacySqlDump;
use App\Support\LooseName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * KARKAS legacy (`carcase` + `carcasedetail`) -> `carcasses` + `carcass_items`. Langkah 3 dari kelompok sapi (keputusan Ayah, 21 Oktober 2026).
 * Dry-run bawaan; `--apply` menulis; aman diulang (nomor karkas yang sudah ada dilewati); satu penolakan membatalkan semuanya. Ditulis lewat DB::table.
 *
 * Aplikasi baru mewajibkan karkas menunjuk ke penimbangan (dan item karkas ke baris timbang). Penghubungnya adalah `legacy_import_map` yang diisi
 * `legacy:import-cattle-receipts` (idweigh -> penimbangan, idweighdetail -> baris timbang). Karkas LAMA yang di legacy tidak punya catatan timbang
 * (sebelum modul timbang legacy dipakai) TIDAK bisa dihubungkan dan DILEWATI serta dilaporkan (keputusan tentangnya menunggu Ayah).
 *
 * Karkas LAMA (tanpa catatan timbang) dibawa juga (keputusan Ayah, 21 Oktober 2026): untuk tiap karkas dibentuk receive + timbang PENGGANTI dari data karkas itu
 * sendiri (eartag, kelas dari kolom breed, berat hidup), terhubung ke satu PO sapi pengganti per supplier ("CPO-LGC-S<idsupplier>"), semuanya diberi catatan "dibentuk dari data
 * karkas legacy". Breed kosong -> kelas STEER (dicatat di catatan baris). Eartag mengikuti aturan unik yang sama (yang lebih lama mengalah dengan akhiran tanggal).
 * `--without-synthetic` mematikannya (karkas lama hanya dilaporkan).
 *
 * Pemasangan: carcass_number = "CRC-<idcarcase 4 digit>" (legacy tidak punya nomor karkas); kill_date = killdate; created_by = idusers bila akun ada, selain itu
 * `--fallback-user`; item: carcass_1 = carcase1, carcass_2 = carcase2, hides, tail apa adanya. Kelas dan eartag tetap di baris receive (tidak diulang di karkas).
 */
class ImportLegacyCarcasses extends Command
{
    protected $signature = 'legacy:import-carcasses
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh sapi)}
                            {--fallback-user= : Username akun pengganti untuk pembuat yang tidak ada di aplikasi}
                            {--without-synthetic : Jangan membentuk receive/timbang pengganti untuk karkas lama (dilewati saja)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan riwayat karkas legacy (dry-run bawaan)';

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path || ! is_file($path)) {
            $this->error('--source wajib diisi dengan berkas snapshot SQL yang ada.');

            return self::FAILURE;
        }

        $fallback = $this->option('fallback-user') ? User::withoutGlobalScopes()->where('username', $this->option('fallback-user'))->first() : null;

        if (! $fallback) {
            $this->error('--fallback-user wajib diisi dengan username yang ada.');

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');
        $table = fn (string $name) => collect(LegacySqlDump::parseTable($sql, $name));

        $weighings = DB::table('legacy_import_map')->where('entity', 'cattle_weighing')->pluck('new_id', 'legacy_id');
        $weighItems = DB::table('legacy_import_map')->where('entity', 'cattle_weighing_item')->pluck('new_id', 'legacy_id');
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $existing = DB::table('carcasses')->pluck('id', 'carcass_number');
        $details = $table('carcasedetail')->groupBy('idcarcase');
        $rows = $table('carcase')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idcarcase')->values();

        $synthetic = ! $this->option('without-synthetic');
        $classes = DB::table('cattle_classes')->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [LooseName::key($name) => $id]);
        $taken = DB::table('cattle_receiving_items')->pluck('eartag')->map(fn ($t) => strtoupper($t))->flip()->all();
        $supplierIds = \App\Models\Supplier::query()->pluck('id')->flip();
        $poIds = [];
        $stats = ['created' => 0, 'skipped' => 0, 'unlinked' => 0, 'unlinkedHeads' => 0, 'items' => 0, 'itemsLost' => 0, 'fallback' => 0, 'carcass1' => 0.0, 'carcass2' => 0.0, 'oldest' => null, 'newest' => null, 'duplicateLinks' => 0, 'synthetic' => 0, 'syntheticHeads' => 0, 'blankBreed' => 0, 'tagsChanged' => 0];
        $conflicts = $examples = [];
        $usedItems = [];

        $run = function () use ($rows, $details, $weighings, $weighItems, $userIds, $existing, $fallback, $apply, $synthetic, $classes, $supplierIds, &$taken, &$poIds, &$stats, &$conflicts, &$examples, &$usedItems): void {
            foreach ($rows as $row) {
                $number = 'CRC-'.str_pad((string) $row['idcarcase'], 4, '0', STR_PAD_LEFT);
                $weighingId = $weighings[(int) $row['idweight']] ?? null;

                if (isset($existing[$number])) {
                    $stats['skipped']++;

                    if ($apply) {
                        $this->remember('carcass', (int) $row['idcarcase'], (int) $existing[$number]);
                        $byItem = DB::table('carcass_items')->where('carcass_id', $existing[$number])->pluck('id', 'cattle_weighing_item_id');

                        foreach ($details->get($row['idcarcase'], collect()) as $d) {
                            $weighItem = $weighItems[(int) $d['idweightdetail']] ?? null;

                            if ($weighItem !== null && isset($byItem[$weighItem])) {
                                $this->remember('carcass_item', (int) $d['iddetail'], (int) $byItem[$weighItem]);
                            }
                        }
                    }

                    continue;
                }

                $note = trim((string) ($row['note'] ?? ''));
                $userId = (int) $row['idusers'];

                if (! isset($userIds[$userId])) {
                    $note = trim($note.' | Pembuat legacy: iduser '.$userId, ' |');
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $synthItems = [];

                if ($weighingId === null) {
                    $stats['unlinked']++;
                    $stats['unlinkedHeads'] += $details->get($row['idcarcase'], collect())->count();
                    $stats['oldest'] = min($stats['oldest'] ?? '9999', (string) $row['killdate']);
                    $stats['newest'] = max($stats['newest'] ?? '0000', (string) $row['killdate']);

                    if (! $synthetic) {
                        continue;
                    }

                    $supplierId = ImportLegacyMaster::SUPPLIER_MERGES[(int) $row['idsupplier']] ?? (int) $row['idsupplier'];

                    if (! isset($supplierIds[$supplierId])) {
                        $conflicts[] = "{$number}: supplier idsupplier={$row['idsupplier']} tidak ada, karkas lama dilewati.";

                        continue;
                    }

                    [$weighingId, $synthItems] = $this->synthesize($row, $details->get($row['idcarcase'], collect()), $supplierId, $userId, $classes, $taken, $poIds, $apply, $stats);
                    $note = trim($note.' | Dibentuk dari data karkas legacy (tanpa catatan receive/timbang di legacy)', ' |');
                }

                $items = [];

                foreach ($details->get($row['idcarcase'], collect()) as $d) {
                    $weighItem = $synthItems[(int) $d['iddetail']] ?? ($weighItems[(int) $d['idweightdetail']] ?? null);

                    if ($weighItem === null) {
                        $stats['itemsLost']++;
                        $conflicts[] = "{$number}: baris karkas iddetail={$d['iddetail']} (eartag {$d['eartag']}) tidak punya baris timbang, dilewati.";

                        continue;
                    }

                    if (isset($usedItems[$weighItem])) {
                        $stats['duplicateLinks']++;
                        $conflicts[] = "{$number}: baris timbang idweighdetail={$d['idweightdetail']} dipakai dua baris karkas (eartag {$d['eartag']}); keduanya dibawa.";
                    }

                    $usedItems[$weighItem] = true;
                    $items[(int) $d['iddetail']] = [
                        'cattle_weighing_item_id' => $weighItem,
                        'carcass_1' => round((float) $d['carcase1'], 2),
                        'carcass_2' => round((float) $d['carcase2'], 2),
                        'hides' => round((float) $d['hides'], 2),
                        'tail' => round((float) $d['tail'], 2),
                    ];
                    $stats['carcass1'] += (float) $d['carcase1'];
                    $stats['carcass2'] += (float) $d['carcase2'];
                }

                $header = ['carcass_number' => $number, 'cattle_weighing_id' => $weighingId, 'kill_date' => $row['killdate'], 'note' => trim($note, ' |') ?: null, 'created_by' => $userId];
                $stats['created']++;
                $stats['items'] += count($items);

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $details->get($row['idcarcase'], collect())->take(2)->all(), $header, array_slice($items, 0, 2, true)];
                }

                if ($apply) {
                    $at = $row['killdate'].' 00:00:00';
                    $id = DB::table('carcasses')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]);
                    $this->remember('carcass', (int) $row['idcarcase'], $id);

                    foreach ($items as $legacyId => $item) {
                        $this->remember('carcass_item', (int) $legacyId, DB::table('carcass_items')->insertGetId($item + ['carcass_id' => $id, 'created_at' => $at, 'updated_at' => $at]));
                    }
                }
            }
        };

        $apply ? DB::transaction($run) : $run();

        $this->report($apply, $stats, $conflicts, $examples);

        return self::SUCCESS;
    }

    /**
     * Membentuk receive + timbang pengganti untuk SATU karkas lama; mengembalikan [id penimbangan, [iddetail karkas => id baris timbang]].
     * Saat dry-run (tanpa --apply) id-nya palsu (-1) dan tidak ada yang ditulis.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $classes
     * @param  array<string, true>  $taken
     * @param  array<int, int>  $poIds
     * @return array{0: int, 1: array<int, int>}
     */
    private function synthesize(array $row, $rows, int $supplierId, int $userId, $classes, array &$taken, array &$poIds, bool $apply, array &$stats): array
    {
        $id = (int) $row['idcarcase'];
        $at = $row['killdate'].' 00:00:00';
        $tagNote = 'Dibentuk dari data karkas legacy (idcarcase='.$id.')';
        $fakeId = -1;

        if (! isset($poIds[$supplierId])) {
            $number = 'CPO-LGC-S'.$supplierId;
            $existingPo = DB::table('purchase_cattles')->where('document_number', $number)->value('id');
            $poIds[$supplierId] = $existingPo ?: ($apply ? DB::table('purchase_cattles')->insertGetId([
                'document_number' => $number, 'supplier_id' => $supplierId, 'shipping_date' => $row['killdate'], 'summary_note' => 'PO pengganti untuk karkas legacy sebelum modul receive/timbang dipakai',
                'created_by' => $userId, 'created_at' => $at, 'updated_at' => $at,
            ]) : $fakeId);
        }

        $receivingId = $apply ? DB::table('cattle_receivings')->insertGetId([
            'receiving_number' => 'RCV-K'.str_pad((string) $id, 4, '0', STR_PAD_LEFT), 'purchase_cattle_id' => $poIds[$supplierId], 'supplier_id' => $supplierId, 'receive_date' => $row['killdate'],
            'note' => $tagNote, 'created_by' => $userId, 'created_at' => $at, 'updated_at' => $at,
        ]) : $fakeId;
        $weighingId = $apply ? DB::table('cattle_weighings')->insertGetId([
            'weighing_number' => 'WGH-K'.str_pad((string) $id, 4, '0', STR_PAD_LEFT), 'cattle_receiving_id' => $receivingId, 'weighing_date' => $row['killdate'],
            'note' => $tagNote, 'created_by' => $userId, 'created_at' => $at, 'updated_at' => $at,
        ]) : $fakeId;

        $links = [];

        foreach ($rows as $d) {
            $classId = $classes[LooseName::key($d['breed'] ?? '')] ?? null;
            $itemNote = null;

            if ($classId === null) {
                $classId = $classes[LooseName::key('STEER')];
                $itemNote = 'kelas tidak tercatat di legacy; diisi STEER';
                $stats['blankBreed']++;
            }

            $tag = strtoupper(trim((string) $d['eartag']));
            $candidate = $tag;
            $base = $tag;
            $n = 2;

            if (isset($taken[$candidate])) {
                $base = $tag.'-'.date('ymd', strtotime($row['killdate']));
                $candidate = $base;
                $stats['tagsChanged']++;
            }

            while (isset($taken[$candidate])) {
                $candidate = $base.'-'.$n++;
            }

            $taken[$candidate] = true;
            $weight = (float) $d['berat'];
            $stats['syntheticHeads']++;

            if ($apply) {
                $receivingItem = DB::table('cattle_receiving_items')->insertGetId([
                    'cattle_receiving_id' => $receivingId, 'cattle_class_id' => $classId, 'eartag' => $candidate, 'initial_weight' => round($weight, 2), 'notes' => $itemNote,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
                $links[(int) $d['iddetail']] = DB::table('cattle_weighing_items')->insertGetId([
                    'cattle_weighing_id' => $weighingId, 'cattle_receiving_item_id' => $receivingItem, 'actual_weight' => $weight > 0 ? round($weight, 2) : null,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
            } else {
                $links[(int) $d['iddetail']] = $fakeId;
            }
        }

        $stats['synthetic']++;

        return [$weighingId, $links];
    }

    /** Mencatat penghubung id legacy -> id baru (hanya saat --apply). */
    private function remember(string $entity, int $legacyId, int $newId): void
    {
        DB::table('legacy_import_map')->insertOrIgnore(['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function report(bool $apply, array $stats, array $conflicts, array $examples): void
    {
        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("carcasses -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}; ekor (item): {$stats['items']}; karkas 1 total ".number_format($stats['carcass1'], 0, ',', '.').' kg, karkas 2 total '.number_format($stats['carcass2'], 0, ',', '.').' kg');
        $this->line("TANPA catatan timbang di legacy: {$stats['unlinked']} karkas dengan {$stats['unlinkedHeads']} ekor, killdate ".($stats['oldest'] ?? '-').' .. '.($stats['newest'] ?? '-').". Dibentuk receive+timbang pengganti: {$stats['synthetic']} karkas, {$stats['syntheticHeads']} ekor (kelas diisi STEER karena breed kosong: {$stats['blankBreed']}; eartag diberi akhiran tanggal: {$stats['tagsChanged']}).");
        $this->line("Baris karkas tanpa baris timbang: {$stats['itemsLost']}. Baris timbang dipakai ganda: {$stats['duplicateLinks']}. Pembuat diganti akun pengganti: {$stats['fallback']}.");

        foreach ($examples as [$row, $lines, $header, $items]) {
            $this->line('');
            $this->line("Contoh karkas idcarcase={$row['idcarcase']}:");
            $this->line("  LAMA  carcase: killdate={$row['killdate']} idweight={$row['idweight']} idusers={$row['idusers']}");

            foreach ($lines as $l) {
                $this->line("        carcasedetail: idweightdetail={$l['idweightdetail']} eartag={$l['eartag']} berat={$l['berat']} carcase1={$l['carcase1']} carcase2={$l['carcase2']} hides={$l['hides']} tail={$l['tail']}");
            }

            $this->line("  BARU  carcasses: carcass_number={$header['carcass_number']} cattle_weighing_id={$header['cattle_weighing_id']} kill_date={$header['kill_date']}");

            foreach ($items as $i) {
                $this->line("        item: cattle_weighing_item_id={$i['cattle_weighing_item_id']} carcass_1={$i['carcass_1']} carcass_2={$i['carcass_2']} hides={$i['hides']} tail={$i['tail']}");
            }
        }

        $this->line('');
        $this->line('Catatan: karkas tanpa catatan timbang tidak bisa dihubungkan (aplikasi baru mewajibkan penimbangan); keputusan menunggu Ayah.');

        foreach (array_slice($conflicts, 0, 6) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
