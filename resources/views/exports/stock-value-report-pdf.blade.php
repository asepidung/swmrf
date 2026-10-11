<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Stock Value Report') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h2 { text-align: center; margin: 0 0 4px 0; }
        .period { text-align: center; margin-bottom: 14px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f2f2f2; font-weight: bold; }
        .r { text-align: right; }
        .note { font-size: 9px; color: #666; }
    </style>
</head>
@php
    $number = fn ($value): string => number_format((float) $value, 2, ',', '.');
    $rupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
    $total = $report['total'];
@endphp
<body>
    <h2>{{ __('Stock Value Report') }}</h2>
    <div class="period">{{ now()->format('d/m/Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Warehouse') }}</th><th>{{ __('Product') }}</th><th class="r">{{ __('Barcodes') }}</th><th class="r">Kg</th>
                <th class="r">{{ __('Value') }}</th><th class="r">{{ __('Unvalued barcodes') }}</th><th class="r">{{ __('Unvalued Kg') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $line)
                <tr>
                    <td>{{ $line['warehouse'] }}</td><td>{{ $line['product'] }}</td><td class="r">{{ $line['barcodes'] }}</td>
                    <td class="r">{{ $number($line['kg']) }}</td><td class="r">{{ $rupiah($line['value']) }}</td>
                    <td class="r">{{ $line['unvalued_barcodes'] }}</td><td class="r">{{ $number($line['unvalued_kg']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">{{ __('No stock found.') }}</td></tr>
            @endforelse
            <tr>
                <td colspan="2"><strong>{{ __('Total') }}</strong></td><td class="r"><strong>{{ $total['barcodes'] }}</strong></td>
                <td class="r"><strong>{{ $number($total['kg']) }}</strong></td><td class="r"><strong>{{ $rupiah($total['value']) }}</strong></td>
                <td class="r"><strong>{{ $total['unvalued_barcodes'] }}</strong></td><td class="r"><strong>{{ $number($total['unvalued_kg']) }}</strong></td>
            </tr>
        </tbody>
    </table>
    <div class="note">{{ __('Barcodes without a cost yet are shown separately and are not counted as zero.') }}</div>
</body>
</html>
