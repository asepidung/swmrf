<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SALES ORDER legacy (`salesorder` + `salesorderdetail`) -> `sales_orders` + `sales_order_items`, SELURUH riwayat yang tidak dihapus
 * (keputusan Ayah, 21 Oktober 2026). Langkah 1 dari SO -> Tally -> DO -> Invoice. Dry-run bawaan; `--apply` menulis; aman diulang
 * (nomor SO yang sudah ada dilewati); satu penolakan membatalkan semuanya. Ditulis lewat DB::table (tanpa event model).
 *
 * Pemasangan:
 *  - so_number = sonumber apa adanya; customer_id lewat PETA PENGGABUNGAN pelanggan (`ImportLegacyMaster::customerIdMap`, butuh `--master`); pelanggan yang hilang = DITOLAK;
 *  - delivery_date = deliverydate; po_number = po; shipping_address = alamat; note = note ("-" dianggap kosong); down_payment 0; created_by = idusers bila akun ada, selain itu `--fallback-user`;
 *  - status (legacy `progress`): Delivered -> completed; Cancel dan Rejected -> cancelled; Waiting -> waiting; On Process -> processing; On Delivery -> on_delivery; DRAFT -> waiting.
 *    Status yang BASI diperbaiki menurut DO-nya: SO On Delivery/On Process/DRAFT/Waiting yang punya DO dan semua DO-nya sudah "Invoiced" menjadi completed (di legacy statusnya tidak pernah diperbarui);
 *  - item: product_id = idbarang; weight (kg bulat); price; discount = PERSEN (aplikasi baru memakai persen). Diskon legacy bernilai > 100 (kemungkinan rupiah/salah kolom) TIDAK dibawa
 *    sebagai persen: discount 0 dan nilainya dicatat di catatan baris; produk yang tidak diimpor (barang non-daging) dilewati dan dicatat di catatan SO.
 */
class ImportLegacySalesOrders extends Command
{
    protected $signature = 'legacy:import-sales-orders
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh sofull)}
                            {--master= : Berkas snapshot master (tabel customers) untuk peta penggabungan pelanggan}
                            {--fallback-user= : Username akun pengganti untuk pembuat SO yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat Sales Order legacy ke sales_orders (dry-run bawaan)';

    public const STATUS_MAP = [
        'Delivered' => 'completed', 'Cancel' => 'cancelled', 'Rejected' => 'cancelled', 'Waiting' => 'waiting',
        'On Process' => 'processing', 'On Delivery' => 'on_delivery', 'DRAFT' => 'waiting',
    ];

    public function handle(): int
    {
        $path = $this->option('source');
        $masterPath = $this->option('master');

        if (! $path || ! is_file($path) || ! $masterPath || ! is_file($masterPath)) {
            $this->error('--source (snapshot SO) dan --master (snapshot master) wajib diisi dengan berkas yang ada.');

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

        $customerMap = ImportLegacyMaster::customerIdMap(file_get_contents($masterPath));
        $customerIds = DB::table('customers')->pluck('id')->flip();
        $productIds = Product::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $existing = DB::table('sales_orders')->pluck('id', 'so_number');
        $details = $table('salesorderdetail')->groupBy('idso');
        $dos = $table('do')->groupBy('idso');
        $names = Product::query()->pluck('name', 'id');
        $rows = $table('salesorder')->sortBy('idso')->values();

        $stats = ['created' => 0, 'skipped' => 0, 'items' => 0, 'skippedItems' => 0, 'badDiscount' => 0, 'merged' => 0, 'fallback' => 0, 'fixedStatus' => 0, 'kg' => 0.0, 'value' => 0.0, 'noItems' => 0];
        $perStatus = $refused = $conflicts = $examples = [];

        $run = function () use ($rows, $details, $dos, $customerMap, $customerIds, $productIds, $userIds, $existing, $names, $fallback, $apply, &$stats, &$perStatus, &$refused, &$conflicts, &$examples): void {
            foreach ($rows as $row) {
                $number = trim((string) $row['sonumber']);

                if (isset($existing[$number])) {
                    $stats['skipped']++;
                    $this->remember('sales_order', (int) $row['idso'], (int) $existing[$number], $apply);

                    continue;
                }

                $customerId = $customerMap[(int) $row['idcustomer']] ?? (int) $row['idcustomer'];

                if (! isset($customerIds[$customerId])) {
                    $refused[] = "{$number}: pelanggan idcustomer={$row['idcustomer']} tidak ada -- DITOLAK.";

                    continue;
                }

                $stats['merged'] += $customerId !== (int) $row['idcustomer'] ? 1 : 0;
                $note = trim((string) ($row['note'] ?? ''));
                $note = $note === '-' ? '' : $note;
                $userId = (int) $row['idusers'];

                if (! isset($userIds[$userId])) {
                    $note = trim($note.' | Pembuat legacy: iduser '.$userId, ' |');
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $legacyStatus = (string) $row['progress'];
                $status = self::STATUS_MAP[$legacyStatus] ?? null;

                if ($status === null) {
                    $refused[] = "{$number}: status legacy \"{$legacyStatus}\" tidak dikenal -- DITOLAK.";

                    continue;
                }

                $soDos = $dos->get($row['idso'], collect());

                if ($status !== 'completed' && $status !== 'cancelled' && $soDos->isNotEmpty() && $soDos->every(fn ($d) => $d['status'] === 'Invoiced')) {
                    $status = 'completed';
                    $stats['fixedStatus']++;
                }

                if ($legacyStatus === 'Rejected') {
                    $note = trim($note.' | Ditolak di legacy', ' |');
                } elseif ($legacyStatus === 'DRAFT' && $status === 'waiting') {
                    $note = trim($note.' | Berstatus DRAFT di legacy', ' |');
                }

                $items = [];
                $skippedText = [];

                foreach ($details->get($row['idso'], collect()) as $line) {
                    if (! isset($productIds[(int) $line['idbarang']])) {
                        $stats['skippedItems']++;
                        $skippedText[] = 'barang '.$line['idbarang'].' '.(float) $line['weight'].' x '.(float) $line['price'];
                        $conflicts[] = "{$number}: baris idbarang={$line['idbarang']} bukan produk impor, dilewati.";

                        continue;
                    }

                    $discount = (float) ($line['discount'] ?? 0);
                    $lineNote = trim((string) ($line['notes'] ?? ''));

                    if ($discount > 100) {
                        $lineNote = trim($lineNote.' | Diskon legacy '.(float) $discount.' tidak dibawa (bukan persen)', ' |');
                        $discount = 0;
                        $stats['badDiscount']++;
                    }

                    $weight = (int) round((float) $line['weight']);
                    $price = (int) round((float) $line['price']);
                    $items[(int) $line['idsodetail']] = [
                        'product_id' => (int) $line['idbarang'], 'weight' => $weight, 'price' => $price, 'discount' => (int) round($discount), 'note' => $lineNote ?: null,
                    ];
                    $stats['kg'] += $weight;
                    $stats['value'] += $weight * $price * (1 - $discount / 100);
                }

                $stats['noItems'] += $items === [] ? 1 : 0;

                if ($skippedText !== []) {
                    $note = trim($note.' | '.count($skippedText).' baris dilewati (bukan produk impor): '.implode('; ', $skippedText), ' |');
                }

                $header = [
                    'so_number' => $number, 'customer_id' => $customerId, 'delivery_date' => $row['deliverydate'], 'po_number' => trim((string) ($row['po'] ?? '')) ?: null,
                    'shipping_address' => trim((string) ($row['alamat'] ?? '')) ?: null, 'status' => $status, 'note' => $note ?: null, 'down_payment' => 0, 'created_by' => $userId,
                ];
                $perStatus["{$legacyStatus} -> {$status}"] = ($perStatus["{$legacyStatus} -> {$status}"] ?? 0) + 1;
                $stats['created']++;
                $stats['items'] += count($items);

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $details->get($row['idso'], collect())->take(2)->all(), $header, array_slice($items, 0, 2, true), $names];
                }

                if ($apply) {
                    $at = $row['creatime'] ?: now();
                    $id = DB::table('sales_orders')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]);
                    $this->remember('sales_order', (int) $row['idso'], $id, true);

                    foreach (array_chunk($items, 200, true) as $chunk) {
                        foreach ($chunk as $legacyId => $item) {
                            $itemId = DB::table('sales_order_items')->insertGetId($item + ['sales_order_id' => $id, 'created_at' => $at, 'updated_at' => $at]);
                            $this->remember('sales_order_item', (int) $legacyId, $itemId, true);
                        }
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
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("sales_orders -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Baris: {$stats['items']} dibawa ({$stats['skippedItems']} dilewati karena bukan produk impor); berat total ".number_format($stats['kg'], 0, ',', '.').' kg; nilai (setelah diskon persen) '.number_format($stats['value'], 0, ',', '.').". SO tanpa baris: {$stats['noItems']}.");
        $this->line('Status: '.collect($perStatus)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').". Status basi diperbaiki menurut DO-nya: {$stats['fixedStatus']}.");
        $this->line("Pelanggan lewat peta penggabungan: {$stats['merged']}. Diskon > 100 (bukan persen) tidak dibawa: {$stats['badDiscount']} baris. Pembuat diganti akun pengganti: {$stats['fallback']}.");

        foreach ($examples as [$row, $lines, $header, $items, $names]) {
            $this->line('');
            $this->line("Contoh {$row['sonumber']}:");
            $this->line("  LAMA  salesorder: sonumber={$row['sonumber']} idcustomer={$row['idcustomer']} deliverydate={$row['deliverydate']} po=\"".($row['po'] ?? '')."\" alamat=\"".($row['alamat'] ?? '')."\" progress={$row['progress']} idusers={$row['idusers']}");

            foreach ($lines as $l) {
                $this->line("        salesorderdetail: idbarang={$l['idbarang']} weight={$l['weight']} price={$l['price']} discount=".($l['discount'] ?? '(kosong)'));
            }

            $this->line("  BARU  sales_orders: so_number={$header['so_number']} customer_id={$header['customer_id']} delivery_date={$header['delivery_date']} status={$header['status']} shipping_address=\"".($header['shipping_address'] ?? '')."\"");

            foreach ($items as $i) {
                $this->line("        item: product_id={$i['product_id']} ({$names[$i['product_id']]}) weight={$i['weight']} price={$i['price']} discount={$i['discount']}%");
            }
        }

        $this->line('');
        $this->line('Catatan: ditulis tanpa event model (tidak membuat tugas, notifikasi, atau perubahan stok). Tally, DO, dan Invoice dibawa di langkah berikutnya.');

        foreach (array_slice($refused, 0, 6) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
