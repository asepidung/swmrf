<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Costings Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        tfoot td {
            font-weight: bold;
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Costings' }}</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="4%">No</th>
                <th width="12%">Nomor</th>
                <th width="10%">Tanggal</th>
                <th width="12%">Boning</th>
                <th class="text-right" width="14%">Biaya Beli</th>
                <th class="text-right" width="14%">Nilai Jual</th>
                <th class="text-right" width="10%">Rasio (k)</th>
                <th class="text-right" width="14%">Laba</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalPurchaseCost = 0;
                $totalSalesValue = 0;
                $totalProfit = 0;
            @endphp
            @forelse($records as $index => $record)
                @php
                    $totalPurchaseCost += (float) $record->purchase_cost;
                    $totalSalesValue += (float) $record->total_sales_value;
                    $totalProfit += (float) $record->profit;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->costing_number }}</td>
                    <td>{{ optional($record->costing_date)->format('d M Y') ?? '-' }}</td>
                    <td>{{ $record->boning?->doc_no }}</td>
                    <td class="text-right">{{ number_format((float) $record->purchase_cost, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $record->total_sales_value, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $record->ratio_k, 6) }}</td>
                    <td class="text-right">{{ number_format((float) $record->profit, 0, ',', '.') }}</td>
                    <td>{{ $record->status }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center" colspan="9">Tidak ada data pada rentang ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="4">Total</td>
                <td class="text-right">{{ number_format($totalPurchaseCost, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalSalesValue, 0, ',', '.') }}</td>
                <td></td>
                <td class="text-right">{{ number_format($totalProfit, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
