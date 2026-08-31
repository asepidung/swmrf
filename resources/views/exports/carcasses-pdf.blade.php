<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Carcass Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        tfoot td { font-weight: bold; background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Karkas' }}</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="4%">No</th>
                <th width="16%">No. Karkas</th>
                <th width="12%">Tgl Potong</th>
                <th class="text-center" width="8%">Ekor</th>
                <th class="text-right" width="12%">Karkas 1</th>
                <th class="text-right" width="12%">Karkas 2</th>
                <th class="text-right" width="10%">Hides</th>
                <th class="text-right" width="10%">Tail</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $grand = 0; @endphp
            @forelse($records as $index => $record)
                @php
                    $c1 = (float) $record->items->sum('carcass_1');
                    $c2 = (float) $record->items->sum('carcass_2');
                    $hides = (float) $record->items->sum('hides');
                    $tail = (float) $record->items->sum('tail');
                    $total = $c1 + $c2 + $hides + $tail;
                    $grand += $total;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->carcass_number ?? '-' }}</td>
                    <td>{{ $record->kill_date ? \Carbon\Carbon::parse($record->kill_date)->format('d-M-Y') : '-' }}</td>
                    <td class="text-center">{{ $record->items->count() }}</td>
                    <td class="text-right">{{ number_format($c1, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($c2, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($hides, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($tail, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="9">Tidak ada data pada rentang ini.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="8">Total Keseluruhan</td>
                <td class="text-right">{{ number_format($grand, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
