<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Return Details</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; font-size: 16px; text-transform: uppercase; }
        .header p { margin: 0; font-size: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f3f4f6; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .footer { margin-top: 30px; font-size: 8px; text-align: center; color: #777; }
        .page-number:before { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="header">
        <h2>Sales Return Details</h2>
        <p>Generated on: {{ now()->format('d M Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Return Number</th>
                <th>Return Date</th>
                <th>Customer</th>
                <th>Barcode</th>
                <th>Product</th>
                <th class="text-right">Weight</th>
                <th>Grade</th>
                <th>Warehouse</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $record->salesReturn->return_number ?? '' }}</td>
                <td>{{ $record->created_at ? $record->created_at->format('d M Y') : '' }}</td>
                <td>{{ $record->salesReturn->customer->name ?? '' }}</td>
                <td>{{ $record->barcode ?? '' }}</td>
                <td>{{ $record->product->name ?? '' }}</td>
                <td class="text-right">{{ number_format($record->weight, 2, ',', '.') }}</td>
                <td>{{ $record->grade->name ?? '' }}</td>
                <td>{{ $record->warehouse->name ?? '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No data available</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span class="page-number"></span>
    </div>
</body>
</html>
