<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kosongkan data hasil PELATIHAN (transaksi dan master) tetapi pertahankan akses dan data dasar
 * (issue #535, untuk go-live 1 November): users, permissions, user_permissions, push_subscriptions,
 * grades, warehouses, dan bank_accounts (akun kas tetap ada; saldo awal menjadi 0, transaksinya hilang).
 *
 * Dry-run bawaan: hanya laporan per tabel (dihapus/dipertahankan, jumlah baris). `--apply` butuh
 * mengetik nama database. Sesudahnya tabel yang dikosongkan mulai dari id 1 lagi (AUTO_INCREMENT
 * direset), siap untuk import master ber-id legacy dan reservasi rentang id.
 *
 * Setiap tabel HARUS terdaftar di KEEP atau WIPE. Tabel yang belum terdaftar (migrasi baru) membuat
 * perintah menolak jalan -- supaya tabel baru tidak terhapus atau terlewat tanpa ada yang memutuskan.
 */
class ResetTrainingData extends Command
{
    protected $signature = 'app:reset-training-data
                            {--apply : Benar-benar menghapus (default hanya laporan dry-run); butuh mengetik nama database}';

    protected $description = 'Kosongkan data transaksi dan master hasil pelatihan; pertahankan users, hak akses, push, grade, gudang, akun kas';

    /** Tabel yang DIPERTAHANKAN, dengan alasannya. */
    public const KEEP = [
        'users' => 'akun pengguna',
        'permissions' => 'daftar izin',
        'user_permissions' => 'izin tiap pengguna',
        'push_subscriptions' => 'langganan notifikasi perangkat',
        'grades' => 'grade (id terkunci, masuk barcode)',
        'warehouses' => 'gudang',
        'bank_accounts' => 'akun kas (saldo awal direset ke 0)',
        // infrastruktur Laravel/aplikasi
        'migrations' => 'riwayat migrasi',
        'sessions' => 'sesi login',
        'cache' => 'cache',
        'cache_locks' => 'cache',
        'jobs' => 'antrean',
        'job_batches' => 'antrean',
        'failed_jobs' => 'antrean',
        'password_reset_tokens' => 'infrastruktur',
        'settings' => 'pengaturan aplikasi',
        'scheduled_run_marks' => 'penanda cron',
    ];

    /** Tabel yang DIKOSONGKAN. */
    public const WIPE = [
        'legacy_import_map',
        'activity_log', 'bank_transactions', 'barcode_costs', 'beef_stock_movements', 'beef_stocks',
        'boning_carcasses', 'boning_items', 'bonings', 'carcass_items', 'carcasses', 'cattle_classes',
        'cattle_receiving_items', 'cattle_receivings', 'cattle_weighing_items', 'cattle_weighings',
        'costing_cattle', 'costing_items', 'costing_price_defaults', 'costings', 'customer_groups', 'customer_product_specs',
        'customer_segments', 'customers', 'delivery_order_items', 'delivery_order_receipt_items',
        'delivery_order_receipts', 'delivery_orders', 'delivery_plans', 'drivers', 'expense_categories', 'expense_items',
        'expenses', 'exports', 'failed_import_rows', 'financial_losses', 'goods_receipt_material_items',
        'goods_receipt_materials', 'goods_receipt_product_items', 'goods_receipt_products', 'imports',
        'invoice_additional_charges', 'invoice_items', 'invoices', 'material_adjustments',
        'material_categories', 'material_findings', 'material_requisition_items', 'material_requisitions',
        'material_stock_movements', 'material_stock_take_items', 'material_stock_takes', 'material_stocks',
        'material_units', 'material_usage_headers', 'material_usages', 'materials', 'mutation_items',
        'mutations', 'payables', 'payment_allocations', 'payment_deductions', 'payments', 'price_list_items',
        'price_lists', 'product_categories', 'product_materials', 'product_requisition_items',
        'product_requisitions', 'production_bom_snapshots', 'production_material_wastes', 'products',
        'purchase_cattle_items', 'purchase_cattles', 'purchase_material_items', 'purchase_materials',
        'purchase_product_items', 'purchase_products', 'qc_findings', 'qc_reports', 'receivables',
        'repack_materials', 'repack_results', 'repack_valuation_prices', 'repack_valuations', 'repacks',
        'sales_order_items', 'sales_orders', 'sales_return_items', 'sales_return_plan_items',
        'sales_return_plans', 'sales_returns', 'stock_take_items', 'stock_takes', 'supplier_payments',
        'suppliers', 'tallies', 'tally_items', 'vehicles',
    ];

    public function handle(): int
    {
        $database = (string) DB::connection()->getDatabaseName();

        // MySQL/MariaDB: getTables() tanpa skema mengembalikan tabel SEMUA database di server; dibatasi ke
        // database koneksi ini. (SQLite, dipakai tes, tidak punya padanan skema itu.)
        $isMysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $tables = collect($isMysql ? Schema::getTables(schema: $database) : Schema::getTables())->pluck('name')->sort()->values();
        $unclassified = $tables->reject(fn ($t) => isset(self::KEEP[$t]) || in_array($t, self::WIPE, true))->values();

        if ($unclassified->isNotEmpty()) {
            $this->error('Tabel belum diklasifikasikan (tambahkan ke KEEP atau WIPE di ResetTrainingData): '.$unclassified->implode(', '));

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->line($apply ? "<fg=red>--apply</> pada database <fg=yellow>{$database}</>" : "Dry-run pada database <fg=yellow>{$database}</> -- tidak ada yang dihapus.");
        $this->newLine();

        $wipe = $tables->filter(fn ($t) => in_array($t, self::WIPE, true))->values();
        $keep = $tables->filter(fn ($t) => isset(self::KEEP[$t]))->values();

        $counts = $tables->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

        $this->line('<fg=cyan>DIHAPUS</> ('.$wipe->count().' tabel, '.$wipe->sum(fn ($t) => $counts[$t]).' baris):');
        foreach ($wipe as $table) {
            $this->line(sprintf('  - %-36s %8d baris', $table, $counts[$table]));
        }

        $this->newLine();
        $this->line('<fg=green>DIPERTAHANKAN</> ('.$keep->count().' tabel):');
        foreach ($keep as $table) {
            $this->line(sprintf('  = %-36s %8d baris  (%s)', $table, $counts[$table], self::KEEP[$table]));
        }

        if (! $apply) {
            $this->newLine();
            $this->info('Dry-run selesai. Tambahkan --apply untuk menghapus (akan diminta mengetik nama database).');

            return self::SUCCESS;
        }

        $typed = (string) $this->ask("Ketik nama database ({$database}) untuk melanjutkan; kosongkan untuk batal");

        if (trim($typed) !== $database) {
            $this->error('Nama database tidak cocok. DIBATALKAN, tidak ada yang dihapus.');

            return self::FAILURE;
        }

        $this->wipe($wipe->all());

        // Akun kas tetap ada; saldo awalnya ikut direset (transaksinya sudah hilang).
        DB::table('bank_accounts')->update(['initial' => 0]);

        $this->newLine();
        $this->info('Selesai: '.$wipe->count().' tabel dikosongkan dan id-nya mulai dari 1 lagi; saldo awal akun kas = 0.');
        $this->line('Berikutnya: import master ber-id legacy, lalu reservasi rentang id (#535).');

        return self::SUCCESS;
    }

    /** @param  array<int, string>  $tables */
    private function wipe(array $tables): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // PRAGMA foreign_keys tidak bisa diubah di dalam transaksi (tes memakainya); kunci asing
            // ditunda sampai commit, dan pada akhirnya semua tabel yang saling merujuk sudah kosong.
            DB::transaction(function () use ($tables): void {
                DB::statement('PRAGMA defer_foreign_keys = ON');

                foreach ($tables as $table) {
                    DB::table($table)->delete();
                    DB::table('sqlite_sequence')->where('name', $table)->delete();
                }
            });

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();   // MySQL/MariaDB: TRUNCATE juga mereset AUTO_INCREMENT
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
