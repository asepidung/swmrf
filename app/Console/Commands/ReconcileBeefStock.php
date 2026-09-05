<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang posisi stok dari buku besar pergerakannya.
 *
 * `beef_stocks` SENGAJA hanya menyimpan keadaan sekarang -- barang yang keluar
 * dihapus barisnya supaya tabelnya tetap ringan. Riwayatnya ada di
 * `beef_stock_movements`. Rancangan itu berarti posisi stok pada tanggal mana
 * pun bisa dihitung ulang, TETAPI hanya sejauh buku besarnya utuh.
 *
 * Dari sisi kode dua hal sudah dipastikan: seluruh 29 titik yang mengubah stok
 * menulis catatan pergerakan, dan baris stok tidak pernah disunting setelah
 * lahir. Yang tidak bisa dijawab kode adalah keadaan DATA yang sudah berjalan
 * -- dan perintah inilah yang menjawabnya.
 *
 * Ujinya satu kalimat: menghitung ulang sampai SEKARANG harus menghasilkan
 * angka yang sama persis dengan isi `beef_stocks`. Kalau cocok, buku besarnya
 * sehat dan posisi tanggal mundur boleh dipercaya. Kalau meleset, selisihnya
 * menunjuk kombinasi mana yang bermasalah.
 *
 * Perintah ini HANYA MEMBACA. Tidak ada satu pun penulisan di dalamnya, jadi
 * aman dijalankan di server kapan saja.
 */
class ReconcileBeefStock extends Command
{
    protected $signature = 'stock:reconcile
                            {--date= : Hitung posisi sampai akhir tanggal ini (YYYY-MM-DD), tanpa membandingkan}
                            {--all : Tampilkan semua baris, bukan hanya yang meleset}
                            {--limit=40 : Jumlah baris terbanyak yang dicetak}';

    protected $description = 'Menghitung ulang posisi stok daging dari beef_stock_movements dan membandingkannya dengan beef_stocks';

    public function handle(): int
    {
        if ($tanggal = $this->option('date')) {
            return $this->posisiPadaTanggal($tanggal);
        }

        $this->info('Menghitung ulang posisi stok dari buku besar pergerakan...');
        $this->newLine();

        $bukuBesar = $this->hitungUlang();
        $sekarang = $this->stokSekarang();

        $kunci = array_unique(array_merge(array_keys($bukuBesar), array_keys($sekarang)));
        sort($kunci);

        $meleset = [];
        $selisihTotal = 0.0;

        foreach ($kunci as $satu) {
            $dariBuku = round($bukuBesar[$satu]['kg'] ?? 0.0, 2);
            $dariStok = round($sekarang[$satu]['kg'] ?? 0.0, 2);
            $selisih = round($dariBuku - $dariStok, 2);

            if (abs($selisih) < 0.005) {
                continue;
            }

            $meleset[] = [
                'nama' => $sekarang[$satu]['nama'] ?? $bukuBesar[$satu]['nama'] ?? $satu,
                'buku' => number_format($dariBuku, 2),
                'stok' => number_format($dariStok, 2),
                'selisih' => number_format($selisih, 2),
            ];

            $selisihTotal += abs($selisih);
        }

        $this->line('Kombinasi produk x gudang x grade diperiksa : ' . count($kunci));
        $this->line('Yang meleset                                : ' . count($meleset));
        $this->line('Jumlah selisih mutlak                       : ' . number_format($selisihTotal, 2) . ' kg');
        $this->newLine();

        if ($meleset !== []) {
            $this->table(
                ['Produk / Gudang / Grade', 'Buku besar', 'Stok', 'Selisih'],
                array_slice($meleset, 0, (int) $this->option('limit')),
            );

            if (count($meleset) > (int) $this->option('limit')) {
                $this->line('... dan ' . (count($meleset) - (int) $this->option('limit')) . ' baris lagi.');
            }

            $this->newLine();
        }

        $tanpaJejak = $this->tanpaJejakMasuk();
        $statusLain = $this->statusSelainInStock();

        $this->laporkanTanpaJejak($tanpaJejak);
        $this->laporkanStatusLain($statusLain);

        if ($meleset === []) {
            $this->info('BERSIH. Hitung ulang dari buku besar sama persis dengan isi stok.');
            $this->line('Posisi stok pada tanggal mundur bisa dipercaya.');

            return self::SUCCESS;
        }

        $this->warn('MELESET. Buku besarnya belum bisa dipakai untuk posisi tanggal mundur');
        $this->warn('sampai selisih di atas dijelaskan.');

        if ($tanpaJejak->isNotEmpty()) {
            $this->newLine();
            $this->line('Perhatikan lebih dulu barang tanpa jejak masuk di atas: barang yang');
            $this->line('sudah ada sebelum sistem ini berjalan memang tidak punya catatan');
            $this->line('masuk, dan itu SENDIRI sudah cukup membuat hitung ulangnya kurang.');
        }

        return self::FAILURE;
    }

    /** Posisi stok pada satu tanggal, tanpa membandingkan apa pun. */
    private function posisiPadaTanggal(string $tanggal): int
    {
        try {
            $batas = Carbon::parse($tanggal)->endOfDay();
        } catch (\Throwable) {
            $this->error('Tanggalnya tidak terbaca. Pakai bentuk YYYY-MM-DD.');

            return self::INVALID;
        }

        $baris = $this->hitungUlang($batas);

        $this->info('Posisi stok sampai ' . $batas->format('d M Y H:i'));
        $this->newLine();

        // Peringatan yang harus ikut setiap kali angka tanggal mundur dicetak.
        $this->warn('Tanggalnya WAKTU INPUT, bukan tanggal dokumen.');
        $this->line('`beef_stock_movements` hanya punya `created_at`. Barang yang datang');
        $this->line('Senin tetapi baru diinput Selasa terhitung di hari Selasa.');
        $this->newLine();

        $isi = [];
        $total = 0.0;

        foreach ($baris as $satu) {
            if (abs($satu['kg']) < 0.005) {
                continue;
            }

            $isi[] = [$satu['nama'], number_format($satu['kg'], 2)];
            $total += $satu['kg'];
        }

        usort($isi, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $this->table(['Produk / Gudang / Grade', 'Kg'], array_slice($isi, 0, (int) $this->option('limit')));

        if (count($isi) > (int) $this->option('limit')) {
            $this->line('... dan ' . (count($isi) - (int) $this->option('limit')) . ' baris lagi.');
        }

        $this->newLine();
        $this->line('Total: ' . number_format($total, 2) . ' kg dalam ' . count($isi) . ' kombinasi.');

        return self::SUCCESS;
    }

    /**
     * Saldo buku besar per produk x gudang x grade.
     *
     * @return array<string, array{nama: string, kg: float}>
     */
    private function hitungUlang(?Carbon $sampai = null): array
    {
        $query = DB::table('beef_stock_movements as m')
            ->leftJoin('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('grades as g', 'g.id', '=', 'm.condition')
            ->selectRaw('m.product_id, m.warehouse_id, m.condition')
            ->selectRaw('MAX(p.code) as kode, MAX(p.name) as produk, MAX(w.name) as gudang, MAX(g.name) as grade')
            ->selectRaw('COALESCE(SUM(m.weight_in), 0) - COALESCE(SUM(m.weight_out), 0) as kg')
            ->groupBy('m.product_id', 'm.warehouse_id', 'm.condition');

        if ($sampai) {
            $query->where('m.created_at', '<=', $sampai);
        }

        $hasil = [];

        foreach ($query->get() as $baris) {
            $hasil[$this->kunci($baris->product_id, $baris->warehouse_id, $baris->condition)] = [
                'nama' => $this->nama($baris),
                'kg' => (float) $baris->kg,
            ];
        }

        return $hasil;
    }

    /**
     * Isi tabel stok saat ini, dihitung sebagaimana layar Stock Overview
     * menghitungnya.
     *
     * @return array<string, array{nama: string, kg: float}>
     */
    private function stokSekarang(): array
    {
        $baris = DB::table('beef_stocks as s')
            ->leftJoin('products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->where('s.status', 'IN_STOCK')
            ->selectRaw('s.product_id, s.warehouse_id, s.grade_id')
            ->selectRaw('MAX(p.code) as kode, MAX(p.name) as produk, MAX(w.name) as gudang, MAX(g.name) as grade')
            ->selectRaw('COALESCE(SUM(s.weight), 0) as kg')
            ->groupBy('s.product_id', 's.warehouse_id', 's.grade_id')
            ->get();

        $hasil = [];

        foreach ($baris as $satu) {
            $hasil[$this->kunci($satu->product_id, $satu->warehouse_id, $satu->grade_id)] = [
                'nama' => $this->nama($satu),
                'kg' => (float) $satu->kg,
            ];
        }

        return $hasil;
    }

    /**
     * Barang yang ada di stok tetapi barcodenya tidak pernah punya catatan
     * masuk.
     *
     * Inilah stok yang sudah ada sebelum sistem ini berjalan. Keberadaannya
     * membuat hitung ulang SELALU kurang, dan kalau tidak dihitung tersendiri
     * ia terbaca seolah buku besarnya bolong -- padahal yang kurang justru
     * titik awalnya.
     */
    private function tanpaJejakMasuk(): \Illuminate\Support\Collection
    {
        return DB::table('beef_stocks as s')
            ->leftJoin('products as p', 'p.id', '=', 's.product_id')
            ->where('s.status', 'IN_STOCK')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('beef_stock_movements as m')
                    ->whereColumn('m.barcode', 's.barcode')
                    ->where('m.weight_in', '>', 0);
            })
            ->selectRaw('s.barcode, s.weight, MAX(p.name) as produk')
            ->groupBy('s.barcode', 's.weight')
            ->get();
    }

    private function statusSelainInStock(): \Illuminate\Support\Collection
    {
        return DB::table('beef_stocks')
            ->where('status', '!=', 'IN_STOCK')
            ->selectRaw('status, COUNT(*) as jumlah, COALESCE(SUM(weight), 0) as kg')
            ->groupBy('status')
            ->get();
    }

    private function laporkanTanpaJejak(\Illuminate\Support\Collection $baris): void
    {
        if ($baris->isEmpty()) {
            $this->line('Barang di stok tanpa catatan masuk : tidak ada.');

            return;
        }

        $kg = $baris->sum(fn ($satu): float => (float) $satu->weight);

        $this->warn('Barang di stok tanpa catatan masuk : ' . $baris->count()
            . ' barcode, ' . number_format($kg, 2) . ' kg.');
        $this->line('Ini stok yang sudah ada sebelum buku besarnya mulai mencatat.');

        $this->table(
            ['Barcode', 'Produk', 'Kg'],
            $baris->take(10)->map(fn ($satu): array => [
                $satu->barcode,
                $satu->produk ?? '-',
                number_format((float) $satu->weight, 2),
            ])->all(),
        );
    }

    private function laporkanStatusLain(\Illuminate\Support\Collection $baris): void
    {
        if ($baris->isEmpty()) {
            $this->line('Baris berstatus selain IN_STOCK    : tidak ada.');
            $this->newLine();

            return;
        }

        $this->warn('Baris berstatus selain IN_STOCK    : ' . $baris->count() . ' status.');
        $this->line('Baris seperti ini tidak dihitung di layar Stock Overview.');

        $this->table(
            ['Status', 'Baris', 'Kg'],
            $baris->map(fn ($satu): array => [
                $satu->status,
                $satu->jumlah,
                number_format((float) $satu->kg, 2),
            ])->all(),
        );
    }

    private function kunci($produk, $gudang, $grade): string
    {
        return (int) $produk . ':' . (int) $gudang . ':' . (int) $grade;
    }

    private function nama(object $baris): string
    {
        return trim(($baris->kode ?? '?') . ' ' . ($baris->produk ?? '?'))
            . ' / ' . ($baris->gudang ?? '?')
            . ' / ' . ($baris->grade ?? '?');
    }
}
