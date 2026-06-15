<!DOCTYPE html>
<html>
<head>
    <title>Plan Delivery Items Detail</title>
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
    <h2>Plan Delivery Items Detail</h2>
    <p>Printed at: {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th>Tgl Kirim</th>
                <th>Customer</th>
                <th>SO Number</th>
                <th class="text-right">Qty (Kg)</th>
                <th>Driver</th>
                <th>Armada</th>
                <th class="text-center">Jam Loading</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $record->deliveryPlan->delivery_date ? \Carbon\Carbon::parse($record->deliveryPlan->delivery_date)->format('d M Y') : '-' }}</td>
                <td>{{ optional($record->customer)->name ?? '-' }}</td>
                <td><strong>{{ $record->so_number }}</strong></td>
                <td class="text-right">{{ number_format($record->items()->sum('weight')) }}</td>
                <td>{{ $record->deliveryPlan->driver ?? '-' }}</td>
                <td>{{ $record->deliveryPlan->armada ?? '-' }}</td>
                <td class="text-center">{{ $record->deliveryPlan->load_time ? \Carbon\Carbon::parse($record->deliveryPlan->load_time)->format('H:i') : '-' }}</td>
                <td>{{ $record->delivery_note ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
