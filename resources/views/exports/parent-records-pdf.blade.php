<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Data Export' }}</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Date</th>
                <th>Document No.</th>
                <th>Supplier</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>User</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $record->created_at ? $record->created_at->format('d/m/Y') : '-' }}</td>
                    <td>{{ $record->document_number ?? '-' }}</td>
                    <td>{{ $record->supplier->name ?? '-' }}</td>
                    <td>Rp {{ number_format($record->total_amount ?? 0, 0, ',', '.') }}</td>
                    <td>{{ $record->status ?? '-' }}</td>
                    <td>{{ $record->user->name ?? '-' }}</td>
                    <td>{{ $record->note ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
