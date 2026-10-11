<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Sales Cost Report') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; color: #333; }
        h2 { text-align: center; margin: 0 0 4px 0; }
        .period { text-align: center; margin-bottom: 14px; color: #555; }
        h3 { font-size: 11px; text-transform: uppercase; margin: 14px 0 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; text-align: left; }
        th { background: #f2f2f2; font-weight: bold; }
        .r { text-align: right; }
        .note { font-size: 9px; color: #666; }
    </style>
</head>
@php
    $number = fn ($value): string => number_format((float) $value, 2, ',', '.');
    $rupiah = fn ($value): string => $value === null ? '-' : 'Rp '.number_format((float) $value, 0, ',', '.');
    $total = $report['total'];
@endphp
<body>
    <h2>{{ __('Sales Cost Report') }}</h2>
    <div class="period">
        {{ \Carbon\Carbon::parse($report['from'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($report['until'])->format('d/m/Y') }}
    </div>

    <h3>{{ __('Per delivery order') }}</h3>
    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th><th>{{ __('Delivery Order') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Product') }}</th>
                <th class="r">Kg</th><th class="r">{{ __('Sales') }}</th><th class="r">HPP</th><th class="r">{{ __('Margin') }}</th><th class="r">{{ __('Unvalued barcodes') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $line)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}</td><td>{{ $line['delivery_order'] }}</td><td>{{ $line['invoice'] ?? '-' }}</td>
                    <td>{{ $line['customer'] }}</td><td>{{ $line['product'] }}</td>
                    <td class="r">{{ $number($line['kg']) }}</td><td class="r">{{ $rupiah($line['sales']) }}</td><td class="r">{{ $rupiah($line['hpp']) }}</td>
                    <td class="r">{{ $rupiah($line['margin']) }}</td><td class="r">{{ $line['unvalued_barcodes'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10">{{ __('No delivery orders in this period.') }}</td></tr>
            @endforelse
            <tr>
                <td colspan="5"><strong>{{ __('Total') }}</strong></td>
                <td class="r"><strong>{{ $number($total['kg']) }}</strong></td><td class="r"><strong>{{ $rupiah($total['sales']) }}</strong></td>
                <td class="r"><strong>{{ $rupiah($total['hpp']) }}</strong></td><td class="r"><strong>{{ $rupiah($total['margin']) }}</strong></td>
                <td class="r"><strong>{{ $total['unvalued_barcodes'] }}</strong></td>
            </tr>
        </tbody>
    </table>
    @if ($total['not_invoiced_rows'] > 0 || $total['unvalued_barcodes'] > 0)
        <div class="note">
            * {{ $total['not_invoiced_rows'] }} {{ __('rows not invoiced yet') }}, {{ $total['unvalued_barcodes'] }} {{ __('barcodes without a cost') }}
            ({{ $number($total['unvalued_kg']) }} kg). {{ $total['margin_rows'] }} / {{ count($report['rows']) }} {{ __('rows have a margin') }}.
        </div>
    @endif

    @foreach ([['by_customer', __('Per customer')], ['by_product', __('Per product')]] as [$key, $heading])
        @if (count($report[$key]))
            <h3>{{ $heading }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>{{ $key === 'by_customer' ? __('Customer') : __('Product') }}</th><th class="r">Kg</th><th class="r">{{ __('Sales') }}</th>
                        <th class="r">HPP</th><th class="r">{{ __('Margin') }}</th><th class="r">{{ __('Unvalued barcodes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report[$key] as $group)
                        <tr>
                            <td>{{ $group['name'] }}</td><td class="r">{{ $number($group['kg']) }}</td><td class="r">{{ $rupiah($group['sales']) }}</td>
                            <td class="r">{{ $rupiah($group['hpp']) }}</td><td class="r">{{ $rupiah($group['margin']) }}</td><td class="r">{{ $group['unvalued_barcodes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
