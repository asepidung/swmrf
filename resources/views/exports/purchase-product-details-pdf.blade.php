<!DOCTYPE html>
<html>
<head>
    <title>Purchase Product Items Detail List</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        h2 { text-align: center; margin-bottom: 5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h2>Purchase Product Items Detail List</h2>
    <p>Printed at: {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>PO Date</th>
                <th>Product</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Subtotal</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ optional($record->purchaseProduct)->po_number ?? '-' }}</strong></td>
                <td>{{ optional(optional($record->purchaseProduct)->supplier)->name ?? '-' }}</td>
                <td>{{ optional($record->purchaseProduct)->po_date ? \Carbon\Carbon::parse($record->purchaseProduct->po_date)->format('d M Y') : '-' }}</td>
                <td>{{ optional($record->product)->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($record->qty, 2, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($record->price, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($record->subtotal, 0, ',', '.') }}</td>
                <td>{{ $record->note ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
