<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Movements Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
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
            padding: 5px 6px;
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
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Riwayat Mutasi Stok Material' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 4%">No</th>
                <th style="width: 13%">Waktu</th>
                <th style="width: 14%">No. Referensi</th>
                <th>Material</th>
                <th style="width: 10%" class="text-center">Tipe</th>
                <th style="width: 10%" class="text-right">Qty In</th>
                <th style="width: 10%" class="text-right">Qty Out</th>
                <th style="width: 10%" class="text-right">Saldo</th>
                <th>Operator</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->created_at ? $record->created_at->format('d/m/Y H:i') : '-' }}</td>
                    <td>{{ $record->reference_document ?? '-' }}</td>
                    <td>{{ $record->material->name ?? '-' }}</td>
                    <td class="text-center">{{ __($record->transaction_type) }}</td>
                    <td class="text-right">{{ number_format($record->qty_in, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($record->qty_out, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($record->balance, 2, ',', '.') }}</td>
                    <td>{{ $record->creator->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
