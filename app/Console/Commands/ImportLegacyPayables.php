<?php

namespace App\Console\Commands;

use App\Models\GoodsReceiptMaterial;
use App\Models\GoodsReceiptProduct;
use App\Models\Payable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * HUTANG (payable) riwayat dari penerimaan barang hasil impor, SEMUANYA dicatat LUNAS (keputusan Ayah, 22 Oktober 2026).
 *
 * Legacy tidak mencatat pelunasan hutang, jadi seluruh riwayat dianggap lunas; baris hutangnya tetap dibentuk (status paid, dibayar penuh, saldo 0)
 * supaya akunting punya sesuatu untuk dibuka kembali lewat aksi "Buka kembali" di halaman Hutang bila ada transaksi yang sebenarnya belum selesai.
 * Dry-run bawaan; `--apply` menulis; aman diulang (penerimaan yang sudah punya hutang dilewati); ditulis lewat DB::table (tanpa event model, tanpa pembayaran/kas).
 *
 * Cakupan: GR material (`goods_receipt_materials`) dan GR beef (`goods_receipt_products`) yang tanggal terimanya SEBELUM `--before` dan belum punya hutang.
 * Nilai = jumlah subtotal item + PPN supplier PKP (seperti `Payable::generateForGoodsReceipt*`), jatuh tempo = tanggal terima + TOP supplier.
 * Penerimaan sapi (`cattle_receivings`) TIDAK dibuatkan hutang riwayat di sini (nilainya bergantung pada harga kelas sapi dan pembayaran PO sapi).
 */
class ImportLegacyPayables extends Command
{
    protected $signature = 'legacy:import-payables
                            {--before= : Hanya penerimaan dengan tanggal terima SEBELUM tanggal ini (Y-m-d), wajib; mencegah menyentuh penerimaan yang dibuat sesudah go-live}
                            {--fallback-user= : Username pencatat hutang riwayat}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Catat hutang riwayat dari GR hasil impor sebagai LUNAS (dry-run bawaan)';

    public const NOTE = 'Riwayat impor legacy: dianggap lunas';

    public function handle(): int
    {
        $before = (string) $this->option('before');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
            $this->error('--before wajib diisi (format Y-m-d), misalnya --before=2026-11-01.');

            return self::FAILURE;
        }

        $user = $this->option('fallback-user') ? User::withoutGlobalScopes()->where('username', $this->option('fallback-user'))->first() : null;

        if (! $user) {
            $this->error('--fallback-user wajib diisi dengan username yang ada.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $taken = DB::table('payables')->pluck('id', 'document_number');
        $stats = ['created' => 0, 'skipped' => 0, 'noAmount' => 0, 'amount' => 0.0, 'byType' => []];
        $conflicts = [];

        $run = function () use ($before, $user, $apply, &$taken, &$stats, &$conflicts): void {
            foreach ([['label' => 'GR material', 'class' => GoodsReceiptMaterial::class], ['label' => 'GR beef', 'class' => GoodsReceiptProduct::class]] as $kind) {
                $class = $kind['class'];
                $class::query()->with(['items', 'supplier'])->whereDate('receive_date', '<', $before)->orderBy('id')->chunkById(200, function ($grs) use ($class, $kind, $user, $apply, &$taken, &$stats, &$conflicts): void {
                    foreach ($grs as $gr) {
                        $number = (string) $gr->gr_number;
                        $has = DB::table('payables')->where(['payableable_type' => $class, 'payableable_id' => $gr->id])->exists();

                        if ($has) {
                            $stats['skipped']++;

                            continue;
                        }

                        if (isset($taken[$number])) {
                            $conflicts[] = "{$number}: nomor dokumen hutang sudah dipakai, dilewati.";

                            continue;
                        }

                        $subtotal = (float) $gr->items->sum('subtotal');
                        $amount = round($subtotal + ($gr->supplier?->ppnAtas($subtotal) ?? 0), 2);

                        if ($amount <= 0) {
                            $stats['noAmount']++;

                            continue;
                        }

                        $due = Carbon::parse($gr->receive_date)->addDays((int) ($gr->supplier->top_days ?? 0))->toDateString();
                        $stats['created']++;
                        $stats['amount'] += $amount;
                        $stats['byType'][$kind['label']] = ($stats['byType'][$kind['label']] ?? 0) + 1;
                        $taken[$number] = true;

                        if ($apply) {
                            DB::table('payables')->insert([
                                'payableable_type' => $class, 'payableable_id' => $gr->id, 'supplier_id' => $gr->supplier_id, 'document_number' => $number,
                                'amount' => $amount, 'paid_amount' => $amount, 'balance' => 0, 'due_date' => $due, 'status' => 'paid', 'note' => self::NOTE,
                                'created_by' => $user->id, 'created_at' => $gr->created_at ?? now(), 'updated_at' => now(),
                            ]);
                        }
                    }
                });
            }
        };

        $apply ? DB::transaction($run) : $run();

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("payables -- dibuat (LUNAS): {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, tanpa nilai (dilewati): {$stats['noAmount']}, konflik: ".count($conflicts).'.');
        $this->line('Nilai total yang dianggap lunas: Rp '.number_format($stats['amount'], 0, ',', '.').'. Rincian: '.collect($stats['byType'])->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').'.');
        $this->line('Catatan: ditulis tanpa event model (tanpa pembayaran, tanpa kas). Penerimaan sapi tidak dibuatkan hutang riwayat.');

        foreach (array_slice($conflicts, 0, 6) as $text) {
            $this->line("  ! {$text}");
        }

        return self::SUCCESS;
    }
}
