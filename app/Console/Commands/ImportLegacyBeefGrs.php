<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * GR DAGING legacy (`grbeef` + `grbeefdetail`, satu baris detail = satu barcode) -> `goods_receipt_products` + `goods_receipt_product_items`,
 * SELURUH riwayat yang tidak dihapus. Langkah 3 dari request beef -> PO -> GR (keputusan Ayah, 21 Oktober 2026).
 * Dry-run bawaan; `--apply` menulis; aman diulang (nomor GR yang sudah ada dilewati); satu penolakan membatalkan semuanya.
 *
 * Ditulis lewat DB::table (tanpa event model): TIDAK membuat stok, pergerakan stok, atau hutang. Barcode yang masih ada di gudang sudah
 * masuk lewat `legacy:import-stock`; GR ini adalah dokumen penerimaannya.
 *
 * Arti dari kode legacy (via Hafizh): baris detail `is_deleted` = 1 berarti baris itu dihapus satu per satu dari GR (salah scan/barcode ditolak) dan barcode-nya
 * dihapus permanen dari stok -> dianggap tidak pernah diterima, DIABAIKAN. GR yang dihapus utuh juga diabaikan. Satu GR beef menutup PO-nya (tanpa penerimaan sebagian).
 *
 * Pemasangan:
 *  - gr_number = grnumber apa adanya (kembar diberi akhiran -2, ...); purchase_product_id = PO daging menurut nomor PO (aturan akhiran sama dengan `legacy:import-pos --beef`);
 *  - supplier lewat peta penggabungan; sj_number = suppcode; created_by = idusers bila ada, selain itu `--fallback-user`; is_locked = 1;
 *  - item: barcode = kdbarcode (apa adanya); product_id = idbarang; grade_id lewat `LegacyGradeMap`; weight = qty; qty_pcs = pcs (kosong -> 0); pack_date = pod;
 *    batch_no = nomor GR (sama dengan stok awal); origin dari digit awal barcode legacy (1 BONING, 2 TRD-LC, 5 TRD-IM); price = harga PO untuk produk itu per kg (kosong -> 0).
 */
class ImportLegacyBeefGrs extends Command
{
    protected $signature = 'legacy:import-beef-grs
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh belanjabeef)}
                            {--fallback-user= : Username akun pengganti untuk pembuat GR yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat GR daging legacy ke goods_receipt_products (dry-run bawaan)';

    /** Digit awal barcode legacy -> asal di aplikasi baru (GR membeli barang: 5 = import dagang, bukan repack). */
    public const ORIGINS = ['1' => 'BONING', '2' => 'TRD-LC', '5' => 'TRD-IM'];

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

        $problems = LegacyGradeMap::appSideProblems();

        if ($problems !== []) {
            $this->error('Terjemahan grade belum bisa dipakai: '.implode('; ', $problems));

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');
        $table = fn (string $name) => collect(LegacySqlDump::parseTable($sql, $name));

        $pos = $table('pobeef')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idpo');
        $poNumber = $this->numbers($pos, 'idpo', 'nopo');
        $purchaseIds = DB::table('purchase_products')->pluck('id', 'po_number');
        $poPrices = $table('pobeefdetail')->groupBy('idpo')->map(fn ($g) => $g->mapWithKeys(fn ($l) => [(int) $l['idbarang'] => (float) $l['price']]));
        $details = $table('grbeefdetail')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->groupBy('idgr');
        $supplierIds = Supplier::query()->pluck('id')->flip();
        $productIds = Product::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $existing = DB::table('goods_receipt_products')->pluck('gr_number')->flip();
        $rows = $table('grbeef')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idgr')->values();

        $stats = ['created' => 0, 'skipped' => 0, 'linesIn' => 0, 'linesOut' => 0, 'noPrice' => 0, 'merged' => 0, 'duplicates' => 0, 'fallback' => 0, 'noItems' => 0, 'kg' => 0.0];
        $refused = $conflicts = $examples = $perOrigin = [];
        $seen = [];

        $run = function () use ($rows, $details, $poNumber, $purchaseIds, $poPrices, $supplierIds, $productIds, $userIds, $existing, $fallback, $apply, &$stats, &$refused, &$conflicts, &$examples, &$perOrigin, &$seen): void {
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

                foreach ($details->get($row['idgr'], collect()) as $line) {
                    $stats['linesIn']++;
                    $barcode = trim((string) $line['kdbarcode']);
                    $legacyGrade = (int) $line['idgrade'];

                    if (! isset($productIds[(int) $line['idbarang']])) {
                        $conflicts[] = "{$number}: {$barcode} produk idbarang={$line['idbarang']} tidak diimpor, baris dilewati.";

                        continue;
                    }

                    if (! LegacyGradeMap::knows($legacyGrade)) {
                        $refused[] = "{$number}: {$barcode} grade legacy {$legacyGrade} di luar tabel terjemahan -- DITOLAK.";

                        continue;
                    }

                    $digit = substr($barcode, 0, 1);
                    $origin = self::ORIGINS[$digit] ?? ImportLegacyStock::ORIGIN_NAMES[$digit] ?? null;

                    if ($origin === null) {
                        $refused[] = "{$number}: {$barcode} kode asal \"{$digit}\" tidak dikenal -- DITOLAK.";

                        continue;
                    }

                    $weight = round((float) $line['qty'], 2);
                    $price = $poPrices[(int) $row['idpo']][(int) $line['idbarang']] ?? null;
                    $stats['noPrice'] += $price === null ? 1 : 0;
                    $pcs = trim((string) ($line['pcs'] ?? ''));

                    $items[] = [
                        'barcode' => $barcode,
                        'batch_no' => $number,
                        'product_id' => (int) $line['idbarang'],
                        'grade_id' => LegacyGradeMap::resolve($legacyGrade)['grade_id'],
                        'weight' => $weight,
                        'qty_pcs' => $pcs === '' ? 0 : (int) $pcs,
                        'ph_level' => null,
                        'pack_date' => $line['pod'] ?: substr((string) $row['creatime'], 0, 10),
                        'origin' => $origin,
                        'price' => (float) ($price ?? 0),
                        'subtotal' => round($weight * (float) ($price ?? 0), 2),
                    ];
                    $stats['linesOut']++;
                    $stats['kg'] += $weight;
                    $perOrigin[$origin] = ($perOrigin[$origin] ?? 0) + 1;
                }

                $stats['noItems'] += $items === [] ? 1 : 0;

                $header = [
                    'gr_number' => $number,
                    'purchase_product_id' => $purchaseId,
                    'supplier_id' => $supplierId,
                    'receive_date' => $row['receivedate'] ?: substr((string) $row['creatime'], 0, 10),
                    'sj_number' => trim((string) ($row['suppcode'] ?? '')) ?: null,
                    'note' => trim($note, ' |') ?: null,
                    'is_locked' => 1,
                    'created_by' => $userId,
                ];

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $details->get($row['idgr'], collect())->take(2)->all(), $header, array_slice($items, 0, 2), $parentNumber];
                }

                $stats['created']++;

                if ($apply) {
                    $id = DB::table('goods_receipt_products')->insertGetId($header + ['created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]);

                    foreach (array_chunk($items, 200) as $chunk) {
                        DB::table('goods_receipt_product_items')->insert(array_map(fn ($i) => $i + ['goods_receipt_product_id' => $id, 'created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()], $chunk));
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

        $this->report($apply, $stats, $refused, $conflicts, $perOrigin, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, string> id legacy -> nomor di aplikasi (kembar diberi akhiran -2, -3, ...). */
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

    private function report(bool $apply, array $stats, array $refused, array $conflicts, array $perOrigin, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("goods_receipt_products -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Barcode (baris): {$stats['linesOut']} dibawa dari {$stats['linesIn']} baris hidup, total ".number_format($stats['kg'], 2, ',', '.')." kg. GR tanpa satu pun baris: {$stats['noItems']}. Baris tanpa harga di PO: {$stats['noPrice']}.");
        $this->line('Per asal: '.collect($perOrigin)->map(fn ($n, $o) => "{$o}={$n}")->implode(', '));
        $this->line("Supplier lewat peta penggabungan: {$stats['merged']}. Pembuat diganti akun pengganti: {$stats['fallback']}. Nomor kembar diberi akhiran: {$stats['duplicates']}.");

        foreach ($examples as [$row, $lines, $header, $items, $parentNumber]) {
            $this->line('');
            $this->line("Contoh {$row['grnumber']}:");
            $this->line("  LAMA  grbeef: grnumber={$row['grnumber']} idpo={$row['idpo']} receivedate={$row['receivedate']} idsupplier={$row['idsupplier']} suppcode=\"".($row['suppcode'] ?? '')."\" idusers={$row['idusers']}");

            foreach ($lines as $line) {
                $this->line("        grbeefdetail: kdbarcode={$line['kdbarcode']} idbarang={$line['idbarang']} idgrade={$line['idgrade']} qty={$line['qty']} pcs=".($line['pcs'] === '' ? '(kosong)' : $line['pcs'])." pod={$line['pod']}");
            }

            $this->line("  BARU  goods_receipt_products: gr_number={$header['gr_number']} po_induk={$parentNumber} supplier_id={$header['supplier_id']} receive_date={$header['receive_date']} is_locked=1");

            foreach ($items as $item) {
                $this->line("        item: barcode={$item['barcode']} product_id={$item['product_id']} grade_id={$item['grade_id']} weight={$item['weight']} qty_pcs={$item['qty_pcs']} pack_date={$item['pack_date']} batch={$item['batch_no']} origin={$item['origin']} price={$item['price']} subtotal={$item['subtotal']}");
            }
        }

        $this->line('');
        $this->line('Catatan: tidak membuat stok, pergerakan stok, atau hutang. Barcode yang masih di gudang sudah masuk lewat legacy:import-stock. Baris detail yang dihapus di legacy diabaikan.');

        foreach (array_slice($conflicts, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
        foreach (array_slice($refused, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
