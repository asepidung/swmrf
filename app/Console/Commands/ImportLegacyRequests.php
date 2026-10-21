<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Request material TERBUKA legacy (`request` + `requestdetail`) -> `material_requisitions` + `material_requisition_items`.
 *
 * Langkah pertama pola dokumen berjalan (urutan Ayah: request material -> PO -> GR). Dry-run bawaan dengan contoh pasangan
 * lama -> baru; `--apply` menulis; aman diulang (nomor dokumen yang sudah ada dilewati, tidak ditimpa: statusnya mungkin sudah
 * diproses di aplikasi); satu penolakan membatalkan semuanya.
 *
 * Terbuka = tidak dihapus (`is_deleted` bukan 0 = dihapus; 127 hasil MySQL memotong tanggal) dan status Request/Waiting/Ordering
 * (belum ada PO). Arti status legacy dari kode legacy: Request = dibuat, Waiting = diterima purchasing (TOP terisi),
 * Ordering = disetujui tetapi PO belum dibuat, PO Created, Partial (ada GR), Completed (PO ditutup).
 *
 * Pemasangan:
 *  - document_number = norequest apa adanya (awalan REQ-, tidak bentrok dengan nomor baru MR#yy);
 *  - supplier_id, material_id memakai nomor legacy (master diimpor ber-ID legacy); baris dengan bahan di luar material impor DILEWATI dan dilaporkan;
 *  - user_id = iduser bila akun itu ada (akun legacy diimpor ber-ID legacy), selain itu akun cadangan `--fallback-user` dan nama pemohon asli masuk catatan;
 *  - status: Request -> Requested; Waiting -> Pending Finance; Ordering -> Pending Finance (aplikasi baru membuat PO saat finance menyetujui, tidak ada status "disetujui menunggu PO");
 *  - total = jumlah subtotal baris yang dibawa + pajak (taxrp).
 */
class ImportLegacyRequests extends Command
{
    protected $signature = 'legacy:import-requests
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh belanja)}
                            {--fallback-user= : Username akun cadangan untuk pemohon yang tidak ada di aplikasi}
                            {--only-open : Hanya request yang masih terbuka (bawaan: seluruh riwayat yang tidak dihapus)}
                            {--cutoff=2026-09-01 : Request terbuka yang dibuat SEBELUM tanggal ini dibawa berstatus Rejected (dianggap ditinggalkan)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan request material terbuka legacy ke material_requisitions (dry-run bawaan)';

    public const OPEN_STATUSES = ['Request', 'Waiting', 'Ordering'];

    /** Request yang sudah menjadi PO (PO Created, Partial = ada GR, Completed = PO ditutup): di aplikasi baru semuanya 'PO Created'. */
    public const HISTORY_STATUSES = ['PO Created', 'Partial', 'Completed'];

    public const STATUS_MAP = [
        'Request' => 'Requested',
        'Waiting' => 'Pending Finance',
        'Ordering' => 'Pending Finance',
    ];

    public const NOTE_TAG = 'Impor legacy';

    /** Status request terbuka yang sudah terlalu lama (keputusan Ayah, 21 Oktober 2026: dibawa tetapi dibatalkan). */
    public const STALE_STATUS = 'Rejected';

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path || ! is_file($path)) {
            $this->error('--source wajib diisi dengan berkas snapshot SQL yang ada.');

            return self::FAILURE;
        }

        $fallbackName = $this->option('fallback-user');
        $fallback = $fallbackName ? User::withoutGlobalScopes()->where('username', $fallbackName)->first() : null;

        if ($fallbackName && ! $fallback) {
            $this->error("Akun cadangan \"{$fallbackName}\" tidak ditemukan.");

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');
        $details = collect(LegacySqlDump::parseTable($sql, 'requestdetail'))->groupBy('idrequest');
        $rows = collect(LegacySqlDump::parseTable($sql, 'request'))
            ->filter(fn ($r) => (int) $r['is_deleted'] === 0
                && in_array($r['stat'], $this->option('only-open') ? self::OPEN_STATUSES : [...self::OPEN_STATUSES, ...self::HISTORY_STATUSES], true))
            ->sortBy('idrequest')
            ->values();

        // Himpunan id yang ada, dimuat sekali (ratusan dokumen x puluhan baris).
        $supplierIds = Supplier::query()->pluck('id')->flip();
        $materialIds = Material::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $existing = DB::table('material_requisitions')->pluck('document_number')->flip();

        $created = $skipped = $linesIn = $linesOut = 0;
        $refused = $conflicts = $examples = [];
        $perStatus = [];
        $fallbackUsed = $staleCount = $merged = $linesSkipped = 0;

        $run = function () use ($rows, $details, $fallback, $apply, $supplierIds, $materialIds, $userIds, $existing, &$created, &$skipped, &$linesIn, &$linesOut, &$refused, &$conflicts, &$examples, &$perStatus, &$fallbackUsed, &$staleCount, &$merged, &$linesSkipped): void {
            foreach ($rows as $row) {
                $number = trim((string) $row['norequest']);

                if (isset($existing[$number])) {
                    $skipped++;

                    continue;
                }

                // Supplier yang digabung saat impor master (peta WAJIB dipakai): id lama -> id yang dipakai.
                $supplierId = ImportLegacyMaster::SUPPLIER_MERGES[(int) $row['idsupplier']] ?? (int) $row['idsupplier'];

                if (! isset($supplierIds[$supplierId])) {
                    $refused[] = "{$number}: supplier idsupplier={$row['idsupplier']} tidak ada -- DITOLAK.";

                    continue;
                }

                $userId = (int) $row['iduser'];
                $note = trim((string) ($row['note'] ?? ''));
                $note = ($note === '0') ? '' : $note;

                if (! isset($userIds[$userId])) {
                    if (! $fallback) {
                        $refused[] = "{$number}: pemohon iduser={$userId} tidak ada di aplikasi dan --fallback-user tidak diberikan -- DITOLAK.";

                        continue;
                    }

                    $note = trim($note.' | Pemohon legacy: iduser '.$userId);
                    $userId = $fallback->id;
                    $fallbackUsed++;
                }

                $items = [];
                $skippedHere = 0;
                foreach ($details->get($row['idrequest'], collect()) as $line) {
                    $linesIn++;

                    if (! isset($materialIds[(int) $line['idrawmate']])) {
                        $conflicts[] = "{$number}: baris bahan idrawmate={$line['idrawmate']} bukan material impor, dilewati.";
                        $linesSkipped++;
                        $skippedHere++;

                        continue;
                    }

                    $qty = (float) $line['qty'];
                    $price = (float) $line['price'];
                    $items[] = [
                        'material_id' => (int) $line['idrawmate'],
                        'qty' => $qty,
                        'price' => $price,
                        'note' => trim((string) ($line['notes'] ?? '')) ?: null,
                        'subtotal' => round($qty * $price, 2),
                    ];
                    $linesOut++;
                }

                $open = in_array($row['stat'], self::OPEN_STATUSES, true);

                // Request terbuka tanpa satu pun baris bahan impor tidak berguna dan dilewati; dokumen riwayat tetap dibawa
                // (nomor dan statusnya dibutuhkan PO sebagai induk), tanpa baris.
                if ($items === [] && $open) {
                    $conflicts[] = "{$number}: tidak ada baris bahan yang bisa dibawa, request dilewati.";

                    continue;
                }

                if ($skippedHere > 0) {
                    $note = trim($note." | {$skippedHere} baris bahan dilewati (bahan di luar material impor)", ' |');
                }

                $subtotal = array_sum(array_column($items, 'subtotal'));
                $tax = (float) $row['taxrp'];
                $status = self::STATUS_MAP[$row['stat']] ?? 'PO Created';
                $stale = $open && ($row['creatime'] ?: '9999') < $this->option('cutoff');
                $rejectNote = null;

                if ($stale) {
                    $status = self::STALE_STATUS;
                    $rejectNote = 'Impor legacy: request berstatus '.$row['stat'].' sejak '.substr((string) $row['creatime'], 0, 10).', tidak pernah menjadi PO (dibatalkan saat pindah data).';
                    $staleCount++;
                }

                if ($supplierId !== (int) $row['idsupplier']) {
                    $merged++;
                }
                $header = [
                    'document_number' => $number,
                    'user_id' => $userId,
                    'supplier_id' => $supplierId,
                    'reject_note' => $rejectNote,
                    'due_date' => $row['duedate'] ?: null,
                    'note' => trim($note.' | '.self::NOTE_TAG.' '.$row['stat'], ' |'),
                    'terms_of_payment' => (string) ((int) ($row['top'] ?? 0)),
                    'tax_type' => null,
                    'tax_amount' => $tax,
                    'total_amount' => round($subtotal + $tax, 2),
                    'status' => $status,
                ];

                $perStatus["{$row['stat']} -> {$status}"] = ($perStatus["{$row['stat']} -> {$status}"] ?? 0) + 1;

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $details->get($row['idrequest'], collect())->all(), $header, $items];
                }

                $created++;

                if ($apply) {
                    $id = DB::table('material_requisitions')->insertGetId($header + [
                        'created_at' => $row['creatime'] ?: now(),
                        'updated_at' => $row['creatime'] ?: now(),
                    ]);

                    foreach ($items as $item) {
                        DB::table('material_requisition_items')->insert($item + [
                            'material_requisition_id' => $id,
                            'created_at' => $row['creatime'] ?: now(),
                            'updated_at' => $row['creatime'] ?: now(),
                        ]);
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

        $this->report($apply, $created, $skipped, $linesIn, $linesOut, $refused, $conflicts, $perStatus, $fallbackUsed, $examples, $staleCount, $merged, $linesSkipped);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    private function report(bool $apply, int $created, int $skipped, int $linesIn, int $linesOut, array $refused, array $conflicts, array $perStatus, int $fallbackUsed, array $examples, int $staleCount, int $merged, int $linesSkipped): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("material_requisitions -- dibuat: {$created}, sudah ada (dilewati): {$skipped}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Baris bahan: {$linesOut} dibawa dari {$linesIn} ({$linesSkipped} dilewati karena bahan di luar material impor). Pemohon diganti akun cadangan: {$fallbackUsed} request.");
        $this->line('Status: '.collect($perStatus)->map(fn ($n, $k) => "{$k}={$n}")->implode(', '));
        $this->line("Dibatalkan karena terlalu lama (dibuat sebelum --cutoff): {$staleCount}. Supplier lewat peta penggabungan: {$merged}.");

        foreach ($examples as [$row, $lines, $header, $items]) {
            $this->line('');
            $this->line("Contoh {$row['norequest']}:");
            $this->line("  LAMA  request: norequest={$row['norequest']} idsupplier={$row['idsupplier']} duedate={$row['duedate']} top=".($row['top'] ?? '').' tax='.($row['tax'] ?? '')." taxrp={$row['taxrp']} xamount={$row['xamount']} stat={$row['stat']} iduser={$row['iduser']} note=\"".($row['note'] ?? '').'"');

            foreach (array_slice($lines, 0, 2) as $line) {
                $this->line("        requestdetail: idrawmate={$line['idrawmate']} qty={$line['qty']} price={$line['price']}");
            }

            $this->line("  BARU  material_requisitions: document_number={$header['document_number']} user_id={$header['user_id']} supplier_id={$header['supplier_id']} due_date={$header['due_date']} terms_of_payment={$header['terms_of_payment']} tax_amount={$header['tax_amount']} total_amount={$header['total_amount']} status={$header['status']} note=\"{$header['note']}\"");

            foreach (array_slice($items, 0, 2) as $item) {
                $this->line("        item: material_id={$item['material_id']} qty={$item['qty']} price={$item['price']} subtotal={$item['subtotal']}");
            }
        }

        $this->line('');
        $this->line('Catatan: nomor dokumen yang sudah ada tidak ditimpa (statusnya mungkin sudah diproses di aplikasi).');

        foreach (array_slice($conflicts, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
        foreach (array_slice($refused, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
