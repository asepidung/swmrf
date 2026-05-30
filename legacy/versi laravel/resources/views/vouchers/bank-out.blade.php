<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Out Voucher - #{{ $installment->id }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .voucher-container {
            border: 2px solid #222;
            padding: 20px;
            max-width: 800px;
            margin: auto;
            position: relative;
            z-index: 1;
            background: #fff;
            overflow: hidden;
        }

        /* JAMU WATERMARK LUNAS */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(39, 174, 96, 0.15);
            /* Warna hijau transparan */
            font-weight: 900;
            text-transform: uppercase;
            z-index: -1;
            white-space: nowrap;
            pointer-events: none;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #222;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header-left h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .header-left p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #555;
        }

        .header-right {
            text-align: right;
        }

        .header-right h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 900;
            color: #000;
        }

        .header-right p {
            margin: 5px 0 0;
            font-size: 14px;
            font-weight: bold;
        }

        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 6px 0;
            vertical-align: top;
        }

        .info-table .label {
            width: 150px;
            font-weight: bold;
        }

        .info-table .colon {
            width: 20px;
            text-align: center;
        }

        .info-table .value {
            border-bottom: 1px dashed #ccc;
            padding-bottom: 2px;
        }

        .amount-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .amount-box {
            background: #f9f9f9;
            border: 2px solid #222;
            padding: 15px;
            font-size: 22px;
            font-weight: 900;
            text-align: center;
            min-width: 250px;
            letter-spacing: 1px;
        }

        .amount-words {
            flex: 1;
            padding: 10px;
            border-left: 4px solid #222;
            background: #f4f4f4;
            font-size: 14px;
            line-height: 1.5;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #222;
            padding: 10px;
            text-align: left;
        }

        .details-table th {
            background: #eee;
            text-transform: uppercase;
            font-size: 11px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            text-align: center;
        }

        .signatures th {
            border: 1px solid #222;
            padding: 8px;
            background: #eee;
            font-size: 11px;
            text-transform: uppercase;
        }

        .signatures td {
            border: 1px solid #222;
            height: 90px;
            vertical-align: bottom;
            padding: 10px;
            font-size: 12px;
        }

        .print-btn {
            display: block;
            width: 120px;
            margin: 20px auto;
            padding: 12px;
            background: #000;
            color: #fff;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            letter-spacing: 1px;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .voucher-container {
                border: none;
                max-width: 100%;
                box-shadow: none;
            }

            .print-btn {
                display: none;
            }

            .amount-box {
                -webkit-print-color-adjust: exact;
                background: #f9f9f9 !important;
            }

            .amount-words {
                -webkit-print-color-adjust: exact;
                background: #f4f4f4 !important;
            }

            th {
                -webkit-print-color-adjust: exact;
                background: #eee !important;
            }
        }
    </style>
</head>

<body>

    <button class="print-btn" onclick="window.print()">🖨️ PRINT</button>

    <div class="voucher-container">

        @if($installment->payable->balance_due <= 0)
            <div class="watermark">PAID IN FULL</div>
    @endif

    <div class="header">
        <div class="header-left">
            <h1>PT. SANTI WIJAYA MEAT</h1>
            <p>Finance & Accounting Department</p>
        </div>
        <div class="header-right">
            <h2>BANK OUT VOUCHER</h2>
            <p>No: BOV-{{ date('Ym', strtotime($installment->payment_date)) }}-{{ str_pad($installment->id, 4, '0', STR_PAD_LEFT) }}</p>
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Paid To / Supplier</td>
            <td class="colon">:</td>
            <td class="value" style="font-size: 15px;"><strong>{{ $installment->accountPayable->supplier->name ?? 'Unknown Supplier' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Payment Date</td>
            <td class="colon">:</td>
            <td class="value">{{ date('d F Y', strtotime($installment->payment_date)) }}</td>
        </tr>
        <tr>
            <td class="label">Payment Method/Bank</td>
            <td class="colon">:</td>
            <td class="value"><strong>{{ $installment->payment_method }}</strong></td>
        </tr>
        <tr>
            <td class="label">Ref. Number</td>
            <td class="colon">:</td>
            <td class="value">{{ $installment->proof_of_payment ?? '-' }}</td>
        </tr>
    </table>

    @php
    $formatter = new \NumberFormatter('id', \NumberFormatter::SPELLOUT);
    $inWords = ucwords($formatter->format($installment->amount_paid)) . ' Rupiah';
    @endphp

    <div class="amount-container">
        <div class="amount-box">
            Rp {{ number_format($installment->amount_paid, 0, ',', '.') }}
        </div>
        <div class="amount-words">
            <strong style="text-transform: uppercase; font-size: 11px; color: #555;">The Sum Of (In Words):</strong><br>
            <i style="font-size: 16px; font-weight: bold;">"{{ $inWords }}"</i>
        </div>
    </div>

    <table class="details-table">
        <thead>
            <tr>
                <th>Transaction Breakdown & Notes</th>
                <th style="width: 200px; text-align: right;">Amount (IDR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Total Invoice / PO Amount</strong><br>
                    <span style="color: #555; font-size: 11px;">Ref PO: {{ $installment->accountPayable->payable->po_number ?? '-' }}</span>
                </td>
                <td style="text-align: right;">
                    {{ number_format($installment->payable->total_amount, 0, ',', '.') }}
                </td>
            </tr>

            @php
            $prevPayments = $installment->payable->total_amount - $installment->payable->balance_due - ($installment->amount_paid + $installment->discount_amount);
            @endphp
            @if($prevPayments > 0)
            <tr>
                <td style="color: #555; padding-left: 20px;"><i>Less: Previous Payments & Adjustments</i></td>
                <td style="text-align: right; color: #555;">
                    ({{ number_format($prevPayments, 0, ',', '.') }})
                </td>
            </tr>
            @endif

            @if($installment->discount_amount > 0)
            <tr>
                <td style="color: #555; padding-left: 20px;"><i>Less: Discount / Adjustment (This Voucher)</i></td>
                <td style="text-align: right; color: #555;">
                    ({{ number_format($installment->discount_amount, 0, ',', '.') }})
                </td>
            </tr>
            @endif

            <tr>
                <td>
                    <strong>Payment Amount (This Voucher)</strong><br>
                    <span style="color: #555; font-size: 11px;">Note: {{ $installment->note ?: 'No notes provided.' }}</span>
                </td>
                <td style="text-align: right; font-weight: bold; background: #eee;">
                    ({{ number_format($installment->amount_paid, 0, ',', '.') }})
                </td>
            </tr>

            <tr>
                <td style="text-align: right; font-weight: 900; font-size: 14px; text-transform: uppercase;">
                    Remaining Balance
                </td>
                <td style="text-align: right; font-weight: 900; font-size: 15px; {{ $installment->payable->balance_due <= 0 ? 'color: #27ae60;' : 'color: #c0392b;' }}">
                    @if($installment->payable->balance_due <= 0)
                        0 (PAID IN FULL)
                        @else
                        {{ number_format($installment->payable->balance_due, 0, ',', '.') }}
                        @endif
                        </td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <thead>
            <tr>
                <th>Prepared By</th>
                <th>Checked By</th>
                <th>Approved By</th>
                <th>Received By</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{{ $installment->creator->name ?? 'System' }}</strong><br><span style="font-size: 10px; color: #777;">( Finance Staff )</span></td>
                <td><br><br><span style="font-size: 10px; color: #777;">( Finance Manager )</span></td>
                <td><br><br><span style="font-size: 10px; color: #777;">( Director )</span></td>
                <td><br><br><span style="font-size: 10px; color: #777;">( Supplier Signature / Stamp )</span></td>
            </tr>
        </tbody>
    </table>
    </div>

</body>

</html>