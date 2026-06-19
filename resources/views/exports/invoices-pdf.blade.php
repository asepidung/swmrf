<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoices Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
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
    <h2>{{ $title ?? 'Data Invoice Customer' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 3%">No</th>
                <th>No. Invoice</th>
                <th>Tanggal Invoice</th>
                <th>Customer</th>
                <th>Cust PO</th>
                <th>No. DO</th>
                <th class="text-right">Total Berat (Kg)</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Diskon</th>
                <th class="text-right">Pajak</th>
                <th class="text-right">Biaya Tambahan</th>
                <th class="text-right">Uang Muka</th>
                <th class="text-right">Sisa Tagihan</th>
                <th>Jatuh Tempo</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->invoice_number }}</td>
                    <td>{{ $record->invoice_date ? $record->invoice_date->format('d/m/Y') : '-' }}</td>
                    <td>{{ $record->customer->name ?? '-' }}</td>
                    <td>{{ $record->po_number ?? '-' }}</td>
                    <td>{{ $record->delivery_order_number ?? '-' }}</td>
                    <td class="text-right">{{ number_format($record->total_weight, 2, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->subtotal, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->total_discount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->tax, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->charge, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->down_payment, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->balance, 0, ',', '.') }}</td>
                    <td>{{ $record->due_date ? $record->due_date->format('d/m/Y') : '-' }}</td>
                    <td class="text-center">{{ $record->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
