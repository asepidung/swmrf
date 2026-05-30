<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $request->document_number }} - {{ $request->supplier->name ?? 'Unknown' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Document Styles */
        :root {
            --accent: #f0ad4e;
            --ink: #111;
            --muted: #666;
            --line: #e7e7e7;
        }

        body {
            color: var(--ink);
            font-size: 13px;
            font-family: Arial, sans-serif;
        }

        .doc {
            max-width: 960px;
            margin: 24px auto 48px;
            padding: 0 16px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 12px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            height: 52px;
            width: auto;
        }

        .brand .name {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .brand .tag {
            font-size: 12px;
            color: var(--muted);
        }

        .title {
            margin: 18px 0 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .title h1 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            letter-spacing: .5px;
        }

        .meta {
            margin-top: 12px;
            display: grid;
            grid-template-columns: auto 1fr auto 1fr;
            column-gap: 16px;
            row-gap: 6px;
            align-items: center;
        }

        .meta dt {
            font-weight: 600;
            margin: 0;
        }

        .meta dd {
            margin: 0;
        }

        table.wgh-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .wgh-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }

        .wgh-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .wgh-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .wgh-table td.center {
            text-align: center;
        }

        .wgh-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .totals {
            margin-top: 6px;
            display: flex;
            justify-content: flex-end;
        }

        .totals table {
            border-collapse: collapse;
        }

        .totals th,
        .totals td {
            padding: 6px 10px;
        }

        .totals th {
            text-align: right;
            color: var(--muted);
            font-weight: 600;
        }

        .totals td {
            text-align: right;
            min-width: 140px;
            border-bottom: 1px solid var(--line);
        }

        .note {
            margin-top: 12px;
        }

        .note .label {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .signs {
            margin-top: 34px;
            display: flex;
            justify-content: flex-end;
        }

        .sign-card {
            width: 260px;
            text-align: center;
        }

        .sign-card .muted {
            margin-bottom: 56px;
            color: var(--muted);
        }

        .sign-line {
            border-top: 1px dashed var(--line);
            padding-top: 6px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            .doc {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="doc">
        <div class="header">
            <div class="brand">
                <img src="{{ asset('img/LOGO-Y.png') }}" alt="Logo">
                <div>
                    <div class="name">PT. SANTI WIJAYA MEAT</div>
                    <div class="tag">Committed to Meeting Your Need</div>
                </div>
            </div>
        </div>

        <div class="title">
            <h1>Beef Requisition</h1>
            <div class="muted">Request No: {{ $request->document_number }}</div>
        </div>

        <dl class="meta">
            <dt>Due Date</dt>
            <dd>{{ \Carbon\Carbon::parse($request->due_date)->format('d-M-Y') }}</dd>

            <dt>Requester</dt>
            <dd>{{ $request->user->name ?? '-' }}</dd>

            <dt>Supplier</dt>
            <dd>{{ $request->supplier->name ?? '-' }}</dd>

            <dt>Terms of Payment</dt>
            <dd>{{ $request->supplier->term_of_payment ?? '0' }} Days</dd>
        </dl>

        <table class="wgh-table">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Product Description</th>
                    <th style="width:100px;">Qty (Kg)</th>
                    <th style="width:140px;">Unit Price (Rp)</th>
                    <th style="width:140px;">Amount (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($request->items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item->product->name ?? '-' }}</td>
                    <td class="num right">{{ number_format($item->qty, 2, ',', '.') }}</td>
                    <td class="num right">{{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="num right">{{ number_format($item->qty * $item->price, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="center" style="color:#888;">No items found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <th>Subtotal</th>
                    <td>Rp {{ number_format($request->total_amount, 0, ',', '.') }}</td>
                </tr>

                @php
                $taxAmount = 0;
                if ($request->supplier && $request->supplier->has_tax) {
                $taxAmount = $request->total_amount * 0.11;
                }
                $grandTotal = $request->total_amount + $taxAmount;
                @endphp

                <tr>
                    <th>Tax (11%)</th>
                    <td>Rp {{ number_format($taxAmount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Grand Total</th>
                    <td><strong>Rp {{ number_format($grandTotal, 0, ',', '.') }}</strong></td>
                </tr>
            </table>
        </div>

        @if(!empty($request->note))
        <div class="note">
            <div class="label">Additional Notes</div>
            <div>{!! nl2br(e($request->note)) !!}</div>
        </div>
        @endif

        <div class="signs">
            <div class="sign-card">
                <div class="muted">Requester</div>
                <div class="sign-line">{{ $request->user->name ?? '-' }}</div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };

        window.onafterprint = function() {
            window.close();
        };
    </script>
</body>

</html>