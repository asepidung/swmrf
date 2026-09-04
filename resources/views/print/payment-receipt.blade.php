{{--
    Bukti terima pembayaran pelanggan.

    SATU dokumen, bukan dua. Keputusan Project Owner, 3 September 2026: bagian
    atas adalah yang dibaca pelanggan -- telah terima dari siapa, berapa, dan
    untuk apa -- sementara rincian alokasi per invoice di bawahnya adalah yang
    dibutuhkan keuangan. Dipecah menjadi kwitansi dan bukti bank masuk yang
    terpisah hanya kalau nanti memang diminta.

    CSS-nya menyatu di berkas ini, TIDAK memanggil CDN seperti halaman cetak
    lain di aplikasi ini. Dokumen ini diserahkan kepada pelanggan dan sering
    dicetak dari gudang atau kendaraan; halaman yang tata letaknya runtuh
    begitu internetnya lambat bukan bukti pembayaran yang bisa dipegang.
    Ketergantungan CDN di halaman cetak lain sudah tercatat sebagai utang
    tersendiri.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $record->payment_number }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 13px;
            color: #222;
            background: #f4f6f9;
            margin: 0;
            padding: 24px;
        }

        .sheet {
            background: #fff;
            max-width: 760px;
            margin: 0 auto;
            padding: 36px 40px;
            border: 1px solid #ddd;
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #222;
            padding-bottom: 12px;
            margin-bottom: 22px;
        }

        .company { font-size: 18px; font-weight: bold; letter-spacing: .5px; }
        .company small { display: block; font-size: 11px; font-weight: normal; color: #666; }

        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 16px; margin: 0 0 4px; letter-spacing: 1px; }
        .doc-title .number { font-family: monospace; font-size: 15px; font-weight: bold; }
        .doc-title .date { font-size: 12px; color: #666; }

        .statement { margin-bottom: 22px; line-height: 1.9; }
        .statement .field {
            display: inline-block;
            min-width: 240px;
            border-bottom: 1px dotted #999;
            font-weight: bold;
        }

        .amount-box {
            border: 1px solid #222;
            padding: 10px 14px;
            margin: 18px 0 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .amount-box .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        .amount-box .value { font-size: 20px; font-weight: bold; font-family: monospace; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e4e4e4; }
        th { background: #f2f2f2; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
        .right { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #222; border-bottom: none; }

        .section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            margin: 22px 0 6px;
        }

        .sign { margin-top: 40px; display: flex; justify-content: flex-end; }
        .sign .box { width: 220px; text-align: center; }
        .sign .line { margin-top: 64px; border-top: 1px solid #222; padding-top: 6px; font-size: 12px; }

        .foot { margin-top: 28px; font-size: 11px; color: #777; text-align: center; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: none; max-width: none; padding: 0; }
            .no-print { display: none; }
        }

        .no-print { text-align: center; margin-bottom: 16px; }
        .no-print button {
            font: inherit;
            padding: 8px 20px;
            cursor: pointer;
            border: 1px solid #222;
            background: #222;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="company">
                WIJAYA MEAT
                <small>{{ __('Payment Receipt') }}</small>
            </div>
            <div class="doc-title">
                <h1>{{ __('Payment Receipt') }}</h1>
                <div class="number">{{ $record->payment_number }}</div>
                <div class="date">{{ $record->payment_date?->format('d F Y') }}</div>
            </div>
        </div>

        {{-- Bagian yang dibaca pelanggan. --}}
        <div class="statement">
            {{ __('Received from') }}:
            <span class="field">{{ $record->customerGroup->name ?? '-' }}</span><br>

            {{ __('Into Account') }}:
            <span class="field">
                {{ $record->bankAccount?->bank_name }} {{ $record->bankAccount?->account_number }}
            </span><br>

            @if ($record->reference_number)
                {{ __('Transfer Reference') }}:
                <span class="field">{{ $record->reference_number }}</span>
            @endif
        </div>

        @php
            $totalAllocated = $record->allocations->sum('amount_allocated');
        @endphp

        <div class="amount-box">
            <span class="label">{{ __('Total Settled') }}</span>
            <span class="value">Rp {{ number_format($totalAllocated, 0, ',', '.') }}</span>
        </div>

        {{-- Bagian yang dibutuhkan keuangan: ke invoice mana saja uangnya. --}}
        <div class="section-title">{{ __('Allocation to Invoices') }}</div>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Invoice Number') }}</th>
                    <th>{{ __('Invoice Date') }}</th>
                    <th class="right">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($record->allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->invoice->invoice_number ?? '-' }}</td>
                        <td>{{ $allocation->invoice?->invoice_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="right">Rp {{ number_format($allocation->amount_allocated, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">-</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">{{ __('Total Settled') }}</td>
                    <td class="right">Rp {{ number_format($totalAllocated, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- Potongan sengaja ditampilkan terpisah.

             Uang yang benar-benar masuk bank LEBIH KECIL daripada tagihan yang
             lunas, dan selisihnya adalah potongan. Tanpa baris ini pembacanya
             akan mencocokkan angka di kertas dengan rekening koran dan
             menemukan selisih yang tidak dijelaskan apa pun. --}}
        @if ($record->deductions->isNotEmpty())
            <div class="section-title">{{ __('Deductions') }}</div>
            <table>
                <tbody>
                    @foreach ($record->deductions as $deduction)
                        <tr>
                            <td>
                                {{ \App\Models\PaymentDeduction::typeLabel($deduction->type) }}
                                &mdash; {{ $deduction->description }}
                                {{-- Potongan yang menunjuk satu invoice harus terbaca
                                     menunjuk invoice itu. Tanpa keterangan ini,
                                     pembacanya tidak punya cara tahu kenapa satu
                                     invoice lunas dengan uang yang lebih sedikit. --}}
                                @if ($deduction->invoice)
                                    <br><small>{{ $deduction->invoice->invoice_number }}</small>
                                @endif
                            </td>
                            <td class="right">Rp {{ number_format($deduction->amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>{{ __('Amount Received in Bank') }}</td>
                        <td class="right">Rp {{ number_format($record->amount, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        @if ($record->note)
            <div class="section-title">{{ __('Note') }}</div>
            <div>{{ $record->note }}</div>
        @endif

        <div class="sign">
            <div class="box">
                {{ __('Received by') }}
                <div class="line">{{ $record->creator->name ?? '-' }}</div>
            </div>
        </div>

        <div class="foot">
            {{ __('Printed on :date', ['date' => now()->format('d/m/Y H:i')]) }}
        </div>
    </div>
</body>
</html>
