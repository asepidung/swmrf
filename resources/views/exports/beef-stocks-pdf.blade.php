<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Beef Stocks Export</title>
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
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Data Stok Beef' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Kode</th>
                <th>Nama Produk</th>
                <th class="text-right">CHILL (J)</th>
                <th class="text-right">FROZEN (J)</th>
                <th class="text-right">CHILL (P)</th>
                <th class="text-right">FROZEN (P)</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->code ?? '-' }}</td>
                    <td>{{ $record->name ?? '-' }}</td>
                    <td class="text-right">
                        {{ $record->chill_jonggol > 0 ? number_format($record->chill_jonggol, 2, ',', '.') : '' }}
                    </td>
                    <td class="text-right">
                        {{ $record->frozen_jonggol > 0 ? number_format($record->frozen_jonggol, 2, ',', '.') : '' }}
                    </td>
                    <td class="text-right">
                        {{ $record->chill_perum > 0 ? number_format($record->chill_perum, 2, ',', '.') : '' }}
                    </td>
                    <td class="text-right">
                        {{ $record->frozen_perum > 0 ? number_format($record->frozen_perum, 2, ',', '.') : '' }}
                    </td>
                    <td class="text-right" style="font-weight: bold;">
                        {{ $record->total_qty > 0 ? number_format($record->total_qty, 2, ',', '.') : '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
