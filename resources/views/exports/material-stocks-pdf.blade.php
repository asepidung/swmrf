<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Material Stocks Export</title>
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
        .warning-text {
            color: #d9534f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Data Stok Material' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Kode Material</th>
                <th>Nama Material</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th class="text-right">Stok Aktual</th>
                <th class="text-right">Min. Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                @php
                    $isBelowMin = $record->qty < ($record->material->min_stock ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->material->code ?? '-' }}</td>
                    <td>{{ $record->material->name ?? '-' }}</td>
                    <td>{{ $record->material->category->name ?? '-' }}</td>
                    <td>{{ $record->material->unit->name ?? '-' }}</td>
                    <td class="text-right @if($isBelowMin) warning-text @endif">
                        {{ number_format($record->qty, 2, ',', '.') }}
                    </td>
                    <td class="text-right">
                        {{ number_format($record->material->min_stock ?? 0, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
