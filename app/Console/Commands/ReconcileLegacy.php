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
 * Modul: stok, boning, so, tally, do, invoice, repack, retur. `--dir` berisi snapshot hasil `legacy/tarik/tarik.sh` (berkas SWM-<modul>-*.sql terbaru dipakai).
 */
class ReconcileLegacy extends Command
{
    protected $signature = 'legacy:reconcile
                            {--dir= : Folder berisi snapshot legacy (SWM-<modul>-*.sql)}
                            {--only= : Hanya modul ini (pisah koma): stok,boning,so,tally,do,invoice,repack,retur}';

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
        $all = ['stok', 'boning', 'so', 'tally', 'do', 'invoice', 'repack', 'retur'];
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
        $bonings = $this->snap('sapi', 'boning');
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
