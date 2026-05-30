<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Weighing - {{ $record->weigh_no }} - {{ $record->receiving?->supplier?->name ?? 'Unknown' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Document Styles */
        :root {
            --accent: #17a2b8;
            /* Biru Tosca biar beda sama GRC (Orange) */
            --ink: #111;
            --muted: #666;
            --line: #e7e7e7;
        }

        body {
            color: var(--ink);
            font-size: 13px;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
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
            text-transform: uppercase;
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
            font-size: 11px;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta dd {
            margin: 0;
            font-size: 13px;
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
            font-size: 11px;
            text-transform: uppercase;
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
            font-weight: bold;
        }

        .note {
            margin-top: 12px;
        }

        .note .label {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--muted);
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
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }

        .sign-line {
            border-top: 1px dashed var(--line);
            padding-top: 6px;
            font-weight: bold;
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
            <h1>Cattle Weighing Report</h1>
            <div style="font-weight: bold; color: var(--accent);">Weight No: {{ $record->weigh_no }}</div>
        </div>

        <dl class="meta">
            <dt>Tgl Timbang</dt>
            <dd>{{ \Carbon\Carbon::parse($record->weigh_date)->format('d-M-Y') }}</dd>

            <dt>PO Number</dt>
            <dd>{{ $record->receiving?->purchaseOrder?->po_number ?? '-' }}</dd>

            <dt>Petugas</dt>
            <dd>{{ $record->creator?->name ?? '-' }}</dd>

            <dt>Supplier</dt>
            <dd>{{ $record->receiving?->supplier?->name ?? '-' }}</dd>

            <dt>GRC Number</dt>
            <dd>{{ $record->receiving?->receiving_number ?? '-' }}</dd>

            <dt>Ekor</dt>
            <dd>{{ number_format($record->items->count(), 0, ',', '.') }} Heads</dd>
        </dl>

        @php
        // Hitung kalkulasi total sebelum nge-loop tabel
        $totalReceive = 0;
        $totalActual = 0;

        foreach($record->items as $item) {
        $totalReceive += (float) ($item->receivingItem?->initial_weight ?? 0);
        $totalActual += (float) ($item->weight ?? 0);
        }

        $totalDiff = $totalActual - $totalReceive;
        @endphp

        <table class="wgh-table">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Eartag</th>
                    <th style="width:140px;">Berat Receive (Kg)</th>
                    <th style="width:140px;">Berat Timbang (Kg)</th>
                    <th style="width:140px;">Selisih (Kg)</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($record->items as $index => $item)
                @php
                $rw = (float) ($item->receivingItem?->initial_weight ?? 0);
                $aw = (float) ($item->weight ?? 0);
                $df = $aw - $rw;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td style="font-weight: bold;">{{ $item->receivingItem?->eartag ?? '-' }}</td>
                    <td class="num">{{ number_format($rw, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($aw, 2, ',', '.') }}</td>
                    <td class="num" @style([ 'color: red;'=> $df < 0, 'color: green;'=> $df > 0,
                            ])>
                            {{ number_format($df, 2, ',', '.') }}
                    </td>
                    <td style="font-size: 11px; color: var(--muted);">{{ $item->notes ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="center" style="color:#888; padding: 20px;">No details found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <th>Total Receive</th>
                    <td>{{ number_format($totalReceive, 2, ',', '.') }} Kg</td>
                </tr>
                <tr>
                    <th>Total Timbang</th>
                    <td>{{ number_format($totalActual, 2, ',', '.') }} Kg</td>
                </tr>
                <tr>
                    <th>Total Selisih</th>
                    <td @style([ 'color: red;'=> $totalDiff < 0, 'color: green;'=> $totalDiff > 0,
                            ])>
                            {{ number_format($totalDiff, 2, ',', '.') }} Kg
                    </td>
                </tr>
            </table>
        </div>

        @if(!empty($record->note))
        <div class="note">
            <div class="label">Catatan Tambahan</div>
            <div style="border: 1px solid var(--line); padding: 8px; background: #fafafa; min-height: 40px;">
                {!! nl2br(e($record->note)) !!}
            </div>
        </div>
        @endif

        <div class="signs">
            <div class="sign-card">
                <div class="muted">Weigher</div>
                <div class="sign-line">{{ $record->creator?->name ?? '-' }}</div>
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