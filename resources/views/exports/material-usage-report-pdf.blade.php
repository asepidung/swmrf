<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Material Usage Report') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h2 { text-align: center; margin: 0 0 4px 0; }
        .period { text-align: center; margin-bottom: 18px; color: #555; }
        h3 { font-size: 12px; text-transform: uppercase; margin: 18px 0 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f2f2f2; font-weight: bold; }
        .r { text-align: right; }
        .note { font-size: 10px; color: #666; }
    </style>
</head>
@php
    $number = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $rupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp
<body>
    <h2>{{ __('Material Usage Report') }}</h2>
    <div class="period">
        {{ \Carbon\Carbon::parse($report['from'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($report['until'])->format('d/m/Y') }}
        &middot; {{ count($report['documents']) }} {{ __('documents') }}
    </div>

    <h3>{{ __('Usage per BOM') }}</h3>
    <table>
        <thead><tr><th>{{ __('Material') }}</th><th class="r" width="22%">{{ __('Quantity') }}</th></tr></thead>
        <tbody>
            @forelse ($report['bom'] as $line)
                <tr><td>{{ $line['material'] }}</td><td class="r">{{ $number($line['qty']) }} {{ __('pcs') }}</td></tr>
            @empty
                <tr><td colspan="2">{{ __('No locked documents in this period.') }}</td></tr>
            @endforelse
            <tr>
                <td>{{ __('Drylog / Pad Absorber') }} ({{ $rupiah($report['drylog']['amount']) }})</td>
                <td class="r">{{ $number($report['drylog']['qty']) }} {{ __('pcs') }}</td>
            </tr>
        </tbody>
    </table>

    <h3>{{ __('Material Waste') }}</h3>
    <table>
        <thead>
            <tr><th>{{ __('Material') }}</th><th class="r" width="18%">{{ __('Quantity') }}</th><th class="r" width="26%">{{ __('Value') }}</th></tr>
        </thead>
        <tbody>
            @forelse ($report['wastes'] as $line)
                <tr><td>{{ $line['material'] }}</td><td class="r">{{ $number($line['qty']) }} {{ __('pcs') }}</td><td class="r">{{ $rupiah($line['amount']) }}</td></tr>
            @empty
                <tr><td colspan="3">{{ __('Nothing was wasted.') }}</td></tr>
            @endforelse
            <tr>
                <td><strong>{{ __('Total wasted') }}</strong></td>
                <td class="r"><strong>{{ $number($report['waste_total']['qty']) }} {{ __('pcs') }}</strong></td>
                <td class="r"><strong>{{ $rupiah($report['waste_total']['amount']) }}</strong></td>
            </tr>
        </tbody>
    </table>
    @if ($report['unpriced'] > 0)
        <div class="note">* {{ $report['unpriced'] }} {{ __('rows without a price are counted as Rp 0.') }}</div>
    @endif

    <h3>{{ __('Per document') }}</h3>
    <table>
        <thead>
            <tr>
                <th width="12%">{{ __('Process') }}</th><th>{{ __('Document') }}</th><th width="15%">{{ __('Date') }}</th>
                <th class="r" width="14%">{{ __('Drylog / Pad Absorber') }}</th><th class="r" width="12%">{{ __('Wasted') }}</th><th class="r" width="20%">{{ __('Value') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['documents'] as $document)
                <tr>
                    <td>{{ $document['kind'] === 'boning' ? 'Boning' : 'Repack' }}</td>
                    <td>{{ $document['doc_no'] }}</td>
                    <td>{{ \Carbon\Carbon::parse($document['date'])->format('d/m/Y') }}</td>
                    <td class="r">{{ $document['drylog'] === null ? '-' : $number($document['drylog']) }}</td>
                    <td class="r">{{ $number($document['waste_qty']) }}</td>
                    <td class="r">{{ $rupiah($document['waste_amount']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">{{ __('No locked documents in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
