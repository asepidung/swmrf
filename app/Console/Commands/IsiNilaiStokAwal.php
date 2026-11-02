<?php

namespace App\Console\Commands;

use App\Models\BarcodeCost;
use App\Models\Product;
use App\Support\BarcodeCosts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi nilai awal stok (beef_stocks IN_STOCK) dari daftar harga per produk (Owner, 2 November 2026):
 * harga terbaru dari sheet costing Januari-Oktober 2026 + Rp3.000, dibulatkan naik ke Rp100,
 * produk yang tidak ada di sheet diisi Owner. Daftar harga dibaca dari CSV (produk, harga_per_kg).
 *
 * Sekali jalan. Tanpa `--apply` hanya laporan (kode yang sama, dijalankan di transaksi yang dibatalkan).
 * Barcode yang sudah bernilai tidak ditimpa, dan hasil repack dibiarkan (nilainya milik valuasi repack).
 * Setelah barcode bernilai, turunannya (relabel dan repack terkunci) dihitung ulang lewat `BarcodeCosts::propagateFrom`.
 */
class IsiNilaiStokAwal extends Command
{
    protected $signature = 'costing:isi-stok-awal
        {--source=legacy/harga-stok-awal.csv : CSV harga per produk (kolom produk, harga_per_kg; tidak masuk repo)}
        {--apply : Simpan (tanpa ini hanya laporan)}';

    protected $description = 'Isi nilai awal stok IN_STOCK dari daftar harga per produk, lalu hitung ulang turunannya';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $path = is_file($source) ? $source : base_path($source);

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $prices = $this->readPrices($path);
        $products = $this->productMap();
        $productNames = Product::pluck('name', 'id')->map(fn ($name) => $this->normalize($name))->all();
        $apply = (bool) $this->option('apply');

        $lines = [];
        $unknown = [];
        $valued = [];
        $changedByPropagation = 0;
        $totalKg = 0.0;
        $totalValue = 0.0;

        DB::beginTransaction();

        try {
            foreach ($prices as $name => $price) {
                $productId = $products[$name] ?? null;

                if (! $productId) {
                    $unknown[] = $name;

                    continue;
                }

                $rows = DB::table('beef_stocks as s')
                    ->leftJoin('barcode_costs as c', 'c.barcode', '=', 's.barcode')
                    ->where('s.product_id', $productId)
                    ->where('s.status', 'IN_STOCK')
                    ->where(function ($q) {
                        $q->whereNull('c.id')
                            ->orWhere(fn ($q) => $q->whereNull('c.cost_per_kg')->where('c.source_type', '!=', BarcodeCost::SOURCE_REPACK));
                    })
                    ->orderBy('s.id')
                    ->get(['s.barcode', 's.weight']);

                $kg = 0.0;

                foreach ($rows as $row) {
                    BarcodeCosts::set($row->barcode, $price, BarcodeCost::SOURCE_OPENING, null, 'harga awal costing Okt 2026', $productId);
                    $valued[] = $row->barcode;
                    $kg += (float) $row->weight;
                }

                if ($rows->isNotEmpty()) {
                    $lines[] = sprintf('%-30s %4d barcode %10s kg x %12s = %16s', $name, $rows->count(), number_format($kg, 2, ',', '.'), number_format($price, 0, ',', '.'), number_format($kg * $price, 0, ',', '.'));
                    $totalKg += $kg;
                    $totalValue += $kg * $price;
                }
            }

            // Efek domino: barang yang sudah keluar dari stok sejak go-live (bahan repack, tally/penjualan) juga belum
            // bernilai; nilai mereka memakai harga produk yang sama, supaya valuasi repack dan laporan HPP penjualan terisi.
            $pernahKeluar = DB::table('beef_stock_movements')
                ->whereIn('transaction_type', ['OUT_TO_REPACK', 'TALLY'])
                ->whereNotNull('barcode')
                ->select('barcode', 'product_id')
                ->union(DB::table('repack_materials')->select('barcode', 'product_id'))
                ->get()
                ->unique('barcode');

            $hasilRepack = DB::table('repack_results')->pluck('barcode')->flip();
            $sudahBernilai = DB::table('barcode_costs')->whereNotNull('cost_per_kg')->pluck('barcode')->flip();
            $domino = ['barcode' => 0, 'kg' => 0.0, 'nilai' => 0.0];
            $dominoPerProduk = [];

            foreach ($pernahKeluar as $row) {
                $name = $productNames[$row->product_id] ?? null;

                if ($name === null || ! isset($prices[$name]) || isset($sudahBernilai[$row->barcode]) || isset($hasilRepack[$row->barcode])) {
                    continue;
                }

                BarcodeCosts::set($row->barcode, $prices[$name], BarcodeCost::SOURCE_OPENING, null, 'harga awal costing Okt 2026 (sudah keluar dari stok)', (int) $row->product_id);
                $valued[] = $row->barcode;
                $domino['barcode']++;
                $dominoPerProduk[$name] = ($dominoPerProduk[$name] ?? 0) + 1;
            }

            foreach ($valued as $barcode) {
                $changedByPropagation += BarcodeCosts::propagateFrom($barcode);
            }

            $hasilBernilai = DB::table('repack_valuations')->where('status', '!=', 'pending')->count();

            $left = DB::table('beef_stocks as s')
                ->join('products as p', 'p.id', '=', 's.product_id')
                ->leftJoin('barcode_costs as c', 'c.barcode', '=', 's.barcode')
                ->where('s.status', 'IN_STOCK')
                ->where(fn ($q) => $q->whereNull('c.id')->orWhereNull('c.cost_per_kg'))
                ->groupBy('p.name')
                ->orderByDesc(DB::raw('SUM(s.weight)'))
                ->get(['p.name', DB::raw('COUNT(*) as n'), DB::raw('SUM(s.weight) as kg'), DB::raw('MAX(c.source_type) as sumber')]);

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->line($apply ? 'DITERAPKAN' : 'DRY-RUN (tidak ada yang disimpan; tambahkan --apply)');
        $this->line('Barcode diisi: '.count($valued).sprintf(' | %s kg | nilai Rp %s', number_format($totalKg, 2, ',', '.'), number_format($totalValue, 0, ',', '.')));
        array_map(fn ($line) => $this->line('  '.$line), $lines);
        $this->line('Barang yang sudah keluar (bahan repack, tally) ikut diisi: '.$domino['barcode'].' barcode');
        $this->line("Turunan yang nilainya berubah lewat rambatan (relabel/repack): {$changedByPropagation} | valuasi repack selesai: {$hasilBernilai}");

        if ($unknown !== []) {
            $this->warn('Produk di CSV tidak ada di master produk ('.count($unknown).'): '.implode(', ', $unknown));
        }

        $this->line('Masih belum bernilai sesudahnya (IN_STOCK): '.$left->sum('n').' barcode, '.number_format((float) $left->sum('kg'), 2, ',', '.').' kg');
        $left->each(fn ($row) => $this->line(sprintf('  %-30s %4d barcode %10s kg  %s', $row->name, $row->n, number_format((float) $row->kg, 2, ',', '.'), $row->sumber ?? '(tanpa baris nilai)')));

        return self::SUCCESS;
    }

    /** @return array<string, float> nama ternormalisasi => harga per kg */
    private function readPrices(string $path): array
    {
        $prices = [];
        $handle = fopen($path, 'r');
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (blank($row[0] ?? null) || ! is_numeric($row[1] ?? null)) {
                continue;
            }

            $prices[$this->normalize($row[0])] = (float) $row[1];
        }

        fclose($handle);

        return $prices;
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    /** @return array<string, int> nama ternormalisasi => product id */
    private function productMap(): array
    {
        $map = [];

        foreach (Product::orderBy('id')->get(['id', 'name']) as $product) {
            $map[$this->normalize($product->name)] ??= $product->id;
        }

        return $map;
    }
}
