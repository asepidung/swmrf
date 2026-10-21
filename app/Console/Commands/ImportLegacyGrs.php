<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * GR material legacy (`grraw` + `grrawdetail`) -> `goods_receipt_materials` + items, SELURUH riwayat yang tidak dihapus.
 * Langkah 3 dari request -> PO -> GR (keputusan Ayah, 21 Oktober 2026). Dry-run bawaan; `--apply` menulis; aman diulang
 * (nomor GR yang sudah ada dilewati); satu penolakan membatalkan semuanya.
 *
 * Ditulis lewat DB::table (tanpa event model): TIDAK menambah stok material dan tidak membuat hutang. Stok material (`stockraw`)
 * adalah pekerjaan terpisah.
 *
 * Pemasangan:
 *  - gr_number = grnumber apa adanya (kembar diberi akhiran -2, -3, ...);
 *  - purchase_material_id = PO menurut nomor PO (aturan akhiran yang sama dengan `legacy:import-pos`); GR tanpa PO di aplikasi dilewati dan dilaporkan;
 *  - supplier_id lewat peta penggabungan; receive_date = receivedate; sj_number = suppcode (kode/surat jalan supplier);
 *  - created_by = idusers bila akun ada, selain itu `--fallback-user`; is_locked = 1 (riwayat tidak untuk disunting);
 *  - item: qty_received = qty; price = harga baris PO untuk bahan itu (GR legacy tidak mencatat harga); baris bahan di luar material impor dilewati dan dicatat di catatan.
 */
class ImportLegacyGrs extends Command
{
    protected $signature = 'legacy:import-grs
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh belanja)}
                            {--fallback-user= : Username akun pengganti untuk pembuat GR yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat GR material legacy ke goods_receipt_materials (dry-run bawaan)';

    public const NOTE_TAG = 'Impor legacy';

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

        $pos = $table('po')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idpo');
        $poNumber = $this->numbers($pos, 'idpo', 'nopo');
        $purchaseIds = DB::table('purchase_materials')->pluck('id', 'po_number');
        $poPrices = $table('podetail')->groupBy('idpo')->map(fn ($g) => $g->mapWithKeys(fn ($l) => [(int) $l['idrawmate'] => (float) $l['price']]));
        $names = $table('rawmate')->pluck('nmrawmate', 'idrawmate');
        $details = $table('grrawdetail')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->groupBy('idgr');
        $supplierIds = Supplier::query()->pluck('id')->flip();
        $materialIds = Material::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $existing = DB::table('goods_receipt_materials')->pluck('gr_number')->flip();
        $rows = $table('grraw')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idgr')->values();

        $stats = ['created' => 0, 'skipped' => 0, 'linesIn' => 0, 'linesOut' => 0, 'linesSkipped' => 0, 'merged' => 0, 'duplicates' => 0, 'fallback' => 0, 'noItems' => 0];
        $refused = $conflicts = $examples = [];
        $seen = [];

        $run = function () use ($rows, $details, $poNumber, $purchaseIds, $poPrices, $names, $supplierIds, $materialIds, $userIds, $existing, $fallback, $apply, &$stats, &$refused, &$conflicts, &$examples, &$seen): void {
            foreach ($rows as $row) {
                $number = trim((string) $row['grnumber']);
                $note = trim((string) ($row['note'] ?? ''));
                $note = ($note === '0') ? '' : $note;

                $seen[$number] = ($seen[$number] ?? 0) + 1;

                if ($seen[$number] > 1) {
                    $note = trim($note." | Nomor kembar di legacy (idgr={$row['idgr']}): nomor asli {$number}", ' |');
                    $number .= '-'.$seen[$number];
                    $stats['duplicates']++;
                }

                if (isset($existing[$number])) {
                    $stats['skipped']++;

                    continue;
                }

                $parentNumber = $poNumber[(int) $row['idpo']] ?? null;
                $purchaseId = $parentNumber !== null ? ($purchaseIds[$parentNumber] ?? null) : null;

                if ($purchaseId === null) {
                    $conflicts[] = "{$number}: PO induk idpo={$row['idpo']} tidak ada di aplikasi, GR dilewati.";

                    continue;
                }

                $supplierId = ImportLegacyMaster::SUPPLIER_MERGES[(int) $row['idsupplier']] ?? (int) $row['idsupplier'];

                if (! isset($supplierIds[$supplierId])) {
                    $refused[] = "{$number}: supplier idsupplier={$row['idsupplier']} tidak ada -- DITOLAK.";

                    continue;
                }

                if ($supplierId !== (int) $row['idsupplier']) {
                    $stats['merged']++;
                }

                $userId = (int) $row['idusers'];

                if (! isset($userIds[$userId])) {
                    $note = trim($note.' | Pembuat legacy: idusers '.$userId, ' |');
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $items = [];
                $skippedText = [];

                foreach ($details->get($row['idgr'], collect()) as $line) {
                    $stats['linesIn']++;

                    if (! isset($materialIds[(int) $line['idrawmate']])) {
                        $stats['linesSkipped']++;
                        $skippedText[] = trim(($names[$line['idrawmate']] ?? 'bahan '.$line['idrawmate']).' '.(float) $line['qty']);
                        $conflicts[] = "{$number}: baris bahan idrawmate={$line['idrawmate']} bukan material impor, dilewati.";

                        continue;
                    }

                    $qty = (float) $line['qty'];
                    $price = (float) ($poPrices[(int) $row['idpo']][(int) $line['idrawmate']] ?? 0);
                    $items[] = [
                        'material_id' => (int) $line['idrawmate'],
                        'qty_received' => $qty,
                        'price' => $price,
                        'subtotal' => round($qty * $price, 2),
                    ];
                    $stats['linesOut']++;
                }

                $stats['noItems'] += $items === [] ? 1 : 0;

                if ($skippedText !== []) {
                    $note .= ' | '.count($skippedText).' baris bahan dilewati (bahan di luar material impor): '.implode('; ', $skippedText);
                }

                $header = [
                    'gr_number' => $number,
                    'purchase_material_id' => $purchaseId,
                    'supplier_id' => $supplierId,
                    'receive_date' => $row['receivedate'] ?: substr((string) $row['creatime'], 0, 10),
                    'sj_number' => trim((string) ($row['suppcode'] ?? '')) ?: null,
                    'note' => trim($note.' | '.self::NOTE_TAG, ' |'),
                    'is_locked' => 1,
                    'created_by' => $userId,
                ];

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $details->get($row['idgr'], collect())->all(), $header, $items, $parentNumber];
                }

                $stats['created']++;

                if ($apply) {
                    $id = DB::table('goods_receipt_materials')->insertGetId($header + ['created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]);

                    foreach ($items as $item) {
                        DB::table('goods_receipt_material_items')->insert($item + ['goods_receipt_material_id' => $id, 'created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]);
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

        $this->report($apply, $stats, $refused, $conflicts, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * id legacy -> nomor dokumen di aplikasi (nomor kembar memakai akhiran -2, -3, ... menurut urutan id), aturan yang sama
     * dengan `legacy:import-pos`.
     *
     * @return array<int, string>
     */
    private function numbers($rows, string $idColumn, string $numberColumn): array
    {
        $seen = [];
        $map = [];

        foreach ($rows as $row) {
            $number = trim((string) $row[$numberColumn]);
            $seen[$number] = ($seen[$number] ?? 0) + 1;
            $map[(int) $row[$idColumn]] = $seen[$number] > 1 ? $number.'-'.$seen[$number] : $number;
        }

        return $map;
    }

    private function report(bool $apply, array $stats, array $refused, array $conflicts, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("goods_receipt_materials -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Baris bahan: {$stats['linesOut']} dibawa dari {$stats['linesIn']} ({$stats['linesSkipped']} dilewati karena bahan di luar material impor). GR tanpa satu pun baris: {$stats['noItems']}.");
        $this->line("Supplier lewat peta penggabungan: {$stats['merged']}. Pembuat diganti akun pengganti: {$stats['fallback']}. Nomor kembar diberi akhiran: {$stats['duplicates']}.");

        foreach ($examples as [$row, $lines, $header, $items, $parentNumber]) {
            $this->line('');
            $this->line("Contoh {$row['grnumber']}:");
            $this->line("  LAMA  grraw: grnumber={$row['grnumber']} idpo={$row['idpo']} receivedate={$row['receivedate']} idsupplier={$row['idsupplier']} suppcode=\"".($row['suppcode'] ?? '')."\" idusers={$row['idusers']}");

            foreach (array_slice($lines, 0, 2) as $line) {
                $this->line("        grrawdetail: idrawmate={$line['idrawmate']} orderqty={$line['orderqty']} qty={$line['qty']}");
            }

            $this->line("  BARU  goods_receipt_materials: gr_number={$header['gr_number']} po_induk={$parentNumber} supplier_id={$header['supplier_id']} receive_date={$header['receive_date']} sj_number=".($header['sj_number'] ?? '(kosong)').' is_locked=1');

            foreach (array_slice($items, 0, 2) as $item) {
                $this->line("        item: material_id={$item['material_id']} qty_received={$item['qty_received']} price={$item['price']} subtotal={$item['subtotal']}");
            }
        }

        $this->line('');
        $this->line('Catatan: tidak menambah stok material dan tidak membuat hutang (ditulis tanpa event model). Stok material (stockraw) dikerjakan terpisah.');

        foreach (array_slice($conflicts, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
        foreach (array_slice($refused, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
