<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LegacySqlDump;
use App\Support\LooseName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RECEIVE dan WEIGHING sapi hidup legacy -> `cattle_receivings` (+ items) dan `cattle_weighings` (+ items), SELURUH riwayat yang tidak dihapus.
 * Langkah 2 dari kelompok sapi (keputusan Ayah, 21 Oktober 2026). Dry-run bawaan; `--apply` menulis; aman diulang (nomor receive/timbang yang sudah ada
 * dilewati); satu penolakan membatalkan semuanya. Ditulis lewat DB::table (tanpa event model).
 *
 * Pemasangan:
 *  - receive: receiving_number = "RCV-<idreceive 4 digit>" (legacy tidak punya nomor receive); purchase_cattle_id = PO sapi menurut nomor PO (aturan akhiran yang sama dengan
 *    `legacy:import-cattle-pos`); supplier dari PO (lewat peta penggabungan); receive_date = receipt_date; doc_no, sv_ok, skkh_ok, note apa adanya; created_by = createby bila akun ada, selain itu `--fallback-user`;
 *  - item receive: kelas sapi menurut nama (longgar), eartag dinormalkan (huruf besar, tanpa spasi tepi), initial_weight = weight;
 *  - EARTAG harus unik di aplikasi baru (sapi hidup satu eartag satu ekor), sedangkan legacy memakai ulang nomor eartag: untuk eartag kembar, yang TERBARU (menurut tanggal terima, lalu
 *    id) memakai eartag aslinya dan yang lebih lama diberi akhiran "-<yymmdd tanggal terima>" (bila masih bentrok ditambah -2, -3, ...); semuanya dilaporkan;
 *  - timbang: weighing_number = weigh_no; cattle_receiving_id lewat idreceive; weighing_date = weigh_date; created_by = createby; item: actual_weight = weight, tetapi 0 -> KOSONG
 *    (sapi belum ditimbang itu kosong, bukan nol, aturan aplikasi baru), cattle_receiving_item_id lewat idreceivedetail;
 *  - receive/timbang yang dihapus (`is_deleted` bukan 0) dan rinciannya diabaikan. Tabel susut legacy (`cattle_loss_receive`) tidak punya padanan: susut dihitung dari receive vs timbang.
 */
class ImportLegacyCattleReceipts extends Command
{
    protected $signature = 'legacy:import-cattle-receipts
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh sapi)}
                            {--fallback-user= : Username akun pengganti untuk pembuat yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan riwayat receive dan weighing sapi legacy (dry-run bawaan)';

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
        $live = fn ($rows) => $rows->filter(fn ($r) => (int) $r['is_deleted'] === 0);

        $classes = DB::table('cattle_classes')->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [LooseName::key($name) => $id]);
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $purchaseIds = DB::table('purchase_cattles')->pluck('id', 'document_number');
        $purchaseSupplier = DB::table('purchase_cattles')->pluck('supplier_id', 'id');

        $pos = $live($table('pocattle'))->sortBy('idpo');
        $poNumber = [];
        $seenPo = [];

        foreach ($pos as $po) {
            $number = trim((string) $po['nopo']);
            $seenPo[$number] = ($seenPo[$number] ?? 0) + 1;
            $poNumber[(int) $po['idpo']] = $seenPo[$number] > 1 ? $number.'-'.$seenPo[$number] : $number;
        }

        $receipts = $live($table('cattle_receive'))->sortBy('idreceive')->values();
        $receiptIds = $receipts->pluck('idreceive')->flip();
        $receiptDetails = $table('cattle_receive_detail')->filter(fn ($r) => isset($receiptIds[$r['idreceive']]))->groupBy('idreceive');
        $weighings = $live($table('weight_cattle'))->sortBy('idweigh')->values();
        $weighIds = $weighings->pluck('idweigh')->flip();
        $weighDetails = $table('weight_cattle_detail')->filter(fn ($r) => isset($weighIds[$r['idweigh']]))->groupBy('idweigh');

        // Eartag unik: terbaru memakai aslinya, yang lebih lama diberi akhiran tanggal terima.
        $date = $receipts->mapWithKeys(fn ($r) => [$r['idreceive'] => (string) $r['receipt_date']]);
        $details = $receipts->flatMap(fn ($r) => $receiptDetails->get($r['idreceive'], collect()))->values();
        $tagOf = [];
        $existingReceipts = DB::table('cattle_receivings')->pluck('id', 'receiving_number');
        $ownIds = $receipts->map(fn ($r) => $existingReceipts['RCV-'.str_pad((string) $r['idreceive'], 4, '0', STR_PAD_LEFT)] ?? null)->filter()->all();
        // Eartag yang sudah dipakai SELAIN oleh receive yang diimpor ini (supaya pengulangan menghasilkan eartag yang sama).
        $taken = DB::table('cattle_receiving_items')->whereNotIn('cattle_receiving_id', $ownIds ?: [0])->pluck('eartag')->map(fn ($t) => strtoupper($t))->flip()->all();
        $changed = [];

        foreach ($details->groupBy(fn ($d) => strtoupper(trim((string) $d['eartag']))) as $tag => $group) {
            $ordered = $group->sortBy(fn ($d) => [$date[$d['idreceive']] ?? '', (int) $d['idreceivedetail']])->values();

            foreach ($ordered as $i => $d) {
                $isNewest = $i === $ordered->count() - 1;
                $candidate = $isNewest ? $tag : $tag.'-'.date('ymd', strtotime($date[$d['idreceive']] ?? '1970-01-01'));
                $base = $candidate;
                $n = 2;

                while (isset($taken[$candidate])) {
                    $candidate = $base.'-'.$n++;
                }

                $taken[$candidate] = true;
                $tagOf[(int) $d['idreceivedetail']] = $candidate;

                if ($candidate !== $tag) {
                    $changed[] = "{$tag} ({$date[$d['idreceive']]}) -> {$candidate}";
                }
            }
        }

        $stats = ['receipts' => 0, 'skippedReceipts' => 0, 'items' => 0, 'weighings' => 0, 'skippedWeighings' => 0, 'weighItems' => 0, 'unweighed' => 0, 'fallback' => 0, 'kg' => 0.0, 'actualKg' => 0.0];
        $refused = $conflicts = $examples = [];
        $existingWeighings = DB::table('cattle_weighings')->pluck('id', 'weighing_number');

        $run = function () use ($receipts, $receiptDetails, $weighings, $weighDetails, $poNumber, $purchaseIds, $purchaseSupplier, $classes, $userIds, $fallback, $tagOf, $existingReceipts, $existingWeighings, $apply, &$stats, &$refused, &$conflicts, &$examples): void {
            $receivingIds = [];
            $itemIds = [];

            foreach ($receipts as $row) {
                $number = 'RCV-'.str_pad((string) $row['idreceive'], 4, '0', STR_PAD_LEFT);

                if (isset($existingReceipts[$number])) {
                    $stats['skippedReceipts']++;
                    $receivingIds[(int) $row['idreceive']] = $existingReceipts[$number];

                    $byTag = DB::table('cattle_receiving_items')->where('cattle_receiving_id', $existingReceipts[$number])->pluck('id', 'eartag');

                    foreach ($receiptDetails->get($row['idreceive'], collect()) as $d) {
                        if (isset($byTag[$tagOf[(int) $d['idreceivedetail']]])) {
                            $itemIds[(int) $d['idreceivedetail']] = $byTag[$tagOf[(int) $d['idreceivedetail']]];
                        }
                    }

                    if ($apply) {
                        $this->remember('cattle_receiving', (int) $row['idreceive'], (int) $existingReceipts[$number]);

                        foreach ($receiptDetails->get($row['idreceive'], collect()) as $d) {
                            if (isset($itemIds[(int) $d['idreceivedetail']])) {
                                $this->remember('cattle_receiving_item', (int) $d['idreceivedetail'], (int) $itemIds[(int) $d['idreceivedetail']]);
                            }
                        }
                    }

                    continue;
                }

                $poKey = $poNumber[(int) $row['idpo']] ?? null;
                $purchaseId = $poKey !== null ? ($purchaseIds[$poKey] ?? null) : null;

                if ($purchaseId === null) {
                    $conflicts[] = "{$number}: PO sapi induk idpo={$row['idpo']} tidak ada di aplikasi, receive dilewati.";

                    continue;
                }

                $note = trim((string) ($row['note'] ?? ''));
                $userId = (int) $row['createby'];

                if (! isset($userIds[$userId])) {
                    $note = trim($note.' | Pembuat legacy: iduser '.$userId, ' |');
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $header = [
                    'receiving_number' => $number,
                    'purchase_cattle_id' => $purchaseId,
                    'supplier_id' => $purchaseSupplier[$purchaseId],
                    'receive_date' => $row['receipt_date'],
                    'doc_no' => trim((string) ($row['doc_no'] ?? '')) ?: null,
                    'sv_ok' => (int) ($row['sv_ok'] ?? 0) === 1,
                    'skkh_ok' => (int) ($row['skkh_ok'] ?? 0) === 1,
                    'note' => trim($note, ' |') ?: null,
                    'created_by' => $userId,
                ];

                $items = [];

                foreach ($receiptDetails->get($row['idreceive'], collect()) as $d) {
                    $classId = $classes[LooseName::key($d['class'])] ?? null;

                    if ($classId === null) {
                        $refused[] = "{$number}: kelas sapi \"{$d['class']}\" tidak dikenal -- DITOLAK.";

                        continue;
                    }

                    $items[(int) $d['idreceivedetail']] = [
                        'cattle_class_id' => $classId,
                        'eartag' => $tagOf[(int) $d['idreceivedetail']],
                        'initial_weight' => round((float) $d['weight'], 2),
                        'notes' => trim((string) ($d['notes'] ?? '')) ?: null,
                    ];
                    $stats['kg'] += (float) $d['weight'];
                }

                $stats['receipts']++;
                $stats['items'] += count($items);

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $receiptDetails->get($row['idreceive'], collect())->take(2)->all(), $header, array_slice($items, 0, 2, true), $poKey];
                }

                if ($apply) {
                    $id = DB::table('cattle_receivings')->insertGetId($header + ['created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]);
                    $receivingIds[(int) $row['idreceive']] = $id;

                    $this->remember('cattle_receiving', (int) $row['idreceive'], $id);

                    foreach ($items as $legacyId => $item) {
                        $itemIds[$legacyId] = DB::table('cattle_receiving_items')->insertGetId($item + ['cattle_receiving_id' => $id, 'created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]);
                        $this->remember('cattle_receiving_item', (int) $legacyId, $itemIds[$legacyId]);
                    }
                } else {
                    $receivingIds[(int) $row['idreceive']] = -1;

                    foreach ($items as $legacyId => $item) {
                        $itemIds[$legacyId] = -1;
                    }
                }
            }

            foreach ($weighings as $row) {
                $number = trim((string) $row['weigh_no']);

                if (isset($existingWeighings[$number])) {
                    $stats['skippedWeighings']++;

                    if ($apply) {
                        $this->remember('cattle_weighing', (int) $row['idweigh'], (int) $existingWeighings[$number]);
                        $byItem = DB::table('cattle_weighing_items')->where('cattle_weighing_id', $existingWeighings[$number])->pluck('id', 'cattle_receiving_item_id');

                        foreach ($weighDetails->get($row['idweigh'], collect()) as $d) {
                            $receivingItem = $itemIds[(int) $d['idreceivedetail']] ?? null;

                            if ($receivingItem !== null && isset($byItem[$receivingItem])) {
                                $this->remember('cattle_weighing_item', (int) $d['idweighdetail'], (int) $byItem[$receivingItem]);
                            }
                        }
                    }

                    continue;
                }

                $receivingId = $receivingIds[(int) $row['idreceive']] ?? null;

                if ($receivingId === null) {
                    $conflicts[] = "{$number}: receive induk idreceive={$row['idreceive']} tidak ada di aplikasi, timbang dilewati.";

                    continue;
                }

                $note = trim((string) ($row['note'] ?? ''));
                $userId = (int) $row['createby'];

                if (! isset($userIds[$userId])) {
                    $note = trim($note.' | Pembuat legacy: iduser '.$userId, ' |');
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $items = [];

                foreach ($weighDetails->get($row['idweigh'], collect()) as $d) {
                    $itemId = $itemIds[(int) $d['idreceivedetail']] ?? null;

                    if ($itemId === null) {
                        $conflicts[] = "{$number}: baris timbang idreceivedetail={$d['idreceivedetail']} tidak punya baris receive, dilewati.";

                        continue;
                    }

                    $weight = (float) $d['weight'];
                    $items[(int) $d['idweighdetail']] = ['cattle_receiving_item_id' => $itemId, 'actual_weight' => $weight > 0 ? round($weight, 2) : null, 'notes' => trim((string) ($d['notes'] ?? '')) ?: null];
                    $stats['unweighed'] += $weight > 0 ? 0 : 1;
                    $stats['actualKg'] += $weight;
                }

                $stats['weighings']++;
                $stats['weighItems'] += count($items);

                if ($apply) {
                    $id = DB::table('cattle_weighings')->insertGetId([
                        'weighing_number' => $number, 'cattle_receiving_id' => $receivingId, 'weighing_date' => $row['weigh_date'], 'note' => trim($note, ' |') ?: null,
                        'created_by' => $userId, 'created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now(),
                    ]);

                    $this->remember('cattle_weighing', (int) $row['idweigh'], $id);

                    foreach ($items as $legacyId => $item) {
                        $this->remember('cattle_weighing_item', (int) $legacyId, DB::table('cattle_weighing_items')->insertGetId($item + ['cattle_weighing_id' => $id, 'created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]));
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

        $this->report($apply, $stats, $refused, $conflicts, $changed, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /** Mencatat penghubung id legacy -> id baru (hanya saat --apply). */
    private function remember(string $entity, int $legacyId, int $newId): void
    {
        DB::table('legacy_import_map')->insertOrIgnore(['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function report(bool $apply, array $stats, array $refused, array $conflicts, array $changed, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("cattle_receivings -- dibuat: {$stats['receipts']}, sudah ada (dilewati): {$stats['skippedReceipts']}; ekor: {$stats['items']}, berat awal total ".number_format($stats['kg'], 0, ',', '.').' kg');
        $this->line("cattle_weighings -- dibuat: {$stats['weighings']}, sudah ada (dilewati): {$stats['skippedWeighings']}; baris timbang: {$stats['weighItems']}, belum ditimbang (berat 0 -> kosong): {$stats['unweighed']}, berat timbang total ".number_format($stats['actualKg'], 0, ',', '.').' kg');
        $this->line("Konflik: ".count($conflicts).', DITOLAK: '.count($refused).". Pembuat diganti akun pengganti: {$stats['fallback']} dokumen.");
        $this->line('EARTAG kembar di legacy diberi akhiran tanggal terima (yang terbaru memakai aslinya): '.count($changed).' ekor. Contoh: '.implode(' | ', array_slice($changed, 0, 6)));

        foreach ($examples as [$row, $lines, $header, $items, $poKey]) {
            $this->line('');
            $this->line("Contoh receive idreceive={$row['idreceive']}:");
            $this->line("  LAMA  cattle_receive: idpo={$row['idpo']} receipt_date={$row['receipt_date']} doc_no=\"".($row['doc_no'] ?? '')."\" sv_ok={$row['sv_ok']} skkh_ok={$row['skkh_ok']} createby={$row['createby']}");

            foreach ($lines as $l) {
                $this->line("        cattle_receive_detail: eartag={$l['eartag']} weight={$l['weight']} class={$l['class']}");
            }

            $this->line("  BARU  cattle_receivings: receiving_number={$header['receiving_number']} po_induk={$poKey} supplier_id={$header['supplier_id']} receive_date={$header['receive_date']}");

            foreach ($items as $i) {
                $this->line("        item: cattle_class_id={$i['cattle_class_id']} eartag={$i['eartag']} initial_weight={$i['initial_weight']}");
            }
        }

        $this->line('');
        $this->line('Catatan: tabel susut legacy (cattle_loss_receive) tidak dibawa (padanannya dihitung dari receive vs timbang). Receive/timbang yang dihapus diabaikan.');

        foreach (array_slice($conflicts, 0, 8) as $line) {
            $this->line("  ! {$line}");
        }
        foreach (array_slice($refused, 0, 8) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
