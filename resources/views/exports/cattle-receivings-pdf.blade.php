<!DOCTYPE html>
<html>
<head>
    <title>Cattle Receivings List</title>
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
    <h2>Cattle Receivings List</h2>
    <p>Printed at: {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Receive Number</th>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Date</th>
                <th class="text-center">Heads</th>
                <th>Doc Number</th>
                <th class="text-center">SV OK</th>
                <th class="text-center">SKKH OK</th>
                <th>Received By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $record->receiving_number }}</strong></td>
                <td>{{ optional($record->purchaseCattle)->document_number ?? '-' }}</td>
                <td>{{ optional($record->supplier)->name ?? '-' }}</td>
                <td>{{ $record->receive_date ? \Carbon\Carbon::parse($record->receive_date)->format('d M Y') : '-' }}</td>
                <td class="text-center">{{ $record->items_count ?? $record->items()->count() }}</td>
                <td>{{ $record->doc_no ?? '-' }}</td>
                <td class="text-center">{{ $record->sv_ok ? 'Yes' : 'No' }}</td>
                <td class="text-center">{{ $record->skkh_ok ? 'Yes' : 'No' }}</td>
                <td>{{ optional($record->creator)->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
