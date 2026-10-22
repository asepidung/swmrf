<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RETUR PENJUALAN legacy (`returjual` + `returjualdetail`, satu baris detail = satu barcode yang diretur) -> `sales_returns` + `sales_return_items`, SELURUH riwayat
 * yang tidak dihapus (keputusan Ayah, 22 Oktober 2026). Dry-run bawaan; `--apply` menulis; aman diulang (nomor retur yang sudah ada dilewati); satu penolakan membatalkan
 * semuanya. Ditulis lewat DB::table: TIDAK menambah stok, tidak mengurangi tagihan, tidak membuat tugas atau notifikasi.
 *
 * Pemasangan:
 *  - return_number = returnnumber (nomor legacy; kembar diberi -2); return_date = returdate; customer_id lewat PETA PENGGABUNGAN pelanggan (butuh `--master`); note = note ("-" = kosong);
 *    status = Approved (retur legacy langsung masuk stok, tidak mengenal persetujuan); created_by = idusers bila akun ada, selain itu `--fallback-user`; created_at = creatime;
 *  - hubungan ke DO/invoice/rencana retur TIDAK dibuat (legacy tidak menyimpannya); credit_amount 0; harga baris kosong;
 *  - item: barcode = kdbarcode apa adanya; product_id = idbarang; gudang + grade lewat `LegacyGradeMap` (grade kosong -> J01); weight = qty; qty_pcs = pcs (kosong -> 0);
 *    pack_date = pod; origin = digit awal barcode diterjemahkan seperti stok (`ImportLegacyStock::ORIGIN_NAMES`); is_repacked = true bila barcode itu sudah menjadi bahan repack di aplikasi baru.
 */
class ImportLegacySalesReturns extends Command
{
    protected $signature = 'legacy:import-sales-returns
                            {--source= : Berkas snapshot SQL retur (legacy/tarik/tarik.sh retur)}
                            {--master= : Berkas snapshot master (tabel customers) untuk peta penggabungan pelanggan}
                            {--fallback-user= : Username akun pengganti untuk pembuat yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat Retur Penjualan legacy (dry-run bawaan)';

    private const EMPTY_GRADE = 1;

    public function handle(): int
    {
        $path = $this->option('source');
        $masterPath = $this->option('master');

        if (! $path || ! is_file($path) || ! $masterPath || ! is_file($masterPath)) {
            $this->error('--source (snapshot retur) dan --master (snapshot master) wajib diisi dengan berkas yang ada.');

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
        $returns = collect(LegacySqlDump::parseTable($sql, 'returjual'))->sortBy(fn ($r) => (int) $r['idreturjual'])->values();
        $details = collect(LegacySqlDump::parseTable($sql, 'returjualdetail'))->groupBy('idreturjual');
        unset($sql);

        $customerMap = ImportLegacyMaster::customerIdMap(file_get_contents($masterPath));
        $customerIds = DB::table('customers')->pluck('id')->flip();
        $productIds = Product::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $existing = DB::table('sales_returns')->pluck('id', 'return_number');
        $repacked = DB::table('repack_materials')->pluck('barcode')->flip();

        $stats = ['created' => 0, 'skipped' => 0, 'items' => 0, 'kg' => 0.0, 'suffix' => 0, 'emptyGrade' => 0, 'repacked' => 0, 'noProduct' => 0, 'noItems' => 0, 'merged' => 0, 'fallback' => 0];
        $refused = $conflicts = $examples = [];
        $seen = [];

        $run = function () use ($returns, $details, $customerMap, $customerIds, $productIds, $userIds, $existing, $repacked, $fallback, $apply, &$stats, &$refused, &$conflicts, &$examples, &$seen): void {
            foreach ($returns as $row) {
                $base = trim((string) $row['returnnumber']);
                $seen[$base] = ($seen[$base] ?? 0) + 1;
                $number = $seen[$base] > 1 ? $base.'-'.$seen[$base] : $base;
                $stats['suffix'] += $seen[$base] > 1 ? 1 : 0;

                if (isset($existing[$number])) {
                    $stats['skipped']++;
                    $this->remember((int) $row['idreturjual'], (int) $existing[$number], $apply);

                    continue;
                }

                $customerId = $customerMap[(int) $row['idcustomer']] ?? (int) $row['idcustomer'];

                if (! isset($customerIds[$customerId])) {
                    $refused[] = "{$number}: pelanggan idcustomer={$row['idcustomer']} tidak ada -- DITOLAK.";

                    continue;
                }

                $stats['merged'] += $customerId !== (int) $row['idcustomer'] ? 1 : 0;
                $items = [];

                foreach ($details->get($row['idreturjual'], collect()) as $d) {
                    $barcode = trim((string) $d['kdbarcode']);

                    if (! isset($productIds[(int) $d['idbarang']])) {
                        $stats['noProduct']++;
                        $conflicts[] = "{$number}: barcode {$barcode} idbarang={$d['idbarang']} bukan produk impor, dilewati.";

                        continue;
                    }

                    $gradeRaw = trim((string) ($d['idgrade'] ?? ''));

                    if ($gradeRaw === '') {
                        $stats['emptyGrade']++;
                        $gradeRaw = (string) self::EMPTY_GRADE;
                    }

                    if (! LegacyGradeMap::knows((int) $gradeRaw)) {
                        $refused[] = "{$number}: barcode {$barcode} grade legacy {$gradeRaw} di luar tabel terjemahan -- DITOLAK.";

                        continue;
                    }

                    $digit = substr($barcode, 0, 1);

                    if (! isset(ImportLegacyStock::ORIGIN_NAMES[$digit])) {
                        $refused[] = "{$number}: barcode {$barcode} kode asal \"{$digit}\" tidak dikenal -- DITOLAK.";

                        continue;
                    }

                    $place = LegacyGradeMap::resolve((int) $gradeRaw);
                    $pcs = trim((string) ($d['pcs'] ?? ''));
                    $isRepacked = isset($repacked[$barcode]);
                    $stats['repacked'] += $isRepacked ? 1 : 0;
                    $at = $d['creatime'] ?: now();
                    $items[] = [
                        'product_id' => (int) $d['idbarang'], 'warehouse_id' => $place['warehouse_id'], 'grade_id' => $place['grade_id'], 'barcode' => $barcode,
                        'weight' => round((float) $d['qty'], 2), 'qty_pcs' => $pcs === '' ? 0 : (int) $pcs, 'pack_date' => $d['pod'] ?: null,
                        'origin' => ImportLegacyStock::ORIGIN_NAMES[$digit], 'is_repacked' => $isRepacked ? 1 : 0, 'created_at' => $at, 'updated_at' => $at,
                    ];
                    $stats['kg'] += (float) $d['qty'];
                }

                $stats['noItems'] += $items === [] ? 1 : 0;
                $note = trim((string) ($row['note'] ?? ''));
                $userId = (int) $row['idusers'];

                if (! isset($userIds[$userId])) {
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $header = [
                    'return_number' => $number, 'return_date' => $row['returdate'], 'customer_id' => $customerId, 'note' => ($note === '' || $note === '-') ? null : $note,
                    'status' => 'Approved', 'credit_amount' => 0, 'created_by' => $userId,
                ];
                $stats['created']++;
                $stats['items'] += count($items);

                if (count($examples) < (int) $this->option('examples') && $items !== []) {
                    $examples[] = [$row, $details->get($row['idreturjual'])->take(1)->all(), $header, array_slice($items, 0, 1)];
                }

                if ($apply) {
                    $at = $row['creatime'] ?: now();
                    $id = DB::table('sales_returns')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]);
                    $this->remember((int) $row['idreturjual'], $id, true);

                    foreach (array_chunk($items, 500) as $chunk) {
                        DB::table('sales_return_items')->insert(array_map(fn ($i) => $i + ['sales_return_id' => $id], $chunk));
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

    private function remember(int $legacyId, int $newId, bool $apply): void
    {
        if ($apply && $newId > 0) {
            DB::table('legacy_import_map')->insertOrIgnore(['entity' => 'sales_return', 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function report(bool $apply, array $stats, array $refused, array $conflicts, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("sales_returns -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Barcode: {$stats['items']} dibawa, ".number_format($stats['kg'], 0, ',', '.')." kg. Retur tanpa barcode: {$stats['noItems']}. Barcode yang sudah di-repack: {$stats['repacked']}.");
        $this->line("Nomor kembar diberi akhiran: {$stats['suffix']}. Grade kosong -> J01: {$stats['emptyGrade']}. Pelanggan lewat peta penggabungan: {$stats['merged']}. Produk bukan impor dilewati: {$stats['noProduct']}. Pembuat diganti akun pengganti: {$stats['fallback']}.");

        foreach ($examples as [$row, $old, $header, $new]) {
            $this->line('');
            $this->line("Contoh {$row['returnnumber']}:");
            $this->line("  LAMA  returjual: returnnumber={$row['returnnumber']} returdate={$row['returdate']} idcustomer={$row['idcustomer']} note=\"".($row['note'] ?? '')."\"");

            foreach ($old as $d) {
                $this->line("        returjualdetail: kdbarcode={$d['kdbarcode']} idbarang={$d['idbarang']} idgrade={$d['idgrade']} qty={$d['qty']} pcs={$d['pcs']} pod={$d['pod']}");
            }

            $this->line("  BARU  sales_returns: return_number={$header['return_number']} return_date={$header['return_date']} customer_id={$header['customer_id']} status={$header['status']}");

            foreach ($new as $i) {
                $this->line("        sales_return_items: barcode={$i['barcode']} product_id={$i['product_id']} warehouse_id={$i['warehouse_id']} grade_id={$i['grade_id']} weight={$i['weight']} qty_pcs={$i['qty_pcs']} origin={$i['origin']} is_repacked={$i['is_repacked']}");
            }
        }

        $this->line('');
        $this->line('Catatan: ditulis tanpa event model (tidak ada tambahan stok, pengurangan tagihan, atau notifikasi).');

        foreach (array_slice($refused, 0, 6) as $text) {
            $this->line("  ! {$text}");
        }
    }
}
