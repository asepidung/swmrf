<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use Illuminate\Console\Command;

/**
 * Tiga rekening bank perusahaan (data dari finance lewat Owner, 27 Oktober 2026). Rekening tidak ikut
 * snapshot master, jadi perintah ini dijalankan di tiap lingkungan, termasuk hari-H. Dicocokkan lewat
 * nomor rekening, aman diulang, dry-run bawaan; `--apply` menulis. Tidak mengubah rekening yang sudah ada.
 */
class SeedCompanyBankAccounts extends Command
{
    protected $signature = 'bank-accounts:seed-company {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Buat rekening bank perusahaan (BNI, BCA, Nobu) bila belum ada (dry-run bawaan, aman diulang)';

    /** @var array<int, array{initial: string, bank_name: string, account_number: string, account_holder: string}> */
    public const ACCOUNTS = [
        ['initial' => 'BNI PT', 'bank_name' => 'Bank Negara Indonesia', 'account_number' => '8585889991', 'account_holder' => 'Santi Wijaya Meat PT'],
        ['initial' => 'BCA PT', 'bank_name' => 'Bank Central Asia', 'account_number' => '7115534882', 'account_holder' => 'Santi Wijaya Meat PT'],
        ['initial' => 'NOBU', 'bank_name' => 'Nobu National Bank', 'account_number' => '16830193333', 'account_holder' => 'Santi Wijaya Meat PT'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');

        foreach (self::ACCOUNTS as $account) {
            if (BankAccount::where('account_number', $account['account_number'])->exists()) {
                $this->line("  = {$account['initial']} sudah ada");

                continue;
            }

            $this->line("  + {$account['initial']} ({$account['bank_name']} {$account['account_number']})");

            if ($apply) {
                BankAccount::create($account + ['is_active' => true]);
            }
        }

        return self::SUCCESS;
    }
}
