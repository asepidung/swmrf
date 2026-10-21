<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DELIVERY ORDER legacy (`do` + `dodetail`) beserta TANDA TERIMA (`doreceipt` + `doreceiptdetail`) -> `delivery_orders` + `delivery_order_items`
 * dan `delivery_order_receipts` + `delivery_order_receipt_items`, SELURUH riwayat yang tidak dihapus (keputusan Ayah, 21 Oktober 2026).
 * Langkah 3 dari SO -> Tally -> DO -> Invoice; butuh SO dan Tally lebih dulu (penghubung lewat `legacy_import_map`).
 * Dry-run bawaan; `--apply` menulis; aman diulang (nomor DO/tanda terima yang sudah ada dilewati); satu penolakan membatalkan semuanya. Tanpa event model (tidak mengubah status SO/tally, tidak ada gerak stok).
 *
 * Pemasangan DO:
 *  - delivery_order_number = donumber (nomor legacy dipertahankan); customer_id lewat peta penggabungan pelanggan (butuh `--master`); sales_order_id / tally_id lewat peta (kosong di legacy = kosong; tidak ketemu = kosong dan dicatat);
 *  - delivery_date, po_number = po, seal_number = sealnumb, note = note; created_by = `--fallback-user` bila akun legacy tidak ada; created_at = created;
 *  - status: Invoiced -> Invoiced, Approved -> Approved, Unapproved -> Ready (kosakata aplikasi baru); Rejected -> DO dihapus-lunak (seperti penolakan di aplikasi baru) dengan catatan;
 *  - supir/kendaraan: legacy menulis teks bebas (97 ejaan). Dicocokkan ke master `drivers` menurut nama (huruf besar, tanpa awalan "H.") dan `vehicles` menurut plat tanpa spasi; yang tidak ketemu
 *    dikosongkan dan teks aslinya dicatat di catatan DO ("Supir legacy: ... | Plat legacy: ...") supaya tidak ada yang hilang;
 *  - item: product_id = idbarang, box, weight, notes; produk bukan impor dilewati dan dicatat.
 * Tanda terima: receipt_number = donumber legacy (kembar diberi -2), delivery_order_id lewat peta DO, sales_order_id dari idso (atau dari DO-nya), customer, delivery_date, po, note, total_box = xbox, total_weight = xweight, status apa adanya.
 * Total DO (xbox/xweight) dan berat terima (rweight) tidak punya kolom di aplikasi baru; dihitung dari item.
 */
class ImportLegacyDeliveryOrders extends Command
{
    protected $signature = 'legacy:import-delivery-orders
                            {--source= : Berkas snapshot SQL DO (legacy/tarik/tarik.sh do)}
                            {--master= : Berkas snapshot master (tabel customers) untuk peta penggabungan pelanggan}
                            {--fallback-user= : Username akun pengganti untuk pembuat yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat Delivery Order + tanda terima legacy (dry-run bawaan)';

    public const STATUS_MAP = ['Invoiced' => 'Invoiced', 'Approved' => 'Approved', 'Unapproved' => 'Ready', 'Rejected' => 'Ready'];

    public function handle(): int
    {
        $path = $this->option('source');
        $masterPath = $this->option('master');

        if (! $path || ! is_file($path) || ! $masterPath || ! is_file($masterPath)) {
            $this->error('--source (snapshot DO) dan --master (snapshot master) wajib diisi dengan berkas yang ada.');

            return self::FAILURE;
        }

        $fallback = $this->option('fallback-user') ? User::withoutGlobalScopes()->where('username', $this->option('fallback-user'))->first() : null;

        if (! $fallback) {
            $this->error('--fallback-user wajib diisi dengan username yang ada.');

            return self::FAILURE;
        }

        ini_set('memory_limit', '2048M');

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');
        $orders = collect(LegacySqlDump::parseTable($sql, 'do'))->sortBy(fn ($r) => (int) $r['iddo'])->values();
        $orderLines = collect(LegacySqlDump::parseTable($sql, 'dodetail'))->groupBy('iddo');
        $receipts = collect(LegacySqlDump::parseTable($sql, 'doreceipt'))->sortBy(fn ($r) => (int) $r['iddoreceipt'])->values();
        $receiptLines = collect(LegacySqlDump::parseTable($sql, 'doreceiptdetail'))->groupBy('iddoreceipt');
        unset($sql);

        $maps = fn (string $entity) => DB::table('legacy_import_map')->where('entity', $entity)->pluck('new_id', 'legacy_id');
        $soMap = $maps('sales_order');
        $tallyMap = $maps('tally');
        $customerMap = ImportLegacyMaster::customerIdMap(file_get_contents($masterPath));
        $customerIds = DB::table('customers')->pluck('id')->flip();
        $productIds = Product::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $drivers = DB::table('drivers')->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [self::driverKey($name) => $id]);
        $vehicles = DB::table('vehicles')->pluck('id', 'police_number')->mapWithKeys(fn ($id, $plate) => [self::plateKey($plate) => $id]);
        $existingDo = DB::table('delivery_orders')->pluck('id', 'delivery_order_number');
        $existingReceipt = DB::table('delivery_order_receipts')->pluck('id', 'receipt_number');

        $stats = ['do' => 0, 'doSkipped' => 0, 'doItems' => 0, 'skippedItems' => 0, 'rejected' => 0, 'noTally' => 0, 'lostTally' => 0, 'lostSo' => 0, 'driverHit' => 0, 'vehicleHit' => 0, 'driverMiss' => 0, 'vehicleMiss' => 0,
            'receipts' => 0, 'receiptSkipped' => 0, 'receiptItems' => 0, 'receiptNoDo' => 0, 'receiptSuffix' => 0, 'fallback' => 0, 'kg' => 0.0];
        $perStatus = $missingDrivers = $missingPlates = $refused = $conflicts = $examples = [];
        $doIdOf = [];
        $knownDo = $orders->pluck('iddo')->map(fn ($i) => (int) $i)->flip();
        $receiptSeen = [];

        $userOf = function (int $legacyUser) use ($userIds, $fallback, &$stats): int {
            if (isset($userIds[$legacyUser])) {
                return $legacyUser;
            }

            $stats['fallback']++;

            return $fallback->id;
        };

        $run = function () use ($orders, $orderLines, $receipts, $receiptLines, $soMap, $tallyMap, $customerMap, $customerIds, $productIds, $drivers, $vehicles, &$existingDo, &$existingReceipt, $userOf, $apply, $knownDo, &$stats, &$perStatus, &$missingDrivers, &$missingPlates, &$refused, &$conflicts, &$examples, &$doIdOf, &$receiptSeen): void {
            foreach ($orders as $row) {
                $number = trim((string) $row['donumber']);

                if (isset($existingDo[$number])) {
                    $stats['doSkipped']++;
                    $doIdOf[(int) $row['iddo']] = (int) $existingDo[$number];
                    $this->remember('delivery_order', (int) $row['iddo'], (int) $existingDo[$number], $apply);

                    continue;
                }

                $customerId = $customerMap[(int) $row['idcustomer']] ?? (int) $row['idcustomer'];

                if (! isset($customerIds[$customerId])) {
                    $refused[] = "{$number}: pelanggan idcustomer={$row['idcustomer']} tidak ada -- DITOLAK.";

                    continue;
                }

                $legacyStatus = trim((string) $row['status']);

                if (! isset(self::STATUS_MAP[$legacyStatus])) {
                    $refused[] = "{$number}: status legacy \"{$legacyStatus}\" tidak dikenal -- DITOLAK.";

                    continue;
                }

                $note = trim((string) ($row['note'] ?? ''));
                $soId = $row['idso'] ? ($soMap[(int) $row['idso']] ?? null) : null;
                $tallyId = $row['idtally'] ? ($tallyMap[(int) $row['idtally']] ?? null) : null;
                $stats['noTally'] += $row['idtally'] ? 0 : 1;
                $stats['lostSo'] += ($row['idso'] && ! $soId) ? 1 : 0;
                $stats['lostTally'] += ($row['idtally'] && ! $tallyId) ? 1 : 0;

                $driver = trim((string) ($row['driver'] ?? ''));
                $plate = trim((string) ($row['plat'] ?? ''));
                $driverId = $driver === '' || $driver === '-' ? null : ($drivers[self::driverKey($driver)] ?? null);
                $vehicleId = $plate === '' || $plate === '-' ? null : ($vehicles[self::plateKey($plate)] ?? null);
                $stats['driverHit'] += $driverId ? 1 : 0;
                $stats['vehicleHit'] += $vehicleId ? 1 : 0;
                $lost = [];

                if ($driver !== '' && $driver !== '-' && ! $driverId) {
                    $stats['driverMiss']++;
                    $missingDrivers[strtoupper($driver)] = ($missingDrivers[strtoupper($driver)] ?? 0) + 1;
                    $lost[] = 'Supir legacy: '.$driver;
                }

                if ($plate !== '' && $plate !== '-' && ! $vehicleId) {
                    $stats['vehicleMiss']++;
                    $missingPlates[strtoupper($plate)] = ($missingPlates[strtoupper($plate)] ?? 0) + 1;
                    $lost[] = 'Plat legacy: '.$plate;
                }

                if ($lost !== []) {
                    $note = trim($note.' | '.implode(' | ', $lost), ' |');
                }

                if ($legacyStatus === 'Rejected') {
                    $note = trim($note.' | Ditolak di legacy', ' |');
                    $stats['rejected']++;
                }

                $items = [];

                foreach ($orderLines->get($row['iddo'], collect()) as $line) {
                    if (! isset($productIds[(int) $line['idbarang']])) {
                        $stats['skippedItems']++;
                        $conflicts[] = "{$number}: baris idbarang={$line['idbarang']} bukan produk impor, dilewati.";

                        continue;
                    }

                    $items[(int) $line['iddodetail']] = ['product_id' => (int) $line['idbarang'], 'box' => (int) $line['box'], 'weight' => round((float) $line['weight'], 2), 'notes' => trim((string) ($line['notes'] ?? '')) ?: null];
                    $stats['kg'] += (float) $line['weight'];
                }

                $header = [
                    'tally_id' => $tallyId, 'sales_order_id' => $soId, 'customer_id' => $customerId, 'delivery_order_number' => $number, 'delivery_date' => $row['deliverydate'],
                    'po_number' => trim((string) ($row['po'] ?? '')) ?: null, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId, 'seal_number' => trim((string) ($row['sealnumb'] ?? '')) ?: null,
                    'note' => $note ?: null, 'status' => self::STATUS_MAP[$legacyStatus], 'created_by' => $userOf((int) $row['idusers']),
                ];
                $perStatus["{$legacyStatus} -> ".$header['status'].($legacyStatus === 'Rejected' ? ' (dihapus-lunak)' : '')] = ($perStatus["{$legacyStatus} -> ".$header['status'].($legacyStatus === 'Rejected' ? ' (dihapus-lunak)' : '')] ?? 0) + 1;
                $stats['do']++;
                $stats['doItems'] += count($items);

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $orderLines->get($row['iddo'], collect())->take(2)->all(), $header, array_slice($items, 0, 2, true)];
                }

                if ($apply) {
                    $at = $row['created'] ?: now();
                    $id = DB::table('delivery_orders')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at, 'deleted_at' => $legacyStatus === 'Rejected' ? $at : null]);
                    $doIdOf[(int) $row['iddo']] = $id;
                    $this->remember('delivery_order', (int) $row['iddo'], $id, true);

                    foreach (array_chunk($items, 500, true) as $chunk) {
                        DB::table('delivery_order_items')->insert(array_map(fn ($i) => $i + ['delivery_order_id' => $id, 'created_at' => $at, 'updated_at' => $at], array_values($chunk)));
                    }
                }
            }

            foreach ($receipts as $row) {
                $base = trim((string) $row['donumber']);
                $receiptSeen[$base] = ($receiptSeen[$base] ?? 0) + 1;
                $number = $receiptSeen[$base] > 1 ? $base.'-'.$receiptSeen[$base] : $base;
                $stats['receiptSuffix'] += $receiptSeen[$base] > 1 ? 1 : 0;

                if (isset($existingReceipt[$number])) {
                    $stats['receiptSkipped']++;
                    $this->remember('delivery_order_receipt', (int) $row['iddoreceipt'], (int) $existingReceipt[$number], $apply);

                    continue;
                }

                $customerId = $customerMap[(int) $row['idcustomer']] ?? (int) $row['idcustomer'];

                if (! isset($customerIds[$customerId])) {
                    $refused[] = "{$number}: tanda terima, pelanggan idcustomer={$row['idcustomer']} tidak ada -- DITOLAK.";

                    continue;
                }

                $status = trim((string) $row['status']);

                if (! in_array($status, ['Invoiced', 'Approved'], true)) {
                    $refused[] = "{$number}: tanda terima, status legacy \"{$status}\" tidak dikenal -- DITOLAK.";

                    continue;
                }

                $doId = $doIdOf[(int) $row['iddo']] ?? DB::table('legacy_import_map')->where(['entity' => 'delivery_order', 'legacy_id' => (int) $row['iddo']])->value('new_id');
                $doId ??= (! $apply && isset($knownDo[(int) $row['iddo']])) ? -1 : null; // dry-run: DO yang akan dibuat belum punya id
                $stats['receiptNoDo'] += $doId ? 0 : 1;
                $soId = $row['idso'] ? ($soMap[(int) $row['idso']] ?? null) : null;
                $soId ??= $doId > 0 ? DB::table('delivery_orders')->where('id', $doId)->value('sales_order_id') : null;
                $items = [];

                foreach ($receiptLines->get($row['iddoreceipt'], collect()) as $line) {
                    if (! isset($productIds[(int) $line['idbarang']])) {
                        $stats['skippedItems']++;

                        continue;
                    }

                    $items[(int) $line['iddoreceiptdetail']] = ['product_id' => (int) $line['idbarang'], 'box' => (int) $line['box'], 'weight' => round((float) $line['weight'], 2), 'notes' => trim((string) ($line['notes'] ?? '')) ?: null];
                }

                $header = [
                    'delivery_order_id' => $doId, 'sales_order_id' => $soId, 'customer_id' => $customerId, 'receipt_number' => $number, 'delivery_date' => $row['deliverydate'],
                    'po_number' => trim((string) ($row['po'] ?? '')) ?: null, 'note' => trim((string) ($row['note'] ?? '')) ?: null, 'total_box' => (int) $row['xbox'], 'total_weight' => round((float) $row['xweight'], 2),
                    'status' => $status, 'created_by' => $userOf((int) $row['idusers']),
                ];
                $stats['receipts']++;
                $stats['receiptItems'] += count($items);

                if ($apply) {
                    $at = $row['created'] ?: now();
                    $id = DB::table('delivery_order_receipts')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]);
                    $this->remember('delivery_order_receipt', (int) $row['iddoreceipt'], $id, true);

                    foreach (array_chunk($items, 500, true) as $chunk) {
                        DB::table('delivery_order_receipt_items')->insert(array_map(fn ($i) => $i + ['delivery_order_receipt_id' => $id, 'created_at' => $at, 'updated_at' => $at], array_values($chunk)));
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

        $this->report($apply, $stats, $perStatus, $missingDrivers, $missingPlates, $refused, $conflicts, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    public static function driverKey(string $name): string
    {
        return preg_replace('/^H\.?\s+/', '', strtoupper(trim($name)));
    }

    public static function plateKey(string $plate): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim($plate)));
    }

    private function remember(string $entity, int $legacyId, int $newId, bool $apply): void
    {
        if ($apply && $newId > 0) {
            DB::table('legacy_import_map')->insertOrIgnore(['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function report(bool $apply, array $stats, array $perStatus, array $missingDrivers, array $missingPlates, array $refused, array $conflicts, array $examples): void
    {
        arsort($missingDrivers);
        arsort($missingPlates);

        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("delivery_orders -- dibuat: {$stats['do']}, sudah ada (dilewati): {$stats['doSkipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Item DO: {$stats['doItems']} dibawa ({$stats['skippedItems']} dilewati bukan produk impor); berat ".number_format($stats['kg'], 0, ',', '.').' kg.');
        $this->line('Status: '.collect($perStatus)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').'.');
        $this->line("Tanpa tally di legacy: {$stats['noTally']}; tally tidak ketemu: {$stats['lostTally']}; SO tidak ketemu: {$stats['lostSo']}. Pembuat diganti akun pengganti: {$stats['fallback']}.");
        $this->line("Supir cocok ke master: {$stats['driverHit']}, tidak cocok (teks dicatat di note): {$stats['driverMiss']}. Kendaraan cocok: {$stats['vehicleHit']}, tidak cocok: {$stats['vehicleMiss']}.");
        $this->line('Supir tidak cocok (teratas): '.collect($missingDrivers)->take(12)->map(fn ($n, $k) => "{$k}={$n}")->implode(', '));
        $this->line('Plat tidak cocok (teratas): '.collect($missingPlates)->take(8)->map(fn ($n, $k) => "{$k}={$n}")->implode(', '));
        $this->line("delivery_order_receipts -- dibuat: {$stats['receipts']}, sudah ada (dilewati): {$stats['receiptSkipped']}; item {$stats['receiptItems']}; tanpa DO: {$stats['receiptNoDo']}; nomor kembar diberi akhiran: {$stats['receiptSuffix']}.");

        foreach ($examples as [$row, $lines, $header, $items]) {
            $this->line('');
            $this->line("Contoh {$row['donumber']}:");
            $this->line("  LAMA  do: donumber={$row['donumber']} idcustomer={$row['idcustomer']} idso=".($row['idso'] ?: '(kosong)').' idtally='.($row['idtally'] ?: '(kosong)')." deliverydate={$row['deliverydate']} driver=\"".($row['driver'] ?? '')."\" plat=\"".($row['plat'] ?? '')."\" status={$row['status']}");

            foreach ($lines as $l) {
                $this->line("        dodetail: idbarang={$l['idbarang']} box={$l['box']} weight={$l['weight']}");
            }

            $this->line("  BARU  delivery_orders: delivery_order_number={$header['delivery_order_number']} customer_id={$header['customer_id']} sales_order_id=".($header['sales_order_id'] ?? '(kosong)').' tally_id='.($header['tally_id'] ?? '(kosong)')." driver_id=".($header['driver_id'] ?? '(kosong)')." vehicle_id=".($header['vehicle_id'] ?? '(kosong)')." status={$header['status']}");

            foreach ($items as $i) {
                $this->line("        delivery_order_items: product_id={$i['product_id']} box={$i['box']} weight={$i['weight']}");
            }
        }

        $this->line('');
        $this->line('Catatan: ditulis tanpa event model (status SO/tally tidak berubah, tidak ada gerak stok). Invoice dibawa di langkah berikutnya.');

        foreach (array_slice($refused, 0, 6) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
