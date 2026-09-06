<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang posisi stok material dari buku besar pergerakannya.
 *
 * Pasangan `stock:reconcile` untuk sisi bahan penolong. Perintah yang lama
 * hanya memeriksa daging, padahal bahan penolong punya buku besarnya sendiri
 * -- dan kalau buku besar itu bolong, tidak ada yang memberitahu.
 *
 * **Bentuk datanya berbeda dari sisi daging, dan bedanya penting.**
 *
 * Stok daging dicatat per BARCODE: satu baris satu potong, dan barisnya
 * dihapus begitu barangnya keluar. Stok material dicatat per JENIS: satu
 * baris satu material, dengan satu angka `qty` yang disunting naik-turun.
 * Barisnya tidak pernah dihapus.
 *
 * Akibatnya dua hal:
 *
 *  - Tidak ada dimensi gudang maupun grade. Perbandingannya cukup per
 *    material.
 *  - "Barang tanpa jejak masuk" tidak bisa dicari lewat barcode. Yang
 *    setara di sini adalah material yang `qty`-nya lebih besar daripada
 *    saldo buku besarnya -- selisih itulah stok yang sudah ada sebelum
 *    buku besarnya mulai mencatat.
 *
 * Perintah ini HANYA MEMBACA. Tidak ada satu pun penulisan di dalamnya, jadi
 * aman dijalankan di server kapan saja.
 */
class ReconcileMaterialStock extends Command
{
    protected $signature = 'stock:reconcile-material
                            {--date= : Hitung posisi sampai akhir tanggal ini (YYYY-MM-DD), tanpa membandingkan}
                            {--limit=40 : Jumlah baris terbanyak yang dicetak}';

    protected $description = 'Menghitung ulang posisi stok material dari material_stock_movements dan membandingkannya dengan material_stocks';

    public function handle(): int
    {
        if ($tanggal = $this->option('date')) {
            return $this->posisiPadaTanggal($tanggal);
        }

        $this->info('Menghitung ulang posisi stok material dari buku besar pergerakan...');
        $this->newLine();

        $this->laporkanBahan();

        $bukuBesar = $this->hitungUlang();
        $sekarang = $this->stokSekarang();

        $kunci = array_unique(array_merge(array_keys($bukuBesar), array_keys($sekarang)));
        sort($kunci);

        $meleset = [];
        $selisihTotal = 0.0;

        foreach ($kunci as $satu) {
            $dariBuku = round($bukuBesar[$satu]['qty'] ?? 0.0, 2);
            $dariStok = round($sekarang[$satu]['qty'] ?? 0.0, 2);
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

        $this->line('Material diperiksa      : ' . count($kunci));
        $this->line('Yang meleset            : ' . count($meleset));
        $this->line('Jumlah selisih mutlak   : ' . number_format($selisihTotal, 2));
        $this->newLine();

        if ($meleset !== []) {
            $this->table(
                ['Material', 'Buku besar', 'Stok', 'Selisih'],
                array_slice($meleset, 0, (int) $this->option('limit')),
            );

            $sisa = count($meleset) - (int) $this->option('limit');

            if ($sisa > 0) {
                $this->line('... dan ' . $sisa . ' baris lagi.');
            }

            $this->newLine();
        }

        $this->laporkanSaldoNegatif();

        if ($meleset === []) {
            $this->info('BERSIH. Hitung ulang dari buku besar sama persis dengan isi stok material.');

            return self::SUCCESS;
        }

        $this->warn('MELESET. Buku besar material belum bisa dipakai untuk posisi tanggal mundur');
        $this->warn('sampai selisih di atas dijelaskan.');
        $this->newLine();
        $this->line('Selisih yang POSITIF berarti buku besarnya lebih besar daripada stoknya:');
        $this->line('ada yang menyunting `qty` tanpa mencatat pergerakannya.');
        $this->line('Selisih yang NEGATIF berarti stoknya lebih besar: kemungkinan besar itu');
        $this->line('stok awal yang sudah ada sebelum buku besarnya mulai mencatat.');

        return self::FAILURE;
    }

    /**
     * Berapa banyak bahan yang dibaca perintah ini.
     *
     * "Bersih" tanpa menyebut ukurannya adalah kalimat yang terdengar
     * meyakinkan padahal belum tentu berarti apa-apa: buku besar dengan dua
     * baris memang selalu cocok.
     */
    private function laporkanBahan(): void
    {
        $gerakan = DB::table('material_stock_movements')->count();
        $stok = DB::table('material_stocks')->count();

        $rentang = DB::table('material_stock_movements')
            ->selectRaw('MIN(created_at) as awal, MAX(created_at) as akhir')
            ->first();

        $this->line('Baris pergerakan dibaca : ' . number_format($gerakan));

        if ($gerakan > 0 && $rentang?->awal) {
            $this->line('Rentang catatannya      : '
                . Carbon::parse($rentang->awal)->format('d M Y')
                . '  s/d  ' . Carbon::parse($rentang->akhir)->format('d M Y'));
        }

        $this->line('Baris stok material     : ' . number_format($stok));
        $this->newLine();

        if ($gerakan < 100) {
            $this->warn('Bahannya masih sedikit. Hasil "bersih" di bawah ini benar untuk data');
            $this->warn('yang ada, tetapi belum cukup untuk menyimpulkan buku besarnya sehat');
            $this->warn('pada pemakaian yang sesungguhnya. Jalankan lagi setelah datanya banyak.');
            $this->newLine();
        }
    }

    /** Posisi stok material pada satu tanggal, tanpa membandingkan apa pun. */
    private function posisiPadaTanggal(string $tanggal): int
    {
        try {
            $batas = Carbon::parse($tanggal)->endOfDay();
        } catch (\Throwable) {
            $this->error('Tanggalnya tidak terbaca. Pakai bentuk YYYY-MM-DD.');

            return self::INVALID;
        }

        $baris = $this->hitungUlang($batas);

        $this->info('Posisi stok material sampai ' . $batas->format('d M Y H:i'));
        $this->newLine();

        // Peringatan yang sama dengan sisi daging, dan sama pentingnya.
        $this->warn('Tanggalnya WAKTU INPUT, bukan tanggal dokumen.');
        $this->line('`material_stock_movements` hanya punya `created_at`. Barang yang datang');
        $this->line('Senin tetapi baru diinput Selasa terhitung di hari Selasa.');
        $this->newLine();

        $isi = [];
        $total = 0.0;

        foreach ($baris as $satu) {
            if (abs($satu['qty']) < 0.005) {
                continue;
            }

            $isi[] = [$satu['nama'], number_format($satu['qty'], 2)];
            $total += $satu['qty'];
        }

        usort($isi, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $this->table(['Material', 'Qty'], array_slice($isi, 0, (int) $this->option('limit')));

        $sisa = count($isi) - (int) $this->option('limit');

        if ($sisa > 0) {
            $this->line('... dan ' . $sisa . ' baris lagi.');
        }

        $this->newLine();
        $this->line('Total: ' . number_format($total, 2) . ' dalam ' . count($isi) . ' material.');

        return self::SUCCESS;
    }

    /**
     * Saldo buku besar per material.
     *
     * Dihitung dari `qty_in` dikurangi `qty_out`, BUKAN dari kolom `balance`.
     * `balance` adalah saldo yang dicatat pada saat baris itu ditulis; kalau
     * ada satu baris yang tidak tercatat, `balance` baris sesudahnya ikut
     * salah tanpa menunjukkan gejala. Menjumlah masuk dan keluar sendiri
     * membuat perintah ini benar-benar memeriksa, bukan mengulang jawaban
     * yang sudah tersimpan.
     *
     * @return array<int, array{nama: string, qty: float}>
     */
    private function hitungUlang(?Carbon $sampai = null): array
    {
        $query = DB::table('material_stock_movements as m')
            ->leftJoin('materials as mt', 'mt.id', '=', 'm.material_id')
            ->selectRaw('m.material_id')
            ->selectRaw('MAX(mt.code) as kode, MAX(mt.name) as material')
            ->selectRaw('COALESCE(SUM(m.qty_in), 0) - COALESCE(SUM(m.qty_out), 0) as qty')
            ->groupBy('m.material_id');

        if ($sampai) {
            $query->where('m.created_at', '<=', $sampai);
        }

        $hasil = [];

        foreach ($query->get() as $baris) {
            $hasil[(int) $baris->material_id] = [
                'nama' => $this->nama($baris),
                'qty' => (float) $baris->qty,
            ];
        }

        return $hasil;
    }

    /**
     * Isi tabel stok material saat ini.
     *
     * @return array<int, array{nama: string, qty: float}>
     */
    private function stokSekarang(): array
    {
        $baris = DB::table('material_stocks as s')
            ->leftJoin('materials as mt', 'mt.id', '=', 's.material_id')
            ->selectRaw('s.material_id')
            ->selectRaw('MAX(mt.code) as kode, MAX(mt.name) as material')
            ->selectRaw('COALESCE(SUM(s.qty), 0) as qty')
            ->groupBy('s.material_id')
            ->get();

        $hasil = [];

        foreach ($baris as $satu) {
            $hasil[(int) $satu->material_id] = [
                'nama' => $this->nama($satu),
                'qty' => (float) $satu->qty,
            ];
        }

        return $hasil;
    }

    /**
     * Saldo yang tercatat MINUS di dalam buku besarnya sendiri.
     *
     * Sejak penolakan stok minus dipasang, keadaan ini seharusnya tidak bisa
     * lahir lagi. Yang dicari di sini baris LAMA yang terlanjur ada sebelum
     * penjagaan itu dipasang -- dan kalau ada yang baru, berarti ada jalur
     * yang menulis stok tanpa lewat `StockService`.
     */
    private function laporkanSaldoNegatif(): void
    {
        $negatif = $this->saldoNegatif();

        if ($negatif->isEmpty()) {
            $this->line('Saldo tercatat minus    : tidak ada.');
            $this->newLine();

            return;
        }

        $this->warn('Saldo tercatat minus    : ' . $negatif->count() . ' baris.');
        $this->line('Sejak penolakan stok minus dipasang, ini seharusnya tidak bisa lahir lagi.');
        $this->line('Kalau tanggalnya baru, ada jalur yang menulis stok tanpa lewat StockService.');

        $this->table(
            ['Material', 'Balance', 'Tanggal'],
            $negatif->take(10)->map(fn ($satu): array => [
                trim(($satu->kode ?? '?') . ' ' . ($satu->material ?? '?')),
                number_format((float) $satu->balance, 2),
                Carbon::parse($satu->created_at)->format('d M Y'),
            ])->all(),
        );

        $this->newLine();
    }

    private function saldoNegatif(): Collection
    {
        return DB::table('material_stock_movements as m')
            ->leftJoin('materials as mt', 'mt.id', '=', 'm.material_id')
            ->where('m.balance', '<', 0)
            ->orderByDesc('m.created_at')
            ->selectRaw('m.balance, m.created_at, mt.code as kode, mt.name as material')
            ->get();
    }

    private function nama(object $baris): string
    {
        return trim(($baris->kode ?? '?') . ' ' . ($baris->material ?? '?'));
    }
}
