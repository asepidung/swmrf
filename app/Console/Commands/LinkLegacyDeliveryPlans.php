<?php

namespace App\Console\Commands;

use App\Models\DeliveryPlan;
use App\Models\SalesOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menautkan Sales Order BELUM SELESAI hasil impor legacy ke Rencana Pengiriman (`delivery_plans`).
 *
 * Rencana pengiriman dibuat otomatis oleh event model `SalesOrder` saat SO disimpan (satu rencana per pelanggan per tanggal
 * kirim). Importer menulis langsung ke tabel tanpa event itu, sehingga SO impor tidak punya `delivery_plan_id` dan SO yang
 * masih berjalan (menunggu / diproses / dalam pengiriman) TIDAK muncul di layar Rencana Pengiriman pada hari kerja pertama.
 *
 * Hanya SO yang belum selesai dan belum dibatalkan yang ditautkan; riwayat (selesai/batal) dibiarkan tanpa rencana, sama
 * seperti sebelumnya. Dry-run bawaan; `--apply` menulis; aman diulang (SO yang sudah punya rencana dilewati).
 */
class LinkLegacyDeliveryPlans extends Command
{
    protected $signature = 'legacy:link-delivery-plans {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Tautkan Sales Order impor yang belum selesai ke Rencana Pengiriman (dry-run bawaan, aman diulang)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $orders = DB::table('sales_orders')
            ->whereNull('deleted_at')
            ->whereNull('delivery_plan_id')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('delivery_date')
            ->get(['id', 'customer_id', 'delivery_date', 'status']);

        $perRencana = $orders->groupBy(fn ($o) => $o->customer_id.'|'.substr((string) $o->delivery_date, 0, 10));

        $dibuat = $dipakai = 0;

        $run = function () use ($perRencana, $apply, &$dibuat, &$dipakai): void {
            foreach ($perRencana as $kunci => $baris) {
                [$customerId, $tanggal] = explode('|', $kunci);

                $rencana = DeliveryPlan::withTrashed()->where('customer_id', $customerId)->whereDate('delivery_date', $tanggal)->first();

                if ($rencana) {
                    $dipakai++;
                } else {
                    $dibuat++;
                }

                if (! $apply) {
                    continue;
                }

                $rencanaId = $rencana?->id ?? DB::table('delivery_plans')->insertGetId([
                    'customer_id' => $customerId,
                    'delivery_date' => $tanggal,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($rencana?->trashed()) {
                    DB::table('delivery_plans')->where('id', $rencanaId)->update(['deleted_at' => null]);
                }

                DB::table('sales_orders')->whereIn('id', $baris->pluck('id'))->update(['delivery_plan_id' => $rencanaId]);
            }
        };

        $apply ? DB::transaction($run) : $run();

        $this->line($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line('SO belum selesai tanpa rencana: '.$orders->count());

        foreach ($orders->groupBy('status') as $status => $isi) {
            $this->line("  {$status}: ".$isi->count());
        }

        $this->line("rencana pengiriman dibuat: {$dibuat}, rencana yang sudah ada dipakai: {$dipakai}");

        return self::SUCCESS;
    }
}
