<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order Cattle Export</title>
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
        tfoot td {
            font-weight: bold;
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Purchase Order Cattle' }}</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="4%">No</th>
                <th width="16%">PO Number</th>
                <th width="12%">PO Date</th>
                <th width="12%">Shipping Date</th>
                <th width="22%">Supplier</th>
                <th class="text-right" width="10%">Total Head</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @php $totalHead = 0; @endphp
            @forelse($records as $index => $record)
                @php $totalHead += (int) $record->items->sum('qty'); @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->document_number ?? '-' }}</td>
                    <td>{{ $record->created_at ? $record->created_at->format('d-M-Y') : '-' }}</td>
                    <td>{{ $record->shipping_date ? $record->shipping_date->format('d-M-Y') : '-' }}</td>
                    <td>{{ $record->supplier->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($record->items->sum('qty'), 0, ',', '.') }}</td>
                    <td>{{ $record->summary_note ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center" colspan="7">Tidak ada data pada rentang ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="5">Total Ekor</td>
                <td class="text-right">{{ number_format($totalHead, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
