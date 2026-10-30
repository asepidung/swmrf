<?php

namespace App\Console\Commands;

use App\Support\LegacyGradeMap;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * REKONSILIASI: membandingkan angka snapshot legacy dengan isi aplikasi baru, per modul (jumlah dokumen, jumlah baris, total berat, total rupiah).
 * Hanya MEMBACA: tidak menulis apa pun. Dipakai setelah impor (hari-H, atau gladi) untuk memutuskan "cocok atau ada selisih" tanpa memeriksa satu per satu.
 *
 * Sisi aplikasi baru dibatasi pada dokumen hasil impor lewat `legacy_import_map`, jadi dokumen yang dibuat tim sesudahnya tidak mengacaukan angka.
 * Sisi legacy memakai aturan yang sama dengan importernya (hanya baris yang tidak dihapus, hanya produk yang ada di aplikasi baru), sehingga selisih
 * yang muncul adalah selisih SUNGGUHAN, bukan akibat aturan pemindahan. Keluar dengan kode 1 bila ada selisih.
 *
 * Modul penjualan dan stok: stok, boning, so, tally, do, invoice, repack, retur. Modul pembelian: request, po, gr, pocattle, hutang
 * (dokumen pembelian tidak memakai `legacy_import_map`, jadi sisi baru dicari lewat NOMOR dokumen dengan aturan akhiran -2, -3 yang sama dengan importer;
 * hutang dibandingkan dengan rumus aplikasi baru atas penerimaan hasil impor). `--dir` berisi snapshot hasil `legacy/tarik/tarik.sh` (berkas SWM-<modul>-*.sql terbaru dipakai).
 */
class ReconcileLegacy extends Command
{
    protected $signature = 'legacy:reconcile
                            {--dir= : Folder berisi snapshot legacy (SWM-<modul>-*.sql)}
                            {--only= : Hanya modul ini (pisah koma): stok,boning,so,tally,do,invoice,repack,retur,request,po,gr,pocattle,hutang}
                            {--before=2026-11-02 : Batas tanggal terima untuk modul hutang (sama dengan legacy:import-payables --before)}';

    protected $description = 'Bandingkan angka snapshot legacy dengan aplikasi baru per modul (hanya baca)';

    /** @var array<int, array{0: string, 1: string, 2: float, 3: float, 4: string}> */
    private array $rows = [];

    private string $dir = '';

    /** @var array<int|string, mixed> */
    private $productIds;

    public function handle(): int
    {
        $this->dir = rtrim((string) $this->option('dir'), '/\\');

        if ($this->dir === '' || ! is_dir($this->dir)) {
            $this->error('--dir wajib diisi dengan folder snapshot yang ada.');

            return self::FAILURE;
        }

        ini_set('memory_limit', '2048M');

        $this->productIds = DB::table('products')->pluck('id')->flip();
        $all = ['stok', 'boning', 'so', 'tally', 'do', 'invoice', 'repack', 'retur', 'request', 'po', 'gr', 'pocattle', 'hutang'];
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $modules = $only === [] ? $all : $only;

        foreach ($modules as $module) {
            if (! in_array($module, $all, true)) {
                $this->error("Modul \"{$module}\" tidak dikenal (pilihan: ".implode(', ', $all).').');

                return self::FAILURE;
            }
        }

        foreach ($modules as $module) {
            $this->{'check'.ucfirst($module)}();
        }

        return $this->report();
    }

    /** Berkas snapshot terbaru untuk satu modul, atau null bila tidak ada. */
    private function file(string $module): ?string
    {
        $files = glob($this->dir.DIRECTORY_SEPARATOR.'SWM-'.$module.'-*.sql') ?: [];
        sort($files);

        return $files === [] ? null : end($files);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function snap(string $module, string $table)
    {
        $file = $this->file($module);

        if (! $file) {
            throw new \RuntimeException("Snapshot modul \"{$module}\" tidak ada di {$this->dir}.");
        }

        return collect(LegacySqlDump::parseTable(file_get_contents($file), $table));
    }

    private function add(string $module, string $label, float|int $legacy, float|int $new, string $note = ''): void
    {
        $this->rows[] = [$module, $label, (float) $legacy, (float) $new, $note];
    }

    private function known(array $row, string $key = 'idbarang'): bool
    {
        return isset($this->productIds[(int) $row[$key]]);
    }

    private function mapped(string $entity)
    {
        return DB::table('legacy_import_map')->where('entity', $entity);
    }

    /** Baris tabel anak yang induknya hasil impor (lewat peta), untuk satu entitas. */
    private function childOf(string $table, string $foreignKey, string $entity)
    {
        return DB::table($table.' as c')->join('legacy_import_map as m', function ($join) use ($foreignKey, $entity) {
            $join->on('m.new_id', '=', 'c.'.$foreignKey)->where('m.entity', '=', $entity);
        });
    }

    private function checkSo(): void
    {
        $so = $this->snap('sofull', 'salesorder');
        $lines = $this->snap('sofull', 'salesorderdetail')->filter(fn ($l) => $this->known($l));
        $this->add('SO', 'sales order', $so->count(), $this->mapped('sales_order')->count());
        $this->add('SO', 'baris item', $lines->count(), $this->childOf('sales_order_items', 'sales_order_id', 'sales_order')->count());
        $this->add('SO', 'berat item (kg, dibulatkan per baris)', $lines->sum(fn ($l) => round((float) $l['weight'])), (float) $this->childOf('sales_order_items', 'sales_order_id', 'sales_order')->sum('c.weight'));
    }

    private function checkTally(): void
    {
        $tallies = $this->snap('tally', 'tally');
        $details = $this->snap('tally', 'tallydetail')->filter(fn ($d) => $this->known($d, 'idbarang'))->groupBy('idtally');
        $hasLines = $this->snap('tally', 'tallydetail')->pluck('idtally')->flip();
        $filledBySo = $tallies->filter(fn ($t) => isset($hasLines[$t['idtally']]))->countBy('idso');
        $skipped = $tallies->filter(fn ($t) => ! isset($hasLines[$t['idtally']]) && ($filledBySo[$t['idso']] ?? 0) > 0)->pluck('idtally')->flip();
        $expected = $tallies->reject(fn ($t) => isset($skipped[$t['idtally']]));

        $items = $expected->flatMap(fn ($t) => $details->get($t['idtally'], collect())->unique(fn ($d) => trim((string) $d['barcode'])));
        $this->add('Tally', 'tally', $expected->count(), $this->mapped('tally')->count(), count($skipped) ? 'tally kosong pada SO yang punya tally lain tidak dibawa: '.count($skipped) : '');
        $this->add('Tally', 'karton', $items->count(), $this->childOf('tally_items', 'tally_id', 'tally')->count());
        $this->add('Tally', 'berat karton (kg)', $items->sum(fn ($d) => round((float) $d['weight'], 2)), (float) $this->childOf('tally_items', 'tally_id', 'tally')->sum('c.weight'));
    }

    private function checkDo(): void
    {
        $orders = $this->snap('do', 'do');
        $lines = $this->snap('do', 'dodetail')->filter(fn ($l) => $this->known($l));
        $receipts = $this->snap('do', 'doreceipt');
        $receiptLines = $this->snap('do', 'doreceiptdetail')->filter(fn ($l) => $this->known($l));
        $this->add('DO', 'delivery order', $orders->count(), $this->mapped('delivery_order')->count());
        $this->add('DO', 'item DO', $lines->count(), $this->childOf('delivery_order_items', 'delivery_order_id', 'delivery_order')->count());
        $this->add('DO', 'berat item DO (kg)', $lines->sum(fn ($l) => round((float) $l['weight'], 2)), (float) $this->childOf('delivery_order_items', 'delivery_order_id', 'delivery_order')->sum('c.weight'));
        $this->add('DO', 'tanda terima', $receipts->count(), $this->mapped('delivery_order_receipt')->count());
        $this->add('DO', 'item tanda terima', $receiptLines->count(), $this->childOf('delivery_order_receipt_items', 'delivery_order_receipt_id', 'delivery_order_receipt')->count());
    }

    private function checkInvoice(): void
    {
        $invoices = $this->snap('inv', 'invoice');
        $allLines = $this->snap('inv', 'invoicedetail');
        $lines = $allLines->filter(fn ($l) => $this->known($l));
        $legacyCharge = $invoices->sum(fn ($i) => round((float) ($i['charge'] ?? 0), 2));
        $this->add('Invoice', 'invoice', $invoices->count(), $this->mapped('invoice')->count());
        $this->add('Invoice', 'baris item (produk daging)', $lines->count(), $this->childOf('invoice_items', 'invoice_id', 'invoice')->count());
        $this->add('Invoice', 'nilai item (Rp)', $lines->sum(fn ($l) => round((float) $l['amount'], 2)), (float) $this->childOf('invoice_items', 'invoice_id', 'invoice')->sum('c.amount'));
        $this->add('Invoice', 'biaya tambahan (Rp, barang non-daging + charge)', $allLines->reject(fn ($l) => $this->known($l))->sum(fn ($l) => round((float) $l['amount'], 2)) + $legacyCharge, (float) $this->childOf('invoice_additional_charges', 'invoice_id', 'invoice')->sum('c.amount'));
        $this->add('Invoice', 'total tagihan: semua baris + charge (Rp)', $allLines->sum(fn ($l) => round((float) $l['amount'], 2)) + $legacyCharge, (float) $this->mapped('invoice')->join('invoices as i', 'i.id', '=', 'legacy_import_map.new_id')->sum(DB::raw('i.subtotal + i.charge')));
    }

    private function checkRepack(): void
    {
        $repacks = $this->snap('repack', 'repack');
        $materials = $this->snap('repack', 'detailbahan')->filter(fn ($l) => $this->known($l));
        $results = $this->snap('repack', 'detailhasil')->filter(fn ($l) => $this->known($l));
        $this->add('Repack', 'repack', $repacks->count(), $this->mapped('repack')->count());
        $this->add('Repack', 'bahan (barcode)', $materials->count(), $this->childOf('repack_materials', 'repack_id', 'repack')->count());
        $this->add('Repack', 'berat bahan (kg)', $materials->sum(fn ($l) => round((float) $l['qty'], 2)), (float) $this->childOf('repack_materials', 'repack_id', 'repack')->sum('c.weight'));
        $this->add('Repack', 'hasil (barcode)', $results->count(), $this->childOf('repack_results', 'repack_id', 'repack')->count());
        $this->add('Repack', 'berat hasil (kg)', $results->sum(fn ($l) => round((float) $l['qty'], 2)), (float) $this->childOf('repack_results', 'repack_id', 'repack')->sum('c.weight'));
    }

    private function checkRetur(): void
    {
        $returns = $this->snap('retur', 'returjual');
        $lines = $this->snap('retur', 'returjualdetail')->filter(fn ($l) => $this->known($l));
        $this->add('Retur', 'retur penjualan', $returns->count(), $this->mapped('sales_return')->count());
        $this->add('Retur', 'barcode retur', $lines->count(), $this->childOf('sales_return_items', 'sales_return_id', 'sales_return')->count());
        $this->add('Retur', 'berat retur (kg)', $lines->sum(fn ($l) => round((float) $l['qty'], 2)), (float) $this->childOf('sales_return_items', 'sales_return_id', 'sales_return')->sum('c.weight'));
    }

    private function checkBoning(): void
    {
        // Snapshot memuat boning yang dihapus di legacy; importer membuangnya, jadi rekonsiliasi pun hanya menghitung yang hidup.
        $bonings = $this->snap('sapi', 'boning')->filter(fn ($b) => (int) ($b['is_deleted'] ?? 0) === 0);
        $liveIds = $bonings->pluck('idboning')->flip();
        $count = 0;
        $kg = 0.0;

        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'SWM-boninglabel*.sql') ?: [] as $file) {
            foreach (LegacySqlDump::parseTable(file_get_contents($file), 'labelboning') as $label) {
                if (! isset($liveIds[$label['idboning']]) || (int) ($label['is_deleted'] ?? 0) !== 0 || ! $this->known($label)) {
                    continue;
                }

                $count++;
                $kg += round((float) $label['qty'], 2);
            }
        }

        $this->add('Boning', 'boning', $bonings->count(), $this->mapped('boning')->count());
        $this->add('Boning', 'label (barcode)', $count, $this->childOf('boning_items', 'boning_id', 'boning')->count());
        $this->add('Boning', 'berat label (kg)', $kg, (float) $this->childOf('boning_items', 'boning_id', 'boning')->sum('c.weight'));
    }

    private function checkStok(): void
    {
        $rows = $this->snap('stok', 'stock')->filter(fn ($r) => trim((string) $r['kdbarcode']) !== '' && LegacyGradeMap::knows((int) $r['idgrade']) && $this->known($r));
        $this->add('Stok', 'barcode di gudang', $rows->count(), DB::table('beef_stocks')->count(), 'dibanding seluruh beef_stocks (belum ada gerak stok baru)');
        $this->add('Stok', 'berat (kg)', $rows->sum(fn ($r) => round((float) $r['qty'], 2)), (float) DB::table('beef_stocks')->sum('weight'));
    }

    /**
     * Nomor dokumen dengan aturan importer: nomor kembar diberi akhiran -2, -3 menurut urutan id.
     *
     * @return array<int, string> id legacy => nomor di aplikasi baru
     */
    private function numbersOf($rows, string $idColumn, string $numberColumn): array
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

    /** @return array<int, int> id baris di aplikasi baru yang nomornya ada di daftar */
    private function newIdsByNumber(string $table, string $column, array $numbers): array
    {
        $ids = [];

        foreach (array_chunk(array_values($numbers), 500) as $chunk) {
            $ids = array_merge($ids, DB::table($table)->whereIn($column, $chunk)->pluck('id')->all());
        }

        return $ids;
    }

    /** @return array{0: int, 1: float} jumlah baris dan jumlah kolom untuk anak dari induk-induk itu */
    private function childTotals(string $table, string $foreignKey, array $parentIds, string $sumColumn): array
    {
        $count = 0;
        $sum = 0.0;

        foreach (array_chunk($parentIds, 500) as $chunk) {
            $count += DB::table($table)->whereIn($foreignKey, $chunk)->count();
            $sum += (float) DB::table($table)->whereIn($foreignKey, $chunk)->sum($sumColumn);
        }

        return [$count, $sum];
    }

    /**
     * Satu pasang dokumen + barisnya: jumlah dokumen, jumlah baris, dan satu jumlah (nilai atau berat/qty).
     *
     * @param  array{label: string, module: string, head: string, line: string, idCol: string, numCol: string, itemCol: string, target: string, items: string, fk: string, sumNew: string, legacySum: callable, sumLabel: string, headFilter?: callable, docKeep?: callable, known: callable}  $c
     */
    private function checkDocuments(string $group, array $c): void
    {
        $rows = $this->snap($c['module'], $c['head'])->filter($c['headFilter'] ?? fn ($r) => (int) ($r['is_deleted'] ?? 0) === 0)->sortBy($c['idCol'])->values();
        $numbers = $this->numbersOf($rows, $c['idCol'], $c['numCol']);
        $lines = $this->snap($c['module'], $c['line'])->filter(fn ($l) => (int) ($l['is_deleted'] ?? 0) === 0)->groupBy($c['idCol']);

        $kept = $rows->filter(function ($r) use ($c, $lines) {
            $known = $lines->get($r[$c['idCol']], collect())->filter(fn ($l) => $c['known']($l));

            return ($c['docKeep'] ?? fn ($row, $knownLines) => true)($r, $known);
        });

        $legacyLines = $kept->flatMap(fn ($r) => $lines->get($r[$c['idCol']], collect())->filter(fn ($l) => $c['known']($l)));
        $keptNumbers = array_intersect_key($numbers, $kept->pluck($c['idCol'])->map(fn ($id) => (int) $id)->flip()->all());
        $newIds = $this->newIdsByNumber($c['target'], $c['newNumCol'], $keptNumbers);
        [$newLines, $newSum] = $this->childTotals($c['items'], $c['fk'], $newIds, $c['sumNew']);

        $this->add($group, $c['label'].': dokumen', $kept->count(), count($newIds), $rows->count() !== $kept->count() ? 'dilewati importer (tanpa baris bahan impor): '.($rows->count() - $kept->count()) : '');
        $this->add($group, $c['label'].': baris', $legacyLines->count(), $newLines, 'baris bahan di luar material/produk impor tidak dibawa');
        $this->add($group, $c['label'].': '.$c['sumLabel'], $legacyLines->sum($c['legacySum']), $newSum);
    }

    private function checkRequest(): void
    {
        $materials = DB::table('materials')->pluck('id')->flip();
        $products = $this->productIds;
        $statuses = [...\App\Console\Commands\ImportLegacyRequests::OPEN_STATUSES, ...\App\Console\Commands\ImportLegacyRequests::HISTORY_STATUSES];

        foreach ([
            ['Request material', 'belanja', 'request', 'requestdetail', 'idrawmate', 'material_requisitions', 'material_requisition_items', 'material_requisition_id', $materials],
            ['Request beef', 'belanjabeef', 'requestbeef', 'requestbeefdetail', 'idbarang', 'product_requisitions', 'product_requisition_items', 'product_requisition_id', $products],
        ] as [$label, $module, $head, $line, $col, $target, $items, $fk, $ids]) {
            $this->checkDocuments('Pembelian', [
                'label' => $label, 'module' => $module, 'head' => $head, 'line' => $line, 'idCol' => 'idrequest', 'numCol' => 'norequest', 'newNumCol' => 'document_number',
                'target' => $target, 'items' => $items, 'fk' => $fk, 'sumNew' => 'subtotal', 'sumLabel' => 'nilai (Rp)',
                'headFilter' => fn ($r) => (int) $r['is_deleted'] === 0 && in_array($r['stat'], $statuses, true),
                'known' => fn ($l) => isset($ids[(int) $l[$col]]),
                // Request terbuka tanpa satu pun baris bahan impor dilewati importer; riwayat tetap dibawa tanpa baris.
                'docKeep' => fn ($r, $known) => ! (in_array($r['stat'], \App\Console\Commands\ImportLegacyRequests::OPEN_STATUSES, true) && $known->isEmpty()),
                'legacySum' => fn ($l) => round(round((float) $l['qty'], 4) * (float) $l['price'], 2),
            ]);
        }
    }

    private function checkPo(): void
    {
        $materials = DB::table('materials')->pluck('id')->flip();
        $products = $this->productIds;

        foreach ([
            ['PO material', 'belanja', 'po', 'podetail', 'idrawmate', 'purchase_materials', 'purchase_material_items', 'purchase_material_id', $materials],
            ['PO beef', 'belanjabeef', 'pobeef', 'pobeefdetail', 'idbarang', 'purchase_products', 'purchase_product_items', 'purchase_product_id', $products],
        ] as [$label, $module, $head, $line, $col, $target, $items, $fk, $ids]) {
            $this->checkDocuments('Pembelian', [
                'label' => $label, 'module' => $module, 'head' => $head, 'line' => $line, 'idCol' => 'idpo', 'numCol' => 'nopo', 'newNumCol' => 'po_number',
                'target' => $target, 'items' => $items, 'fk' => $fk, 'sumNew' => 'subtotal', 'sumLabel' => 'nilai (Rp)',
                'known' => fn ($l) => isset($ids[(int) $l[$col]]),
                'legacySum' => fn ($l) => round((float) $l['qty'] * (float) $l['price'], 2),
            ]);
        }
    }

    private function checkGr(): void
    {
        $materials = DB::table('materials')->pluck('id')->flip();
        $products = $this->productIds;

        foreach ([
            ['GR material', 'belanja', 'grraw', 'grrawdetail', 'idrawmate', 'goods_receipt_materials', 'goods_receipt_material_items', 'goods_receipt_material_id', 'qty_received', $materials],
            ['GR beef', 'belanjabeef', 'grbeef', 'grbeefdetail', 'idbarang', 'goods_receipt_products', 'goods_receipt_product_items', 'goods_receipt_product_id', 'weight', $products],
        ] as [$label, $module, $head, $line, $col, $target, $items, $fk, $sumNew, $ids]) {
            $this->checkDocuments('Pembelian', [
                'label' => $label, 'module' => $module, 'head' => $head, 'line' => $line, 'idCol' => 'idgr', 'numCol' => 'grnumber', 'newNumCol' => 'gr_number',
                'target' => $target, 'items' => $items, 'fk' => $fk, 'sumNew' => $sumNew, 'sumLabel' => 'qty / berat',
                'known' => fn ($l) => isset($ids[(int) $l[$col]]),
                'legacySum' => fn ($l) => round((float) $l['qty'], 2),
            ]);
        }
    }

    private function checkPocattle(): void
    {
        $classNames = DB::table('cattle_classes')->count();
        $this->checkDocuments('Pembelian', [
            'label' => 'PO sapi', 'module' => 'sapi', 'head' => 'pocattle', 'line' => 'pocattledetail', 'idCol' => 'idpo', 'numCol' => 'nopo', 'newNumCol' => 'document_number',
            'target' => 'purchase_cattles', 'items' => 'purchase_cattle_items', 'fk' => 'purchase_cattle_id', 'sumNew' => 'qty', 'sumLabel' => 'ekor',
            'known' => fn ($l) => $classNames > 0,
            'legacySum' => fn ($l) => (float) $l['qty'],
        ]);
    }

    /**
     * Hutang riwayat: satu hutang untuk tiap penerimaan hasil impor yang tanggal terimanya sebelum --before, semuanya lunas (saldo 0), dan nilainya
     * sama dengan rumus aplikasi baru (jumlah subtotal item + PPN supplier PKP). Sisi "legacy" di sini adalah harapan dari penerimaan hasil impor.
     */
    private function checkHutang(): void
    {
        $before = (string) $this->option('before');
        $expectedCount = 0;
        $zeroValue = 0;
        $expectedAmount = 0.0;
        $actualCount = 0;
        $actualAmount = 0.0;
        $actualBalance = 0.0;

        foreach ([
            ['belanja', 'grraw', 'goods_receipt_materials', 'goods_receipt_material_items', 'goods_receipt_material_id', \App\Models\GoodsReceiptMaterial::class],
            ['belanjabeef', 'grbeef', 'goods_receipt_products', 'goods_receipt_product_items', 'goods_receipt_product_id', \App\Models\GoodsReceiptProduct::class],
        ] as [$module, $head, $target, $items, $fk, $class]) {
            $rows = $this->snap($module, $head)->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idgr')->values();
            $numbers = $this->numbersOf($rows, 'idgr', 'grnumber');
            $ids = $this->newIdsByNumber($target, 'gr_number', $numbers);

            foreach (array_chunk($ids, 500) as $chunk) {
                $receipts = DB::table($target)->whereIn('id', $chunk)->where('receive_date', '<', $before)->get(['id', 'supplier_id']);
                $subtotals = DB::table($items)->whereIn($fk, $receipts->pluck('id'))->selectRaw($fk.' as gr, sum(subtotal) as total')->groupBy($fk)->pluck('total', 'gr');
                $suppliers = \App\Models\Supplier::withoutGlobalScopes()->whereIn('id', $receipts->pluck('supplier_id')->unique())->get()->keyBy('id');

                foreach ($receipts as $receipt) {
                    $sub = (float) ($subtotals[$receipt->id] ?? 0);
                    $amount = round($sub + (float) ($suppliers[$receipt->supplier_id]?->ppnAtas($sub) ?? 0), 2);

                    // Importer tidak membuat hutang untuk penerimaan bernilai nol (tanpa item atau tanpa harga).
                    if ($amount <= 0) {
                        $zeroValue++;

                        continue;
                    }

                    $expectedCount++;
                    $expectedAmount += $amount;
                }

                $payables = DB::table('payables')->where('payableable_type', $class)->whereIn('payableable_id', $receipts->pluck('id'))->whereNull('deleted_at');
                $actualCount += (clone $payables)->count();
                $actualAmount += (float) (clone $payables)->sum('amount');
                $actualBalance += (float) (clone $payables)->sum('balance');
            }
        }

        $this->add('Hutang', "hutang riwayat (penerimaan sebelum {$before})", $expectedCount, $actualCount, $zeroValue > 0 ? "penerimaan bernilai Rp 0 tidak dibuatkan hutang: {$zeroValue}" : '');
        $this->add('Hutang', 'nilai hutang (Rp): subtotal item + PPN supplier PKP', $expectedAmount, $actualAmount);
        $this->add('Hutang', 'saldo hutang (Rp): semua riwayat dianggap lunas', 0, $actualBalance);
    }

    private function report(): int
    {
        $differs = 0;
        $module = '';

        foreach ($this->rows as [$mod, $label, $legacy, $new, $note]) {
            if ($mod !== $module) {
                $this->line('');
                $this->line("== {$mod}");
                $module = $mod;
            }

            $same = abs($legacy - $new) < 0.01;
            $differs += $same ? 0 : 1;
            $fmt = fn (float $n) => number_format($n, floor($n) == $n ? 0 : 2, ',', '.');
            $this->line(sprintf('  %-52s legacy %16s | baru %16s | %s', $label, $fmt($legacy), $fmt($new), $same ? 'COCOK' : 'SELISIH '.$fmt($new - $legacy)).($note !== '' ? "  ({$note})" : ''));
        }

        $this->line('');
        $this->line($differs === 0 ? 'HASIL: SEMUA COCOK ('.count($this->rows).' pemeriksaan).' : "HASIL: ADA SELISIH di {$differs} dari ".count($this->rows).' pemeriksaan.');

        return $differs === 0 ? self::SUCCESS : self::FAILURE;
    }
}
