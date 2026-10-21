<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * INVOICE legacy (`invoice` + `invoicedetail`) -> `invoices` + `invoice_items` (+ `invoice_additional_charges` untuk biaya tambahan), SELURUH riwayat
 * yang tidak dihapus (keputusan Ayah, 21 Oktober 2026). Langkah 4 (terakhir) dari SO -> Tally -> DO -> Invoice; butuh SO dan DO/tanda terima lebih dulu.
 * Dry-run bawaan; `--apply` menulis; aman diulang (nomor invoice yang sudah ada dilewati); satu penolakan membatalkan semuanya. Tanpa event model
 * (tidak mengubah status tanda terima/DO, tidak ada piutang, jurnal, atau pembayaran).
 *
 * Pemasangan:
 *  - invoice_number = noinvoice (nomor legacy); customer_id lewat peta penggabungan pelanggan (butuh `--master`); delivery_order_receipt_id lewat peta `delivery_order_receipt`;
 *    sales_order_id dari tanda terima/DO-nya (kolom wajib di aplikasi baru). Invoice lama yang DO-nya tidak punya SO di legacy dihubungkan ke SO PENGGANTI (satu per DO, nomor "SO-LGC-<nomor DO>",
 *    status completed, isi dari baris invoice) supaya tautan wajib itu terpenuhi;
 *  - invoice_date, due_date, term_of_payment = top, po_number = pocustomer, delivery_order_number = donumber, note, total_weight = xweight, total_discount = xdiscount, tax 0 (legacy tidak pernah memakai),
 *    invoice_exchange_date = tgltf; created_by = `--fallback-user` (legacy tidak mencatat pembuat invoice);
 *  - status tukar faktur legacy: "Sudah TF" -> Sudah TF, "Belum TF" -> Belum TF, "-" (tidak perlu tukar faktur) -> Belum Dibayar;
 *  - item: product_id, weight, price, discount_percent = discount, discount_rp = discountrp, amount; box 0 (legacy tidak mencatat); subtotal = jumlah amount item (arti di aplikasi baru);
 *    invoice yang jumlah itemnya berbeda dari xamount legacy tetap memakai jumlah item dan selisihnya dicatat di note;
 *  - satu baris `receivables` per invoice (seperti CreateInvoice di aplikasi baru; daftar piutang membacanya);
 *  - barang NON-DAGING di baris invoice (STYROFOAM, ICE GELL, DELIVERY COST: tidak diimpor sebagai produk) -> baris `invoice_additional_charges` (nama, jumlah, harga, diskon, nilai);
 *    charge legacy (>0) -> satu baris tambahan "Biaya tambahan (legacy)"; invoices.charge = jumlah semuanya (arti di aplikasi baru); down_payment = downpayment;
 *  - PEMBAYARAN: legacy tidak mencatat pelunasan sama sekali (balance tidak pernah dikurangi), jadi `--payment` WAJIB dipilih:
 *      settled = semua invoice dianggap lunas (paid_amount = tagihan, saldo 0, status Lunas) tanpa catatan pembayaran;
 *      open    = tidak ada yang dibayar (paid_amount 0, saldo = tagihan; invoice dengan saldo legacy 0 = Lunas), status menurut tukar faktur.
 */
class ImportLegacyInvoices extends Command
{
    protected $signature = 'legacy:import-invoices
                            {--source= : Berkas snapshot SQL invoice (legacy/tarik/tarik.sh inv)}
                            {--master= : Berkas snapshot master (tabel customers) untuk peta penggabungan pelanggan}
                            {--payment= : settled | open (wajib; lihat penjelasan di kelas)}
                            {--fallback-user= : Username pembuat invoice (legacy tidak mencatat pembuat)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat Invoice legacy ke invoices + invoice_items (dry-run bawaan)';

    public const EXCHANGE_STATUS = ['Sudah TF' => 'Sudah TF', 'Belum TF' => 'Belum TF', '-' => 'Belum Dibayar', '' => 'Belum Dibayar'];

    public function handle(): int
    {
        $path = $this->option('source');
        $masterPath = $this->option('master');
        $payment = (string) $this->option('payment');

        if (! $path || ! is_file($path) || ! $masterPath || ! is_file($masterPath)) {
            $this->error('--source (snapshot invoice) dan --master (snapshot master) wajib diisi dengan berkas yang ada.');

            return self::FAILURE;
        }

        if (! in_array($payment, ['settled', 'open'], true)) {
            $this->error('--payment wajib diisi: settled (semua dianggap lunas) atau open (tidak ada yang dibayar).');

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
        $invoices = collect(LegacySqlDump::parseTable($sql, 'invoice'))->sortBy(fn ($r) => (int) $r['idinvoice'])->values();
        $lines = collect(LegacySqlDump::parseTable($sql, 'invoicedetail'))->groupBy('idinvoice');
        unset($sql);

        $receiptMap = DB::table('legacy_import_map')->where('entity', 'delivery_order_receipt')->pluck('new_id', 'legacy_id');
        $customerMap = ImportLegacyMaster::customerIdMap(file_get_contents($masterPath));
        $customerIds = DB::table('customers')->pluck('id')->flip();
        $productIds = Product::query()->pluck('id')->flip();
        $existing = DB::table('invoices')->pluck('id', 'invoice_number');
        $receipts = DB::table('delivery_order_receipts')->get(['id', 'delivery_order_id', 'sales_order_id', 'customer_id'])->keyBy('id');
        $orderSo = DB::table('delivery_orders')->pluck('sales_order_id', 'id');
        $barang = collect(LegacySqlDump::parseTable(file_get_contents($masterPath), 'barang'))->mapWithKeys(fn ($r) => [(int) $r['idbarang'] => strtoupper(trim((string) $r['nmbarang']))]);
        $groupOf = DB::table('customers')->pluck('customer_group_id', 'id');
        $substitute = DB::table('sales_orders')->where('so_number', 'like', 'SO-LGC-%')->pluck('id', 'so_number');

        $stats = ['created' => 0, 'skipped' => 0, 'items' => 0, 'skippedItems' => 0, 'subtotal' => 0.0, 'charge' => 0.0, 'dp' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'kg' => 0.0, 'noReceipt' => 0, 'substituteSo' => 0, 'mismatch' => 0, 'extraLines' => 0, 'charges' => 0, 'noDue' => 0];
        $perStatus = $refused = $conflicts = $examples = [];

        $run = function () use ($invoices, $lines, $barang, $groupOf, $receiptMap, $customerMap, $customerIds, $productIds, &$existing, $receipts, $orderSo, &$substitute, $fallback, $payment, $apply, &$stats, &$perStatus, &$refused, &$conflicts, &$examples): void {
            foreach ($invoices as $row) {
                $number = trim((string) $row['noinvoice']);

                if (isset($existing[$number])) {
                    $stats['skipped']++;
                    $this->remember('invoice', (int) $row['idinvoice'], (int) $existing[$number], $apply);

                    continue;
                }

                $customerId = $customerMap[(int) $row['idcustomer']] ?? (int) $row['idcustomer'];

                if (! isset($customerIds[$customerId])) {
                    $refused[] = "{$number}: pelanggan idcustomer={$row['idcustomer']} tidak ada -- DITOLAK.";

                    continue;
                }

                $exchange = trim((string) ($row['status'] ?? ''));

                if (! isset(self::EXCHANGE_STATUS[$exchange])) {
                    $refused[] = "{$number}: status legacy \"{$exchange}\" tidak dikenal -- DITOLAK.";

                    continue;
                }

                $receiptId = $receiptMap[(int) $row['iddoreceipt']] ?? null;
                $receipt = $receiptId ? $receipts->get($receiptId) : null;
                $stats['noReceipt'] += $receiptId ? 0 : 1;
                $soId = $receipt?->sales_order_id ?: ($receipt?->delivery_order_id ? ($orderSo[$receipt->delivery_order_id] ?? null) : null);

                $items = [];
                $extra = [];
                $note = trim((string) ($row['note'] ?? ''));

                foreach ($lines->get($row['idinvoice'], collect()) as $line) {
                    if (! isset($productIds[(int) $line['idbarang']])) {
                        $name = $barang[(int) $line['idbarang']] ?? '';

                        if (in_array($name, ImportLegacyMaster::NON_MEAT_PRODUCT_NAMES, true)) {
                            $extra[] = [
                                'name' => $name, 'qty' => round((float) $line['weight'], 2), 'price' => round((float) $line['price'], 2), 'discount_percent' => (float) ($line['discount'] ?? 0),
                                'discount_rp' => round((float) ($line['discountrp'] ?? 0), 2), 'amount' => round((float) $line['amount'], 2),
                            ];
                            $stats['extraLines']++;
                        } else {
                            $stats['skippedItems']++;
                            $conflicts[] = "{$number}: baris idbarang={$line['idbarang']} ({$name}) bukan produk impor dan bukan biaya tambahan, dilewati.";
                        }

                        continue;
                    }

                    $items[(int) $line['idinvoicedetail']] = [
                        'product_id' => (int) $line['idbarang'], 'box' => 0, 'weight' => round((float) $line['weight'], 2), 'price' => round((float) $line['price'], 2),
                        'discount_percent' => (float) ($line['discount'] ?? 0), 'discount_rp' => round((float) ($line['discountrp'] ?? 0), 2), 'amount' => round((float) $line['amount'], 2),
                    ];
                }

                if (! $soId) {
                    $soNumber = 'SO-LGC-'.preg_replace('/^DO-/', '', trim((string) $row['donumber']));
                    $soId = $substitute[$soNumber] ?? null;
                    $stats['substituteSo']++;

                    if (! $soId && $apply) {
                        $at = $row['invoice_date'];
                        $soId = DB::table('sales_orders')->insertGetId([
                            'so_number' => $soNumber, 'customer_id' => $customerId, 'delivery_date' => $at, 'po_number' => trim((string) ($row['pocustomer'] ?? '')) ?: null, 'status' => 'completed',
                            'note' => 'Dibentuk dari invoice legacy '.$number.' (DO lama tanpa Sales Order)', 'down_payment' => 0, 'created_by' => $fallback->id, 'created_at' => $at, 'updated_at' => $at,
                        ]);
                        $substitute[$soNumber] = $soId;

                        foreach ($items as $item) {
                            DB::table('sales_order_items')->insert([
                                'sales_order_id' => $soId, 'product_id' => $item['product_id'], 'weight' => (int) round($item['weight']), 'price' => (int) round($item['price']),
                                'discount' => (int) round($item['discount_percent']), 'created_at' => $at, 'updated_at' => $at,
                            ]);
                        }
                    }
                }

                $subtotal = round(array_sum(array_column($items, 'amount')), 2);
                $legacyAmount = round((float) $row['xamount'], 2);
                $extraSum = round(array_sum(array_column($extra, 'amount')), 2) - round((float) ($row['charge'] ?? 0), 2);

                if (abs($subtotal + $extraSum - $legacyAmount) > 1) {
                    $stats['mismatch']++;
                    $note = trim($note.' | Selisih legacy: jumlah item '.number_format($subtotal, 2, '.', '').' vs xamount '.number_format($legacyAmount, 2, '.', ''), ' |');
                }

                $legacyCharge = round((float) ($row['charge'] ?? 0), 2);

                if ($legacyCharge > 0) {
                    $extra[] = ['name' => 'Biaya tambahan (legacy)', 'qty' => 1, 'price' => $legacyCharge, 'discount_percent' => 0, 'discount_rp' => 0, 'amount' => $legacyCharge];
                }

                $charge = round(array_sum(array_column($extra, 'amount')), 2);
                $downPayment = round((float) ($row['downpayment'] ?? 0), 2);
                $bill = max(0, round($subtotal + $charge - $downPayment, 2));
                $legacyBalance = round((float) $row['balance'], 2);

                if ($payment === 'settled' || $legacyBalance <= 0) {
                    $paid = $bill;
                    $status = 'Lunas';
                } else {
                    $paid = 0.0;
                    $status = self::EXCHANGE_STATUS[$exchange];
                }

                $due = trim((string) ($row['duedate'] ?? ''));
                $stats['noDue'] += ($due === '' || str_starts_with($due, '0000')) ? 1 : 0;
                $tgltf = trim((string) ($row['tgltf'] ?? ''));

                $header = [
                    'invoice_number' => $number, 'delivery_order_receipt_id' => $receiptId, 'customer_id' => $customerId, 'sales_order_id' => $soId, 'invoice_date' => $row['invoice_date'],
                    'due_date' => ($due === '' || str_starts_with($due, '0000')) ? null : $due, 'term_of_payment' => (int) $row['top'], 'status' => $status,
                    'invoice_exchange_date' => ($tgltf === '' || str_starts_with($tgltf, '0000')) ? null : $tgltf, 'po_number' => trim((string) ($row['pocustomer'] ?? '')) ?: null,
                    'delivery_order_number' => trim((string) ($row['donumber'] ?? '')) ?: null, 'note' => $note ?: null, 'total_weight' => round((float) $row['xweight'], 2), 'subtotal' => $subtotal,
                    'total_discount' => round((float) ($row['xdiscount'] ?? 0), 2), 'tax' => 0, 'charge' => $charge, 'down_payment' => $downPayment, 'paid_amount' => $paid,
                    'balance' => max(0, round($bill - $paid, 2)), 'created_by' => $fallback->id,
                ];
                $perStatus[$status] = ($perStatus[$status] ?? 0) + 1;
                $stats['created']++;
                $stats['items'] += count($items);
                $stats['subtotal'] += $subtotal;
                $stats['charge'] += $charge;
                $stats['dp'] += $downPayment;
                $stats['paid'] += $paid;
                $stats['balance'] += $header['balance'];
                $stats['kg'] += $header['total_weight'];
                $stats['charges'] += $extra !== [] ? 1 : 0;

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $lines->get($row['idinvoice'], collect())->take(2)->all(), $header, array_slice($items, 0, 2, true)];
                }

                if ($apply) {
                    $at = $row['creatime'] ?: now();
                    $id = DB::table('invoices')->insertGetId($header + ['created_at' => $at, 'updated_at' => $at]);
                    $this->remember('invoice', (int) $row['idinvoice'], $id, true);

                    DB::table('receivables')->insert(['invoice_id' => $id, 'customer_id' => $customerId, 'customer_group_id' => $groupOf[$customerId] ?? null, 'created_at' => $at, 'updated_at' => $at]);

                    foreach (array_chunk($items, 500, true) as $chunk) {
                        DB::table('invoice_items')->insert(array_map(fn ($i) => $i + ['invoice_id' => $id, 'created_at' => $at, 'updated_at' => $at], array_values($chunk)));
                    }

                    foreach ($extra as $e) {
                        DB::table('invoice_additional_charges')->insert($e + ['invoice_id' => $id, 'created_at' => $at, 'updated_at' => $at]);
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

        $this->report($apply, $payment, $stats, $perStatus, $refused, $conflicts, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    private function remember(string $entity, int $legacyId, int $newId, bool $apply): void
    {
        if ($apply && $newId > 0) {
            DB::table('legacy_import_map')->insertOrIgnore(['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function report(bool $apply, string $payment, array $stats, array $perStatus, array $refused, array $conflicts, array $examples): void
    {
        $fmt = fn (float $n) => number_format($n, 0, ',', '.');

        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("invoices -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused)." (pembayaran: {$payment})");
        $this->line("Item: {$stats['items']} dibawa ({$stats['skippedItems']} dilewati bukan produk impor); berat ".$fmt($stats['kg']).' kg. Biaya tambahan: '.$stats['charges'].' invoice ('.$stats['extraLines'].' baris barang non-daging dipindah ke Biaya Tambahan).');
        $this->line('Nilai: subtotal Rp '.$fmt($stats['subtotal']).' + biaya Rp '.$fmt($stats['charge']).' - uang muka Rp '.$fmt($stats['dp']).'; dibayar Rp '.$fmt($stats['paid']).'; SALDO Rp '.$fmt($stats['balance']).'.');
        $this->line('Status: '.collect($perStatus)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').'.');
        $this->line("Tanpa tanda terima di aplikasi baru: {$stats['noReceipt']}; memakai SO pengganti: {$stats['substituteSo']}; jumlah item beda dari xamount legacy: {$stats['mismatch']}; tanpa jatuh tempo: {$stats['noDue']}.");

        foreach ($examples as [$row, $lines, $header, $items]) {
            $this->line('');
            $this->line("Contoh {$row['noinvoice']}:");
            $this->line("  LAMA  invoice: noinvoice={$row['noinvoice']} idcustomer={$row['idcustomer']} iddoreceipt={$row['iddoreceipt']} invoice_date={$row['invoice_date']} top={$row['top']} status=\"{$row['status']}\" xamount={$row['xamount']} xdiscount={$row['xdiscount']} balance={$row['balance']}");

            foreach ($lines as $l) {
                $this->line("        invoicedetail: idbarang={$l['idbarang']} weight={$l['weight']} price={$l['price']} discount={$l['discount']} amount={$l['amount']}");
            }

            $this->line("  BARU  invoices: invoice_number={$header['invoice_number']} customer_id={$header['customer_id']} sales_order_id=".($header['sales_order_id'] ?? '(kosong)')." receipt_id=".($header['delivery_order_receipt_id'] ?? '(kosong)')." status={$header['status']} subtotal={$header['subtotal']} paid_amount={$header['paid_amount']} balance={$header['balance']}");

            foreach ($items as $i) {
                $this->line("        invoice_items: product_id={$i['product_id']} weight={$i['weight']} price={$i['price']} discount_percent={$i['discount_percent']} amount={$i['amount']}");
            }
        }

        $this->line('');
        $this->line('Catatan: ditulis tanpa event model (tidak ada piutang, jurnal, atau pembayaran; status tanda terima/DO tidak berubah).');

        foreach (array_slice($refused, 0, 6) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
