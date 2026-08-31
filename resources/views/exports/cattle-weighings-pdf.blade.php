<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cattle Weighing Export</title>
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
    <h2>{{ $title ?? 'Penimbangan Sapi' }}</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="4%">No</th>
                <th width="14%">No. Timbang</th>
                <th width="12%">Tanggal</th>
                <th width="14%">No. Terima</th>
                <th width="18%">Supplier</th>
                <th class="text-center" width="8%">Ekor</th>
                <th class="text-right" width="12%">Berat Terima</th>
                <th class="text-right" width="12%">Berat Aktual</th>
                <th class="text-right">Susut</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalInitial = 0;
                $totalActual = 0;
            @endphp
            @forelse($records as $index => $record)
                @php
                    $initial = (float) $record->items->sum('initial_weight');
                    $actual = (float) $record->items->sum('actual_weight');
                    $totalInitial += $initial;
                    $totalActual += $actual;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->weighing_number ?? '-' }}</td>
                    <td>{{ $record->weighing_date ? \Carbon\Carbon::parse($record->weighing_date)->format('d-M-Y') : '-' }}</td>
                    <td>{{ $record->receiving->receiving_number ?? '-' }}</td>
                    <td>{{ $record->receiving->supplier->name ?? '-' }}</td>
                    <td class="text-center">{{ $record->items->count() }}</td>
                    <td class="text-right">{{ number_format($initial, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($actual, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($initial - $actual, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center" colspan="9">Tidak ada data pada rentang ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="6">Total</td>
                <td class="text-right">{{ number_format($totalInitial, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalActual, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalInitial - $totalActual, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
