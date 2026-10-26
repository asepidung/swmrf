<?php

namespace App\Console\Commands;

use App\Models\ExpenseCategory;
use App\Support\LooseName;
use Illuminate\Console\Command;

/**
 * Kategori Pengeluaran (Expense) awal, disusun dari contoh petty cash Februari 2026 dari finance (196 baris)
 * dan dari item non-REQUEST di file pemilahan material (operasional, ATK, alat sablon, aset); Owner,
 * 26 Oktober 2026. Kategori Expense BUKAN bagian snapshot master, jadi perintah ini dijalankan di tiap
 * lingkungan (coba, VPS, hari-H). Dry-run bawaan; `--apply` menulis; aman diulang (nama dibandingkan longgar).
 * Kategori lama (ATK, AIR MINUM, dst.) tidak disentuh.
 */
class SeedExpenseCategories extends Command
{
    protected $signature = 'expenses:seed-categories {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Isi kategori Expense awal dari contoh petty cash finance (dry-run bawaan, aman diulang)';

    /**
     * Nama kategori => contoh isinya (hanya untuk laporan dan dokumentasi).
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'Pengiriman & Pengangkutan' => 'Uang jalan driver/helper per trip, tukar faktur, dokumen kirim, kuli bongkar muat',
        'BBM, Tol & Parkir' => 'Bensin, solar, isi e-toll, parkir',
        'Perawatan Kendaraan' => 'Servis, tambal ban, aki, poles, coating, semir, kompon, cleaner mobil',
        'Makan & Konsumsi' => 'Sarapan, snack, roti/kue untuk tamu',
        'Tunjangan Makan Lembur' => 'Makan lembur, makan teknisi',
        'Perlengkapan Produksi' => 'Plastik kemasan kecil, karung, lakban, sarung tangan, jarum, minyak',
        'Alat & Bahan Sablon' => 'Screen, tinta, M4 sablon',
        'Kebersihan & Rumah Tangga' => 'Detergen, sabun, spons, sikat, racun lalat, lampu flycatcher, kanebo, gas',
        'Perlengkapan Kantor (ATK)' => 'Kertas, tinta/cartridge printer, materai, spidol, pulpen, stapler, bindex, baterai',
        'Komunikasi, Internet & IT' => 'Pulsa, paket data GPS, internet, hosting, RAM',
        'Pajak, Retribusi & Perizinan' => 'Retribusi RPH, pajak kendaraan, uji lab, legalitas dokumen',
        'Pengobatan' => 'Obat, tindakan medis karyawan',
        'Perbaikan & Pemeliharaan' => 'Listrik, besi/plat, kawat las, lampu, epoxy/cat, sparepart mesin, pisau rumput',
        'Operasional Perjalanan Dinas' => 'Uang operasional dan rembesan perjalanan dinas',
        'Sewa & Rental' => 'Rental, sewa',
        'Promosi & Percetakan' => 'Brosur, cetakan promosi',
        'Aset & Barang Modal' => 'Laptop, printer, CCTV, router, panel, bak perebusan, box container',
        'Lain-lain' => 'Yang belum punya kategori sendiri',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $existing = ExpenseCategory::withTrashed()->get(['id', 'name', 'is_active', 'deleted_at']);
        $created = $same = [];

        foreach (array_keys(self::CATEGORIES) as $name) {
            $found = $existing->first(fn (ExpenseCategory $c) => LooseName::key($c->name) === LooseName::key($name));

            if ($found) {
                $same[] = "{$name}: sudah ada (id {$found->id})".($found->deleted_at ? ' -- TERHAPUS, tidak dihidupkan' : '').'.';

                continue;
            }

            $created[] = $name;

            if ($apply) {
                ExpenseCategory::create(['name' => $name, 'is_active' => true]);
            }
        }

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line('dibuat: '.count($created).', sudah ada (dilewati): '.count($same));

        foreach ($created as $name) {
            $this->line("  + {$name}");
        }
        foreach ($same as $line) {
            $this->line("  = {$line}");
        }

        return self::SUCCESS;
    }
}
