<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Carcas - {{ $record->weighing->receiving->supplier->name ?? '-' }} - {{ \Carbon\Carbon::parse($record->kill_date)->format('d-M-Y') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --accent: #f0ad4e;
            --ink: #111;
            --muted: #666;
            --line: #e7e7e7;
        }

        body {
            color: var(--ink);
            font-size: 13px;
            font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
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

        table.car-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .car-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }

        .car-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .car-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .car-table td.center {
            text-align: center;
        }

        .car-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .totals {
            margin-top: 8px;
            display: flex;
            justify-content: flex-end;
        }

        .totals table {
            border-collapse: collapse;
        }

        .totals th {
            text-align: right;
            color: var(--muted);
            font-weight: 600;
            padding: 4px 10px;
        }

        .totals td {
            text-align: right;
            min-width: 140px;
            border-bottom: 1px solid var(--line);
            padding: 4px 10px;
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
            .no-print {
                display: none !important;
            }

            .doc {
                margin: 0;
                padding: 0;
                max-width: 100%;
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
            <h1>Carcas Report</h1>
            <div class="muted">
                Kill Date: {{ \Carbon\Carbon::parse($record->kill_date)->format('d-M-Y') }}
            </div>
        </div>

        <dl class="meta">
            <dt>Supplier</dt>
            <dd>{{ $record->weighing->receiving->supplier->name ?? '-' }}</dd>

            <dt>No Weighing</dt>
            <dd>{{ $record->weighing->weigh_no ?? '-' }}</dd>

            <dt>Tgl Timbang</dt>
            <dd>{{ \Carbon\Carbon::parse($record->weighing->weigh_date)->format('d-M-Y') }}</dd>

            <dt>Heads</dt>
            <dd>{{ number_format($record->items->count(), 0, ',', '.') }}</dd>
        </dl>

        <table class="car-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th style="width:90px;">Eartag</th>
                    <th style="width:80px;">Class</th>
                    <th style="width:100px;">Receive Wt (Kg)</th>
                    <th style="width:90px;">Carcas A (Kg)</th>
                    <th style="width:90px;">Carcas B (Kg)</th>
                    <th style="width:110px;">Total Carcas (Kg)</th>
                    <th style="width:80px;">Hides (Kg)</th>
                    <th style="width:80px;">Tail (Kg)</th>
                    <th style="width:80px;">Yield (%)</th>
                </tr>
            </thead>
            <tbody>
                @php
                $tLive = 0; $tC1 = 0; $tC2 = 0; $tCarc = 0; $tHides = 0; $tTails = 0;
                @endphp
                @foreach($record->items as $index => $item)
                @php
                $live = $item->weighingItem->weight ?? 0;
                $totalRowCarc = $item->carcass_1 + $item->carcass_2;
                $rowYield = $live > 0 ? ($totalRowCarc / $live * 100) : 0;

                $tLive += $live;
                $tC1 += $item->carcass_1;
                $tC2 += $item->carcass_2;
                $tCarc += $totalRowCarc;
                $tHides += $item->hides;
                $tTails += $item->tail;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="center">{{ $item->weighingItem->receivingItem->eartag ?? '-' }}</td>
                    <td class="center">{{ $item->weighingItem->receivingItem->cattleCategory->name ?? '-' }}</td>
                    <td class="num">{{ number_format($live, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->carcass_1, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->carcass_2, 2, ',', '.') }}</td>
                    <td class="num"><strong>{{ number_format($totalRowCarc, 2, ',', '.') }}</strong></td>
                    <td class="num">{{ number_format($item->hides, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->tail, 2, ',', '.') }}</td>
                    <td class="num">{{ $rowYield > 0 ? number_format($rowYield, 2, ',', '.') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @php
        $offal = $tCarc + $tTails;
        $yieldTotal = $tLive > 0 ? ($tCarc / $tLive * 100) : 0;
        @endphp

        <div class="totals">
            <table>
                <tr>
                    <th>Total Receive</th>
                    <td>{{ number_format($tLive, 2, ',', '.') }} Kg</td>
                </tr>

                <tr>
                    <th>Offal</th>
                    <td style="font-weight: bold;">{{ number_format($offal, 2, ',', '.') }} Kg</td>
                </tr>
                <tr>
                    <th>Total Hides</th>
                    <td>{{ number_format($tHides, 2, ',', '.') }} Kg</td>
                </tr>
                <tr>
                    <th>Total Tails</th>
                    <td>{{ number_format($tTails, 2, ',', '.') }} Kg</td>
                </tr>
                <tr>
                    <th>Carcase Yield</th>
                    <td style="font-weight: bold;">{{ $tLive > 0 ? number_format($yieldTotal, 2, ',', '.') . ' %' : '-' }}</td>
                </tr>
            </table>
        </div>

        @if($record->note)
        <div class="note">
            <div class="label">Catatan</div>
            <div>{!! nl2br(e($record->note)) !!}</div>
        </div>
        @endif

        <div class="signs">
            <div class="sign-card">
                <div class="muted">Prepared by</div>
                <div class="sign-line">{{ $record->creator->name ?? '-' }}</div>
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