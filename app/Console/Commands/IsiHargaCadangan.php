<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Mengisi `products.fallback_cost_per_kg` dari daftar harga per produk (Owner, 9 November 2026), CSV: produk, harga_per_kg.
 *
 * Tanpa `--apply` hanya laporan. Produk yang sudah punya harga cadangan tidak ditimpa kecuali `--timpa`.
 * Hanya mengubah kolom itu: tidak menyentuh harga barcode mana pun.
 */
class IsiHargaCadangan extends Command
{
    protected $signature = 'costing:isi-harga-cadangan
        {--source=legacy/harga-stok-awal.csv : CSV harga per produk (kolom produk, harga_per_kg)}
        {--timpa : Timpa harga cadangan yang sudah ada}
        {--apply : Simpan (tanpa ini hanya laporan)}';

    protected $description = 'Isi harga cadangan per kg produk dari daftar harga';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $path = is_file($source) ? $source : base_path($source);

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $map = [];
        foreach (Product::orderBy('id')->get(['id', 'name', 'fallback_cost_per_kg']) as $product) {
            $map[$this->normalize($product->name)] ??= $product;
        }

        $apply = (bool) $this->option('apply');
        $filled = $skipped = 0;
        $unknown = [];

        $handle = fopen($path, 'r');
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (blank($row[0] ?? null) || ! is_numeric($row[1] ?? null)) {
                continue;
            }

            $product = $map[$this->normalize($row[0])] ?? null;

            if (! $product) {
                $unknown[] = $row[0];

                continue;
            }

            if ($product->fallback_cost_per_kg !== null && ! $this->option('timpa')) {
                $skipped++;

                continue;
            }

            if ($apply) {
                $product->forceFill(['fallback_cost_per_kg' => (float) $row[1]])->save();
            }

            $filled++;
        }

        fclose($handle);

        $this->info(($apply ? 'Diisi' : 'Akan diisi').": {$filled} produk; dilewati (sudah ada): {$skipped}.");
        if ($unknown !== []) {
            $this->warn('Produk tidak ditemukan: '.implode(', ', array_unique($unknown)));
        }
        if (! $apply) {
            $this->comment('Hanya laporan. Tambahkan --apply untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}
