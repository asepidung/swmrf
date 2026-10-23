<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PO material legacy (`po` + `podetail`) -> `purchase_materials` + `purchase_material_items`, SELURUH riwayat yang tidak dihapus
 * (keputusan Ayah, 21 Oktober 2026). Langkah 2 dari request -> PO -> GR. Dry-run bawaan; `--apply` menulis; aman diulang
 * (nomor PO yang sudah ada dilewati); satu penolakan membatalkan semuanya.
 *
 * Arti `po.stat` (dari kode legacy, via Hafizh): 0 = terbuka (belum ada GR), 3 = sebagian (ada GR, belum ditutup; bisa sudah diterima penuh),
 * 1 = ditutup lewat "Close PO" (walau kurang), 2 = batal. `is_deleted` bukan 0 = dihapus.
 *
 * Pemasangan:
 *  - po_number = nopo apa adanya (nomor kembar diberi akhiran -2, -3, ...);
 *  - material_requisition_id = request induk menurut nomor request (yang kembar memakai aturan akhiran yang sama dengan `legacy:import-requests`); PO tanpa induk dilewati dan dilaporkan;
 *  - supplier_id lewat peta penggabungan supplier; approved_by = `--approver` (legacy tidak mencatat penyetuju PO);
 *  - status: 2 -> canceled; 1 -> completed; 3 -> completed bila qty GR >= qty PO, selain itu partial; 0 -> pending, tetapi canceled bila dibuat sebelum `--cutoff` (dianggap ditinggalkan);
 *  - po_date = tanggal creatime; total = jumlah (qty x harga) baris yang dibawa + PPN 11% bila PO itu ber-PPN (dibaca dari xamount; kolom taxrp legacy tidak dipakai karena salah hitung);
 *  - baris bahan di luar material impor dilewati dan dicatat sebagai teks di catatan; TOP, jatuh tempo, dan PPN legacy juga dicatat di catatan (tabel baru tidak punya kolomnya).
 */
class ImportLegacyPos extends Command
{
    protected $signature = 'legacy:import-pos
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh belanja)}
                            {--approver= : Username akun yang dicatat sebagai penyetuju PO impor}
                            {--beef : PO DAGING (pobeef -> purchase_products); bawaan: PO material}
                            {--cutoff=2026-09-01 : PO terbuka (stat 0) yang dibuat SEBELUM tanggal ini dibawa berstatus canceled}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat PO material legacy ke purchase_materials (dry-run bawaan)';

    public const NOTE_TAG = 'Impor legacy';

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path || ! is_file($path)) {
            $this->error('--source wajib diisi dengan berkas snapshot SQL yang ada.');

            return self::FAILURE;
        }

        $approver = $this->option('approver') ? User::withoutGlobalScopes()->where('username', $this->option('approver'))->first() : null;

        if (! $approver) {
            $this->error('--approver wajib diisi dengan username yang ada (penyetuju PO impor).');

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');
        $table = fn (string $name) => collect(LegacySqlDump::parseTable($sql, $name));

        // PO DAGING (--beef): tabel bersaudara; tidak ada penerimaan sebagian (satu GR menutup PO): 0 terbuka, 1 sudah diterima, 2 batal.
        $beef = (bool) $this->option('beef');
        $cfg = $beef
            ? ['req' => 'requestbeef', 'po' => 'pobeef', 'det' => 'pobeefdetail', 'col' => 'idbarang', 'parent' => 'product_requisitions', 'parentFk' => 'product_requisition_id', 'target' => 'purchase_products', 'items' => 'purchase_product_items', 'fk' => 'purchase_product_id', 'itemCol' => 'product_id']
            : ['req' => 'request', 'po' => 'po', 'det' => 'podetail', 'col' => 'idrawmate', 'parent' => 'material_requisitions', 'parentFk' => 'material_requisition_id', 'target' => 'purchase_materials', 'items' => 'purchase_material_items', 'fk' => 'purchase_material_id', 'itemCol' => 'material_id'];

        $requests = $table($cfg['req'])->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idrequest');
        $requestNumber = $this->requestNumbers($requests);
        $requisitions = DB::table($cfg['parent'])->pluck('id', 'document_number');

        $details = $table($cfg['det'])->groupBy('idpo');
        $grs = $beef ? collect() : $table('grraw')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->groupBy('idpo');
        $grLines = $beef ? collect() : $table('grrawdetail')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->groupBy('idgr');
        $names = $beef ? $table('barang')->pluck('nmbarang', 'idbarang') : $table('rawmate')->pluck('nmrawmate', 'idrawmate');
        $supplierIds = Supplier::query()->pluck('id')->flip();
        $materialIds = ($beef ? \App\Models\Product::query() : Material::query())->pluck('id')->flip();
        $existing = DB::table($cfg['target'])->pluck('po_number')->flip();

        $rows = $table($cfg['po'])->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idpo')->values();

        $stats = ['created' => 0, 'skipped' => 0, 'linesIn' => 0, 'linesOut' => 0, 'linesSkipped' => 0, 'merged' => 0, 'duplicates' => 0, 'stale' => 0, 'taxGuessed' => 0];
        $refused = $conflicts = $examples = $perStatus = [];
        $seen = [];

        $run = function () use ($rows, $details, $grs, $grLines, $cfg, $beef, $names, $supplierIds, $materialIds, $existing, $requestNumber, $requisitions, $approver, $apply, &$stats, &$refused, &$conflicts, &$examples, &$perStatus, &$seen): void {
            foreach ($rows as $row) {
                $number = trim((string) $row['nopo']);
                $note = trim((string) ($row['note'] ?? ''));
                $note = ($note === '0') ? '' : $note;

                $seen[$number] = ($seen[$number] ?? 0) + 1;

                if ($seen[$number] > 1) {
                    $note = trim($note." | Nomor kembar di legacy (idpo={$row['idpo']}): nomor asli {$number}", ' |');
                    $number .= '-'.$seen[$number];
                    $stats['duplicates']++;
                }

                if (isset($existing[$number])) {
                    $stats['skipped']++;

                    continue;
                }

                $parentNumber = $requestNumber[(int) $row['idrequest']] ?? null;
                $parentId = $parentNumber !== null ? ($requisitions[$parentNumber] ?? null) : null;

                if ($parentId === null) {
                    $conflicts[] = "{$number}: request induk idrequest={$row['idrequest']} tidak ada di aplikasi, PO dilewati.";

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

                $items = [];
                $skippedText = [];

                foreach ($details->get($row['idpo'], collect()) as $line) {
                    $stats['linesIn']++;

                    if (! isset($materialIds[(int) $line[$cfg['col']]])) {
                        $stats['linesSkipped']++;
                        $skippedText[] = trim(($names[$line[$cfg['col']]] ?? 'bahan '.$line[$cfg['col']]).' '.(float) $line['qty'].' x '.(float) $line['price']);
                        $conflicts[] = "{$number}: baris bahan {$cfg['col']}={$line[$cfg['col']]} bukan material/produk impor, dilewati.";

                        continue;
                    }

                    $qty = (float) $line['qty'];
                    $price = (float) $line['price'];
                    $items[] = [
                        $cfg['itemCol'] => (int) $line[$cfg['col']],
                        'qty' => $qty,
                        'price' => $price,
                        'subtotal' => round($qty * $price, 2),
                    ];
                    $stats['linesOut']++;
                }

                // Status baru dari status legacy (+ jumlah GR untuk stat 3).
                $stat = (string) $row['stat'];
                $ordered = $details->get($row['idpo'], collect())->sum(fn ($l) => (float) $l['qty']);
                $received = $grs->get($row['idpo'], collect())->sum(fn ($g) => $grLines->get($g['idgr'], collect())->sum(fn ($l) => (float) $l['qty']));
                $status = match ($stat) {
                    '2' => 'canceled',
                    '1' => 'completed',
                    '3' => $received + 0.0001 >= $ordered ? 'completed' : 'partial',
                    default => 'pending',
                };

                if ($stat === '0' && ($row['creatime'] ?: '9999') < $this->option('cutoff')) {
                    $status = 'canceled';
                    $note = trim($note.' | Impor legacy: PO terbuka sejak '.substr((string) $row['creatime'], 0, 10).', tidak pernah diterima (dibatalkan saat pindah data)', ' |');
                    $stats['stale']++;
                }

                $terms = 'TOP '.(int) ($row['top'] ?? 0).' hari; jatuh tempo '.($row['duedate'] ?: '-').'; PPN '.(float) ($row['tax'] ?? 0).'%';
                $note = trim($note.' | '.$terms, ' |');

                if ($skippedText !== []) {
                    $note .= ' | '.count($skippedText).' baris bahan dilewati (bahan di luar material impor): '.implode('; ', $skippedText);
                }

                // Total = jumlah (qty x harga) baris yang dibawa + PPN. Kolom `taxrp` legacy TIDAK dipakai: ia salah hitung (PPN 11% dikenakan
                // lagi di atas total yang sudah ber-PPN, jadi 12,21%) dan membuat total PO hasil impor lebih besar dari total legacy.
                // Ada atau tidaknya PPN dibaca dari `xamount` legacy: sama dengan jumlah baris x 1,11 = ber-PPN 11%; sama dengan jumlah baris = tanpa PPN;
                // selain itu (baris diubah sesudah PO terbit) dipakai kolom `tax` (persen) legacy.
                $subtotal = array_sum(array_column($items, 'subtotal'));
                $legacyLines = $details->get($row['idpo'], collect())->sum(fn ($l) => (float) $l['qty'] * (float) $l['price']);
                $legacyTotal = (float) ($row['xamount'] ?? 0);

                if ($legacyLines > 0 && abs($legacyTotal - $legacyLines * 1.11) <= 2) {
                    $rate = 0.11;
                } elseif ($legacyLines > 0 && abs($legacyTotal - $legacyLines) <= 2) {
                    $rate = 0.0;
                } else {
                    $rate = (float) ($row['tax'] ?? 0) > 0 ? 0.11 : 0.0;
                    $stats['taxGuessed'] += $legacyLines > 0 ? 1 : 0;
                }

                $tax = round($subtotal * $rate, 2);
                $header = [
                    'po_number' => $number,
                    $cfg['parentFk'] => $parentId,
                    'supplier_id' => $supplierId,
                    'approved_by' => $approver->id,
                    'po_date' => substr((string) $row['creatime'], 0, 10),
                    'total_amount' => round($subtotal + $tax, 2),
                    'note' => $note,
                    'status' => $status,
                ];

                $perStatus["stat {$stat} -> {$status}"] = ($perStatus["stat {$stat} -> {$status}"] ?? 0) + 1;

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $details->get($row['idpo'], collect())->all(), $header, $items, $parentNumber];
                }

                $stats['created']++;

                if ($apply) {
                    $id = DB::table($cfg['target'])->insertGetId($header + ['created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]);

                    foreach ($items as $item) {
                        DB::table($cfg['items'])->insert($item + [$cfg['fk'] => $id, 'created_at' => $row['creatime'] ?: now(), 'updated_at' => $row['creatime'] ?: now()]);
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

        $this->report($cfg, $apply, $stats, $refused, $conflicts, $perStatus, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * idrequest -> nomor dokumen request di aplikasi (aturan yang sama dengan `legacy:import-requests`: nomor kembar
     * memakai akhiran -2, -3, ... menurut urutan idrequest).
     *
     * @return array<int, string>
     */
    private function requestNumbers($requests): array
    {
        $seen = [];
        $map = [];

        foreach ($requests as $row) {
            $number = trim((string) $row['norequest']);
            $seen[$number] = ($seen[$number] ?? 0) + 1;
            $map[(int) $row['idrequest']] = $seen[$number] > 1 ? $number.'-'.$seen[$number] : $number;
        }

        return $map;
    }

    private function report(array $cfg, bool $apply, array $stats, array $refused, array $conflicts, array $perStatus, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("{$cfg['target']} -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Baris bahan: {$stats['linesOut']} dibawa dari {$stats['linesIn']} ({$stats['linesSkipped']} dilewati karena bahan di luar material impor).");
        $this->line('Status: '.collect($perStatus)->map(fn ($n, $k) => "{$k}={$n}")->implode(', '));
        $this->line("PO terbuka lama dibatalkan (sebelum --cutoff): {$stats['stale']}. Supplier lewat peta penggabungan: {$stats['merged']}. Nomor kembar diberi akhiran: {$stats['duplicates']}.");

        foreach ($examples as [$row, $lines, $header, $items, $parentNumber]) {
            $this->line('');
            $this->line("Contoh {$row['nopo']}:");
            $this->line("  LAMA  po: nopo={$row['nopo']} idrequest={$row['idrequest']} idsupplier={$row['idsupplier']} creatime={$row['creatime']} stat={$row['stat']} top=".($row['top'] ?? '').' tax='.($row['tax'] ?? '')." taxrp={$row['taxrp']} xamount={$row['xamount']}");

            foreach (array_slice($lines, 0, 2) as $line) {
                $this->line("        {$cfg['det']}: {$cfg['col']}={$line[$cfg['col']]} qty={$line['qty']} price={$line['price']} subtotal={$line['subtotal']}");
            }

            $this->line("  BARU  {$cfg['target']}: po_number={$header['po_number']} request_induk={$parentNumber} supplier_id={$header['supplier_id']} po_date={$header['po_date']} total_amount={$header['total_amount']} status={$header['status']}");
            $this->line("        catatan: {$header['note']}");

            foreach (array_slice($items, 0, 2) as $item) {
                $this->line("        item: {$cfg['itemCol']}={$item[$cfg['itemCol']]} qty={$item['qty']} price={$item['price']} subtotal={$item['subtotal']}");
            }
        }

        $this->line('');
        $this->line('Catatan: nomor PO yang sudah ada tidak ditimpa. GR dibawa di langkah berikutnya.');

        foreach (array_slice($conflicts, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
        foreach (array_slice($refused, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
