<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuat dokumen hasil impor yang di legacy TIDAK tercatat pembuatnya diganti dari akun cadangan impor (mis. `opik`, QC)
 * menjadi akun netral "Legacy" (keputusan Ayah, 31 Oktober 2026), supaya tidak terbaca seolah seorang karyawan membuat ribuan
 * dokumen. Akun Legacy nonaktif dan bersandi acak: tidak bisa dipakai masuk.
 *
 * Bawaannya empat tabel yang pembuatnya memang tidak ada di legacy atau akunnya tidak ada di aplikasi: `invoices` dan `tallies`
 * (legacy tidak punya kolom pembuat), serta `sales_orders` dan `payables` (pembuat diganti akun cadangan). Keputusan Ayah,
 * 1 November 2026: semua dokumen yang pembuatnya tidak ada di legacy menjadi "Legacy". Diperiksa di VPS sebelum perintah ini
 * dilebarkan: setiap baris `opik` di keempat tabel itu hasil akun cadangan, tidak ada yang dibuat opik sungguhan.
 * Tabel lain tidak diizinkan karena di sana pembuat SUNGGUHAN tercatat dan bisa kebetulan akun yang sama.
 *
 * Dry-run bawaan; `--apply` menulis; aman diulang.
 */
class AssignLegacyCreator extends Command
{
    protected $signature = 'legacy:assign-legacy-creator
        {--from=opik : Username akun cadangan impor yang dilepas dari dokumen}
        {--tables=invoices,tallies,sales_orders,payables : Daftar tabel dipisah koma (kolom created_by)}
        {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Ganti pembuat dokumen impor tanpa pembuat tercatat menjadi akun "Legacy" (dry-run bawaan, aman diulang)';

    /** Tabel yang boleh disentuh; bukan masukan bebas karena nama tabel masuk ke kueri. */
    private const ALLOWED = ['invoices', 'tallies', 'sales_orders', 'payables'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $from = User::withoutGlobalScopes()->where('username', $this->option('from'))->first();

        if (! $from) {
            $this->error('Akun asal tidak ditemukan: '.$this->option('from'));

            return self::FAILURE;
        }

        $tables = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('tables')))));
        foreach ($tables as $table) {
            if (! in_array($table, self::ALLOWED, true)) {
                $this->error("Tabel tidak diizinkan: {$table} (boleh: ".implode(', ', self::ALLOWED).')');

                return self::FAILURE;
            }
        }

        $legacy = User::withoutGlobalScopes()->where('username', 'legacy')->first();
        $this->line($legacy ? "Akun Legacy sudah ada (id {$legacy->id})." : 'Akun Legacy akan dibuat (nonaktif, tidak bisa login).');

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->where('created_by', $from->id)->count();
        }

        if ($apply) {
            DB::transaction(function () use (&$legacy, $from, $tables): void {
                $legacy ??= User::create([
                    'name' => 'Legacy',
                    'username' => 'legacy',
                    'password' => Str::random(40),
                    'gender' => 'L',
                    'role' => 'employee',
                    'is_active' => false,
                ]);

                foreach ($tables as $table) {
                    DB::table($table)->where('created_by', $from->id)->update(['created_by' => $legacy->id]);
                }
            });
        }

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        foreach ($counts as $table => $n) {
            $this->line("  {$table}: {$n} baris dibuat oleh {$from->username}".($apply ? ' -> Legacy' : ''));
        }

        return self::SUCCESS;
    }
}
