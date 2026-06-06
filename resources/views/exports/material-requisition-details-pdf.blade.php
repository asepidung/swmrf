<!DOCTYPE html>
<html>
<head>
    <title>Detail Request Material List</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        h2 { text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h2>Detail Request Material List</h2>
    <p>Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Request Date</th>
                <th>No. Request</th>
                <th>Supplier</th>
                <th>Item Name</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Status</th>
                <th>User</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ optional($record->requisition)->created_at ? $record->requisition->created_at->format('d-M-Y') : '-' }}</td>
                <td>{{ optional($record->requisition)->document_number ?? '-' }}</td>
                <td>{{ optional(optional($record->requisition)->supplier)->name ?? '-' }}</td>
                <td>{{ optional($record->material)->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($record->qty, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($record->price, 0, ',', '.') }}</td>
                <td>{{ optional($record->requisition)->status ?? '-' }}</td>
                <td>{{ optional(optional($record->requisition)->user)->name ?? '-' }}</td>
                <td>{{ $record->note ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
