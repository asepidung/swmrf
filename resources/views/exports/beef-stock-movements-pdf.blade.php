<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Beef Stock Movements Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 4px 5px;
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
    <h2>{{ $title ?? 'Riwayat Mutasi Stok Beef' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 3%">No</th>
                <th style="width: 10%">Waktu</th>
                <th style="width: 12%">No. Referensi</th>
                <th style="width: 16%">Barcode</th>
                <th>Produk</th>
                <th style="width: 7%">Gudang</th>
                <th style="width: 6%">Grade</th>
                <th style="width: 8%" class="text-center">Tipe</th>
                <th style="width: 6%" class="text-right">Berat In</th>
                <th style="width: 6%" class="text-right">Berat Out</th>
                <th style="width: 5%" class="text-right">Pcs In</th>
                <th style="width: 5%" class="text-right">Pcs Out</th>
                <th style="width: 10%">Operator</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->created_at ? $record->created_at->format('d/m/Y H:i') : '-' }}</td>
                    <td>{{ $record->reference_document ?? '-' }}</td>
                    <td>{{ $record->barcode ?? '-' }}</td>
                    <td>{{ $record->product->name ?? '-' }}</td>
                    <td>{{ $record->warehouse->name ?? '-' }}</td>
                    <td>{{ $record->grade->name ?? '-' }}</td>
                    <td class="text-center">{{ __($record->transaction_type) }}</td>
                    <td class="text-right">{{ $record->weight_in > 0 ? number_format($record->weight_in, 2, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ $record->weight_out > 0 ? number_format($record->weight_out, 2, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ $record->pcs_in > 0 ? $record->pcs_in : '-' }}</td>
                    <td class="text-right">{{ $record->pcs_out > 0 ? $record->pcs_out : '-' }}</td>
                    <td>{{ $record->creator->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
