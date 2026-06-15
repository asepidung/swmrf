<!DOCTYPE html>
<html>
<head>
    <title>Plan Delivery List</title>
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
    <h2>Plan Delivery List</h2>
    <p>Printed at: {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th>Tgl Kirim</th>
                <th>Customer</th>
                <th class="text-center">Total PO</th>
                <th class="text-right">Qty (Kg)</th>
                <th>Driver</th>
                <th>Armada</th>
                <th class="text-center">Jam Loading</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $record->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->format('d M Y') : '-' }}</td>
                <td>{{ optional($record->customer)->name ?? '-' }}</td>
                <td class="text-center">{{ $record->sales_orders_count }}</td>
                <td class="text-right">{{ number_format($record->total_qty) }}</td>
                <td>{{ $record->driver ?? '-' }}</td>
                <td>{{ $record->armada ?? '-' }}</td>
                <td class="text-center">{{ $record->load_time ? \Carbon\Carbon::parse($record->load_time)->format('H:i') : '-' }}</td>
                <td>{{ $record->notes ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
