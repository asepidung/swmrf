<?php

namespace App\Console\Commands;

use App\Models\CostingPriceDefault;
use App\Models\Product;
use App\Services\CostingPrices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell\FormulaCell;

/**
 * Isi awal harga patokan costing (issue #541): membaca sheet costing terbaru (kolom E = produk,
 * H = NET, I = GROSS) dan menyimpan gross + net per produk ke `costing_price_defaults`.
 *
 * Sekali jalan. Tanpa `--apply` hanya laporan (kode yang sama, dijalankan di transaksi yang
 * dibatalkan). Produk dicocokkan menurut nama ternormalisasi (huruf besar, spasi dirapikan);
 * yang tidak ketemu dilaporkan, tidak ditebak.
 */
class ImportCostingPrices extends Command
{
    protected $signature = 'costing:import-prices
        {--source=legacy/costing-oktober-2026.xlsx : Berkas xlsx costing (tidak masuk repo)}
        {--sheet=LJP 15 - 8 OKT : Nama sheet}
        {--apply : Simpan (tanpa ini hanya laporan)}';

    protected $description = 'Isi awal harga patokan costing (gross + net per produk) dari sheet costing terbaru';

    /** @var array<int, string> */
    private array $matched = [];

    /** @var array<int, string> */
    private array $notes = [];

    /** @var array<int, string> */
    private array $flagged = [];

    /** @var array<int, string> */
    private array $unmatched = [];

    public function handle(): int
    {
        $path = base_path((string) $this->option('source'));

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readSheet($path, (string) $this->option('sheet'));

        if ($rows === null) {
            $this->error('Sheet "'.$this->option('sheet').'" tidak ada di berkas.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $products = $this->productMap();

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $productId = $products[$row['name']] ?? null;

                if (! $productId) {
                    $this->unmatched[] = "{$row['name']} (gross ".number_format($row['gross'], 2, ',', '.').', net '.number_format($row['net'], 2, ',', '.').')';

                    continue;
                }

                CostingPriceDefault::updateOrCreate(
                    ['product_id' => $productId],
                    ['gross_price' => $row['gross'], 'net_price' => $row['net'], 'source' => 'import'],
                );

                $discount = $row['gross'] > 0 ? round((1 - $row['net'] / $row['gross']) * 100, 2) : 0;
                $this->matched[] = sprintf('%-28s gross %14s  net %14s  potongan %6s%%', $row['name'], number_format($row['gross'], 2, ',', '.'), number_format($row['net'], 2, ',', '.'), number_format($discount, 2, ',', '.'));
            }

            // Pengisi awal penanda "tidak ikut kg overhead": hanya produk bernama OFFAL / KULIT / HIDES.
            foreach (['OFFAL', 'KULIT', 'HIDES'] as $name) {
                if (isset($products[$name])) {
                    Product::whereKey($products[$name])->update(['excluded_from_overhead' => true]);
                    $this->flagged[] = $name;
                }
            }

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->line($apply ? 'DITERAPKAN' : 'DRY-RUN (tidak ada yang disimpan; tambahkan --apply)');
        $this->line('Cocok: '.count($this->matched));
        array_map(fn ($line) => $this->line('  '.$line), $this->matched);

        $this->line('Ditandai "tidak ikut kg overhead": '.($this->flagged === [] ? '(tidak ada produk bernama OFFAL/KULIT/HIDES)' : implode(', ', $this->flagged)));

        foreach ($this->notes as $note) {
            $this->warn($note);
        }

        if ($this->unmatched !== []) {
            $this->warn('Tidak ada di master produk ('.count($this->unmatched).'):');
            array_map(fn ($line) => $this->line('  '.$line), $this->unmatched);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, gross: float, net: float}>|null
     */
    private function readSheet(string $path, string $sheetName): ?array
    {
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($path);
        $rows = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            $rows = [];
            $seen = [];
            $started = false;

            foreach ($sheet->getRowIterator() as $number => $row) {
                $cells = array_map(
                    fn ($cell) => $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue(),
                    $row->getCells(),
                );

                $name = $this->normalize((string) ($cells[4] ?? ''));

                // Data dimulai setelah baris kepala kolom ("PRODUCTS" di kolom E); judul di atasnya dilewati.
                if (! $started) {
                    $started = $name === 'PRODUCTS';

                    continue;
                }
                $net = $cells[7] ?? null;
                $gross = $cells[8] ?? null;

                if ($name === '') {
                    continue;
                }

                $gross = is_numeric($gross) ? (float) $gross : 0.0;

                if (! is_numeric($net) || (float) $net <= 0) {
                    if ($gross > 0) {
                        $net = $gross;
                        $this->notes[] = "Baris {$number} {$name}: tanpa net, net = gross.";
                    } else {
                        continue;
                    }
                }

                if (isset($seen[$name])) {
                    $this->notes[] = "Baris {$number} {$name}: nama kembar di sheet, baris ini menggantikan baris {$seen[$name]}.";
                }
                $seen[$name] = $number;

                $rows[$name] = ['name' => $name, 'gross' => round($gross, 2), 'net' => CostingPrices::roundNet((float) $net)];
            }
        }

        $reader->close();

        return $rows === null ? null : array_values($rows);
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
