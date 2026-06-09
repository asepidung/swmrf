<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payables Export</title>
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
    <h2>{{ $title ?? 'Data Hutang Supplier' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Tanggal Timbul</th>
                <th>No. Dokumen</th>
                <th>Supplier</th>
                <th class="text-right">Total Hutang</th>
                <th class="text-right">Telah Dibayar</th>
                <th class="text-right">Sisa Hutang</th>
                <th>Jatuh Tempo</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->created_at ? $record->created_at->format('d/m/Y') : '-' }}</td>
                    <td>{{ $record->document_number ?? '-' }}</td>
                    <td>{{ $record->supplier->name ?? '-' }}</td>
                    <td class="text-right">Rp {{ number_format($record->amount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->paid_amount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->balance, 0, ',', '.') }}</td>
                    <td>{{ $record->due_date ? \Carbon\Carbon::parse($record->due_date)->format('d/m/Y') : '-' }}</td>
                    <td class="text-center">{{ __($record->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
