<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LG-01: menulis baris pembuka buku besar untuk stok awal hasil impor.
 *
 * Stok awal ditulis ke `beef_stocks` tanpa baris pergerakan MASUK. Saat barangnya keluar, baris KELUAR tercatat, sehingga
 * posisi stok pada tanggal lampau meleset (bisa minus). Perintah ini menulis satu baris `OPENING_BALANCE` per barcode
 * yang tidak punya satu pun baris masuk, sebesar selisih antara isi stok sekarang dan hasil buku besar barcode itu.
 *
 * Dry-run bawaan; `--apply` menulis. Aman diulang: barcode yang sudah punya baris masuk (termasuk pembukanya) dilewati.
 */
class WriteOpeningStockMovements extends Command
{
    protected $signature = 'stock:write-opening
                            {--date= : Tanggal pembuka (YYYY-MM-DD), biasanya tanggal go-live}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Tulis baris pembuka buku besar untuk stok awal tanpa jejak masuk (dry-run bawaan)';

    public function handle(): int
    {
        if (! $this->option('date') || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->option('date'))) {
            $this->error('--date=YYYY-MM-DD wajib diisi.');

            return self::FAILURE;
        }

        $tanggal = Carbon::parse($this->option('date'))->startOfDay();

        $user = User::query()->where('role', 'programmer')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();

        if (! $user) {
            $this->error('Tidak ada pengguna untuk dicatat sebagai pembuat baris.');

            return self::FAILURE;
        }

        $tanpaMasuk = DB::table('beef_stock_movements')
            ->select('barcode')
            ->whereNotNull('barcode')
            ->groupBy('barcode')
            ->havingRaw('SUM(CASE WHEN weight_in > 0 THEN 1 ELSE 0 END) = 0')
            ->pluck('barcode');

        $stokTanpaGerakan = DB::table('beef_stocks as s')
            ->where('s.status', 'IN_STOCK')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('beef_stock_movements as m')->whereColumn('m.barcode', 's.barcode'))
            ->pluck('s.barcode');

        $barcodes = $tanpaMasuk->merge($stokTanpaGerakan)->unique()->values();
        $baris = [];

        foreach ($barcodes->chunk(500) as $potong) {
            $stok = DB::table('beef_stocks')->whereIn('barcode', $potong)->where('status', 'IN_STOCK')->get()->keyBy('barcode');
            $gerak = DB::table('beef_stock_movements')->whereIn('barcode', $potong)->orderBy('id')->get()->groupBy('barcode');

            foreach ($potong as $barcode) {
                $s = $stok->get($barcode);
                $g = $gerak->get($barcode, collect());
                $pertama = $g->first();

                $sekarang = $s ? (float) $s->weight : 0.0;
                $bersih = (float) $g->sum('weight_in') - (float) $g->sum('weight_out');
                $selisih = round($sekarang - $bersih, 2);

                if ($selisih <= 0) {
                    continue;
                }

                $produk = $s->product_id ?? $pertama?->product_id;
                $gudang = $s->warehouse_id ?? $pertama?->warehouse_id;
                $grade = $s->grade_id ?? $pertama?->condition;

                if (! $produk || ! $gudang || ! $grade) {
                    continue;
                }

                $waktu = $tanggal->copy();

                if ($pertama && Carbon::parse($pertama->created_at)->lt($waktu)) {
                    $waktu = Carbon::parse($pertama->created_at)->subSecond();
                }

                $baris[] = [
                    'product_id' => $produk, 'warehouse_id' => $gudang, 'condition' => (string) $grade, 'barcode' => $barcode,
                    'transaction_type' => 'OPENING_BALANCE', 'reference_document' => 'OPENING-'.$tanggal->toDateString(),
                    'weight_in' => $selisih, 'weight_out' => 0, 'pcs_in' => (int) ($s->qty_pcs ?? 0), 'pcs_out' => 0,
                    'note' => 'Baris pembuka stok awal (LG-01)', 'created_by' => $user->id,
                    'created_at' => $waktu, 'updated_at' => $waktu,
                ];
            }
        }

        $kg = array_sum(array_column($baris, 'weight_in'));
        $this->line('Barcode tanpa jejak masuk : '.count($baris).' ('.number_format($kg, 2, ',', '.').' kg)');

        if ($baris === []) {
            $this->info('Tidak ada yang perlu ditulis.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn('DRY-RUN: tidak ada yang ditulis. Jalankan ulang dengan --apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($baris) {
            foreach (array_chunk($baris, 500) as $potong) {
                DB::table('beef_stock_movements')->insert($potong);
            }
        });

        $this->info('Ditulis: '.count($baris).' baris. Jalankan stock:reconcile untuk memeriksa.');

        return self::SUCCESS;
    }
}
