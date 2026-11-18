<?php

namespace App\Support;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Uang muka (DP) pelanggan (K-05, keputusan Ayah 18 November 2026): saat DP diisi di Sales Order, DP dicatat sebagai
 * pembayaran yang masuk buku kas (ada riwayat pergerakan uangnya), lalu mengurangi invoice SO itu. DP lama yang sudah
 * terlanjur dipakai di invoice dibiarkan sebagai riwayat.
 */
class CustomerDownPayment
{
    /** Pembayaran DP yang masih berlaku untuk SO ini, bila ada. */
    public static function activePayment(SalesOrder $so): ?Payment
    {
        return Payment::active()->where('sales_order_id', $so->id)->first();
    }

    /**
     * Catat DP SO ini sebesar kolom `down_payment`-nya ke rekening/kas yang dipilih. Tidak melakukan apa pun bila DP nol
     * atau DP sudah pernah dicatat.
     *
     * @throws \RuntimeException bila pelanggan tidak punya grup atau rekeningnya tidak ada
     */
    public static function record(SalesOrder $so, ?int $bankAccountId): ?Payment
    {
        $jumlah = (float) $so->down_payment;

        if ($jumlah <= 0 || static::activePayment($so)) {
            return null;
        }

        $akun = $bankAccountId ? BankAccount::find($bankAccountId) : null;
        $groupId = $so->customer?->customer_group_id;

        if (! $akun || ! $groupId) {
            throw new \RuntimeException(__('The down payment needs a bank/cash account and a customer with a group.'));
        }

        return DB::transaction(function () use ($so, $jumlah, $akun, $groupId): Payment {
            $payment = Payment::create([
                'customer_group_id' => $groupId,
                'sales_order_id' => $so->id,
                'bank_account_id' => $akun->id,
                'payment_date' => now()->toDateString(),
                'amount' => $jumlah,
                'total_deduction' => 0,
                'note' => __('Down payment for sales order :number', ['number' => $so->so_number ?? $so->id]),
            ]);

            BankTransaction::create([
                'bank_account_id' => $akun->id,
                'type' => 'in',
                'amount' => $jumlah,
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
                'description' => __('Down payment for sales order :number (:payment)', ['number' => $so->so_number ?? $so->id, 'payment' => $payment->payment_number]),
                'transaction_date' => now()->toDateString(),
            ]);

            return $payment;
        });
    }

    /**
     * Batas DP yang boleh dipakai invoice SO ini: jumlah DP yang tercatat (atau DP lama di SO bila belum pernah dicatat
     * sebagai pembayaran) dikurangi yang sudah dipakai invoice lain milik SO yang sama. Tanpa batas ini, DP bisa diketik
     * bebas di invoice dan mengurangi piutang tanpa ada uang masuk.
     */
    public static function availableForInvoice(?int $salesOrderId, ?int $exceptInvoiceId = null): float
    {
        $so = $salesOrderId ? SalesOrder::find($salesOrderId) : null;

        if (! $so) {
            return 0.0;
        }

        $dasar = static::activePayment($so) ? (float) static::activePayment($so)->amount : (float) $so->down_payment;

        $terpakai = (float) Invoice::where('sales_order_id', $so->id)
            ->when($exceptInvoiceId, fn ($q) => $q->where('id', '!=', $exceptInvoiceId))
            ->sum('down_payment');

        return round(max($dasar - $terpakai, 0), 2);
    }
}
