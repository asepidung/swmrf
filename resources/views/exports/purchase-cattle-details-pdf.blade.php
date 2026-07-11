<!DOCTYPE html>
<html>
<head>
    <title>Purchase Cattle Details Export</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; }
        th { background: #f4f4f4; }
        h3 { text-align: center; }
    </style>
</head>
<body>
    <h3>PO CATTLE ITEMS DETAIL</h3>
    <table>
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>PO Date</th>
                <th>Cattle Class</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
            <tr>
                <td>{{ $record->purchaseCattle->document_number ?? '-' }}</td>
                <td>{{ $record->purchaseCattle->supplier->name ?? '-' }}</td>
                <td>{{ $record->purchaseCattle->created_at ? $record->purchaseCattle->created_at->format('d-M-Y') : '-' }}</td>
                <td>{{ $record->cattleClass->name ?? '-' }}</td>
                <td style="text-align: right">{{ number_format($record->qty, 0, ',', '.') }}</td>
                <td style="text-align: right">Rp {{ number_format($record->price, 0, ',', '.') }}</td>
                <td style="text-align: right">Rp {{ number_format($record->subtotal, 0, ',', '.') }}</td>
                <td>{{ $record->item_notes ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>