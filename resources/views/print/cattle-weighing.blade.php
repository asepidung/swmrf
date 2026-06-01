<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Cattle Weighing - {{ $record->weighing_number }}</title>
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
            font-size: 11px;
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
            font-size: 12px;
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

        .wgh-table tfoot tr {
            background: #fafafa;
            font-weight: bold;
        }

        .loss-badge {
            font-weight: bold;
            padding: 2px 4px;
            border: 1px solid #ffcccc;
            background: #ffe6e6;
            color: #cc0000;
            font-size: 10px;
            border-radius: 4px;
        }
        
        .gain-badge {
            font-weight: bold;
            padding: 2px 4px;
            border: 1px solid #ccffcc;
            background: #e6ffe6;
            color: #009900;
            font-size: 10px;
            border-radius: 4px;
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
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
            gap: 60px;
        }

        .sign-card {
            width: 180px;
            text-align: center;
        }

        .sign-card .muted {
            margin-bottom: 60px;
            color: var(--muted);
            font-weight: 600;
            font-size: 11px;
        }

        .sign-line {
            border-top: 1px solid var(--ink);
            padding-top: 6px;
            font-weight: bold;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .doc {
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(255, 0, 0, 0.15);
            font-weight: bold;
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
            user-select: none;
        }
    </style>
</head>

<body>
    @if($record->trashed())
        <div class="watermark">DELETED</div>
    @endif
    <div class="doc">
        <div class="header">
            <div class="brand">
                <img src="{{ asset('img/light.png') }}" alt="Logo">
                <div>
                    <div class="name">PT. SANTI WIJAYA MEAT</div>
                    <div class="tag">Committed to Meeting Your Need</div>
                </div>
            </div>
        </div>

        <div class="title">
            <h1>Cattle Weighing Record</h1>
            <div style="font-weight: bold; color: var(--ink); font-size: 14px;">No: {{ $record->weighing_number }}</div>
        </div>

        <dl class="meta">
            <dt>Tgl. Timbang</dt>
            <dd>{{ \Carbon\Carbon::parse($record->weighing_date)->format('d-M-Y') }}</dd>

            <dt>Supplier</dt>
            <dd>{{ $record->receiving->supplier->name ?? '-' }}</dd>

            <dt>No. Receive</dt>
            <dd>{{ $record->receiving->receiving_number ?? '-' }}</dd>

            <dt>No. PO Referensi</dt>
            <dd>{{ $record->receiving->purchaseCattle->document_number ?? '-' }}</dd>



            <dt>Penimbang</dt>
            <dd>{{ $record->creator->name ?? 'Admin' }}</dd>
        </dl>

        <table class="wgh-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Eartag Number</th>
                    <th style="width:100px;">Initial Weight</th>
                    <th style="width:100px;">Actual Weight</th>
                    <th style="width:100px;">Variance</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalInitial = 0;
                    $totalActual = 0;
                    $totalVariance = 0;
                @endphp
                @forelse($record->items as $index => $item)
                @php
                    $initial = $item->receivingItem->initial_weight ?? 0;
                    $actual = $item->actual_weight ?? 0;
                    $variance = $actual - $initial;
                    
                    $totalInitial += $initial;
                    $totalActual += $actual;
                    $totalVariance += $variance;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td style="font-weight: bold; letter-spacing: 0.5px; font-size: 12px;">{{ $item->receivingItem->eartag ?? '-' }}</td>
                    <td class="num">{{ number_format($initial, 2, ',', '.') }} Kg</td>
                    <td class="num">{{ number_format($actual, 2, ',', '.') }} Kg</td>
                    <td class="num">
                        @if($variance < 0)
                            <span class="loss-badge">{{ number_format($variance, 2, ',', '.') }} Kg</span>
                        @elseif($variance > 0)
                            <span class="gain-badge">+{{ number_format($variance, 2, ',', '.') }} Kg</span>
                        @else
                            {{ number_format($variance, 2, ',', '.') }} Kg
                        @endif
                    </td>
                    <td style="color: var(--muted); font-size: 10px;">{{ $item->notes ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="center" style="color:#888; padding: 20px;">Tidak ada data sapi.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align: right; text-transform: uppercase; font-size: 11px;">Total ({{ $record->items->count() }} Heads)</td>
                    <td class="num">{{ number_format($totalInitial, 2, ',', '.') }} Kg</td>
                    <td class="num">{{ number_format($totalActual, 2, ',', '.') }} Kg</td>
                    <td class="num">
                        @if($totalVariance < 0)
                            <span class="loss-badge">{{ number_format($totalVariance, 2, ',', '.') }} Kg</span>
                        @elseif($totalVariance > 0)
                            <span class="gain-badge">+{{ number_format($totalVariance, 2, ',', '.') }} Kg</span>
                        @else
                            {{ number_format($totalVariance, 2, ',', '.') }} Kg
                        @endif
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        @if(!empty($record->note))
        <div class="note">
            <div class="label">Catatan Tambahan:</div>
            <div style="border: 1px solid var(--line); padding: 8px; background: #fafafa; min-height: 40px;">
                {!! nl2br(e($record->note)) !!}
            </div>
        </div>
        @endif

        <div class="signs">
            <div class="sign-card">
                <div class="muted">Diperiksa Oleh,</div>
                <div class="sign-line">QC / Supervisor</div>
            </div>
            <div class="sign-card">
                <div class="muted">Penimbang,</div>
                <div class="sign-line">{{ $record->creator->name ?? 'Admin' }}</div>
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
