<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Material Usage Details</title>
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
        <h2>Material Usage Details</h2>
        <p>Generated on: {{ now()->format('d M Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Reference Document</th>
                <th>Usage Date</th>
                <th>Material</th>
                <th class="text-right">Quantity</th>
                <th>Unit</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $index => $record)
            @php
                $type = $record->usageable ? class_basename($record->usageable_type) : '-';
                $docNo = $record->usageable->doc_no ?? $record->usageable_id ?? '';
                $refDoc = $record->usageable ? $type . ' (' . $docNo . ')' : '-';
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $refDoc }}</td>
                <td>{{ $record->created_at ? $record->created_at->format('d M Y') : '' }}</td>
                <td>{{ $record->material->name ?? '' }}</td>
                <td class="text-right">{{ $record->qty }}</td>
                <td>{{ $record->material->unit->name ?? '' }}</td>
                <td>{{ $record->note ?? '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">No data available</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span class="page-number"></span>
    </div>
</body>
</html>
