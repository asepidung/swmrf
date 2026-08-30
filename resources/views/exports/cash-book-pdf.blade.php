<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cash Book Export</title>
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
    <h2>{{ $title ?? 'Buku Kas' }}</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="4%">No</th>
                <th width="12%">Tanggal</th>
                <th width="12%">Rekening</th>
                <th class="text-right" width="16%">Uang Masuk</th>
                <th class="text-right" width="16%">Uang Keluar</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalIn = 0;
                $totalOut = 0;
            @endphp
            @forelse($records as $index => $record)
                @php
                    $isIn = $record->type === 'in';
                    $amount = (float) $record->amount;
                    $isIn ? $totalIn += $amount : $totalOut += $amount;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ optional($record->transaction_date)->format('d M Y') ?? '-' }}</td>
                    <td>{{ $record->bankAccount->initial ?? '-' }}</td>
                    <td class="text-right">{{ $isIn ? number_format($amount, 0, ',', '.') : '' }}</td>
                    <td class="text-right">{{ $isIn ? '' : number_format($amount, 0, ',', '.') }}</td>
                    <td>{{ $record->description ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center" colspan="6">Tidak ada data pada rentang ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="3">Total</td>
                <td class="text-right">{{ number_format($totalIn, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalOut, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
