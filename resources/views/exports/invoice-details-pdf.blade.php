<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Items Detail Export</title>
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
    <h2>{{ $title ?? 'Detail Item Invoice' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>No. Invoice</th>
                <th>Customer</th>
                <th>Tanggal Invoice</th>
                <th>Nama Produk</th>
                <th class="text-center">Box</th>
                <th class="text-right">Berat (Kg)</th>
                <th class="text-right">Harga (Rp)</th>
                <th class="text-center">Disc %</th>
                <th class="text-right">Disc Rp</th>
                <th class="text-right">Total Net</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->invoice->invoice_number ?? '-' }}</td>
                    <td>{{ $record->invoice->customer->name ?? '-' }}</td>
                    <td>{{ $record->invoice->invoice_date ? $record->invoice->invoice_date->format('d/m/Y') : '-' }}</td>
                    <td>{{ $record->product->name ?? '-' }}</td>
                    <td class="text-center">{{ $record->box }}</td>
                    <td class="text-right">{{ number_format($record->weight, 2, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->price, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $record->discount_percent }}%</td>
                    <td class="text-right">Rp {{ number_format($record->discount_rp, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->amount, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
