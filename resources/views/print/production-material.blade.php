<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ __('Material Usage') }} - {{ $document->doc_no }}</title>
    <style>
        @page { size: A4; margin: 1cm; }
        body { font-family: 'Arial', sans-serif; font-size: 11px; color: #333; line-height: 1.4; margin: 0; }
        .header { display: flex; align-items: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-box { width: 80px; margin-right: 20px; }
        .logo-box img { width: 100%; height: auto; }
        .company-info { flex-grow: 1; }
        .company-name { font-size: 18px; font-weight: bold; color: #000; margin: 0; }
        .company-address { font-size: 10px; color: #333; margin-top: 3px; line-height: 1.3; }
        .doc-title-box { text-align: right; min-width: 200px; }
        .doc-title-box h2 { margin: 0; font-size: 18px; text-transform: uppercase; color: #000; border-bottom: 1px solid #333; display: inline-block; }
        .doc-meta { margin-top: 8px; font-size: 11px; text-align: right; }
        h3 { font-size: 12px; text-transform: uppercase; margin: 18px 0 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table th { background: #fafafa; border: 1px solid #000; padding: 6px; text-align: center; text-transform: uppercase; font-size: 10px; }
        table td { border: 1px solid #000; padding: 6px; vertical-align: top; font-size: 11px; }
        .text-right { text-align: right; }
        .note { border: 1px solid #ccc; padding: 8px; font-size: 10px; font-style: italic; }
        .draft { border: 1px solid #c80; color: #a60; padding: 6px; margin-bottom: 10px; font-size: 10px; }
        .sig-container { margin-top: 40px; display: flex; justify-content: space-between; }
        .sig-box { width: 30%; text-align: center; }
        .sig-space { height: 60px; }
        .sig-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; font-size: 11px; }
        @media print { .no-print { display: none; } }
    </style>
</head>

@php
    $rupiah = fn (?float $value): string => $value === null ? '-' : 'Rp '.number_format($value, 0, ',', '.');
@endphp

<body>
    <div style="padding: 10px;">
        <div class="header">
            <div class="logo-box"><img src="{{ asset('img/light.png') }}" alt="LOGO"></div>
            <div class="company-info">
                <div class="company-name">PT. SANTI WIJAYA MEAT</div>
                <div class="company-address">
                    PERUM ASABRI RT 001/RW 005, Desa Sukasirna, Kec. Jonggol,<br>
                    Kab. Bogor, Jawa Barat, 16830 Phone: 0813 6006 959
                </div>
            </div>
            <div class="doc-title-box">
                <h2>{{ __('Material Usage') }}</h2>
                <div class="doc-meta">
                    <strong>{{ __('Process') }}:</strong> {{ $process }}<br>
                    <strong>{{ __('Document') }}:</strong> {{ $document->doc_no }}<br>
                    <strong>{{ __('Date') }}:</strong> {{ $date->format('d-M-Y') }}<br>
                    <strong>{{ __('Status') }}:</strong> {{ $document->status }}
                </div>
            </div>
        </div>

        @unless ($summary['final'])
            <div class="draft">{{ __('Not final yet. These figures are recalculated until the document is locked.') }}</div>
        @endunless

        <h3>{{ __('Usage per BOM') }}</h3>
        <table>
            <thead>
                <tr>
                    <th width="6%">{{ __('No') }}</th>
                    <th>{{ __('Material') }}</th>
                    <th width="18%">{{ __('Quantity') }}</th>
                    <th width="14%">{{ __('Unit') }}</th>
                    <th width="18%">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['bom'] as $row)
                    <tr>
                        <td class="text-right">{{ $loop->iteration }}</td>
                        <td>{{ $row['material'] }}</td>
                        <td class="text-right">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                        <td>{{ $row['unit'] }}</td>
                        <td class="text-right">
                            {{ $summary['final'] && $row['amount'] !== null ? $rupiah($row['amount']) : '-' }}
                            @if ($summary['final'] && $row['amount'] !== null && (float) $row['amount'] <= 0) *@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('No material usage according to the BOM.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($summary['final'])
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right"><strong>{{ __('Total usage value') }}</strong> ({{ __('incl. drylog') }})</td>
                        <td class="text-right"><strong>{{ $rupiah($summary['usage_total']) }}</strong></td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <h3>{{ __('Drylog / Pad Absorber') }}</h3>
        <table>
            <tbody>
                <tr>
                    <td>{{ $summary['drylog_name'] ?? __('Drylog / Pad Absorber') }}</td>
                    <td class="text-right" width="18%">
                        {{ $summary['drylog'] === null ? '-' : number_format($summary['drylog'], 0, ',', '.') }}
                    </td>
                    <td width="14%">{{ __('pcs') }}</td>
                    <td class="text-right" width="16%">
                        {{ $summary['final'] && $summary['drylog_amount'] !== null ? $rupiah($summary['drylog_amount']) : '-' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>{{ __('Material Waste') }}</h3>
        <table>
            <thead>
                <tr>
                    <th width="6%">{{ __('No') }}</th>
                    <th>{{ __('Material') }}</th>
                    <th width="14%">{{ __('Quantity') }}</th>
                    <th width="28%">{{ __('Reason') }}</th>
                    <th width="16%">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['wastes'] as $row)
                    <tr>
                        <td class="text-right">{{ $loop->iteration }}</td>
                        <td>{{ $row['material'] }}</td>
                        <td class="text-right">{{ number_format($row['qty'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                        <td>{{ $row['reason'] }}</td>
                        <td class="text-right">
                            {{ $rupiah($row['amount']) }}
                            @if ($summary['final'] && (float) $row['amount'] <= 0) *@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('Nothing was wasted.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($summary['final'] && count($summary['wastes']))
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right"><strong>{{ __('Total wasted') }}</strong></td>
                        <td class="text-right"><strong>{{ $rupiah($summary['waste_total']) }}</strong></td>
                    </tr>
                </tfoot>
            @endif
        </table>
        @if ($summary['unpriced'] + $summary['usage_unpriced'] > 0)
            <div class="note">* {{ __('no price yet') }}</div>
        @endif

        <div class="sig-container">
            <div class="sig-box"><div class="sig-space"></div><div class="sig-name">&nbsp;</div><div>{{ __('Prepared by') }}</div></div>
            <div class="sig-box"><div class="sig-space"></div><div class="sig-name">&nbsp;</div><div>{{ __('Checked by') }}</div></div>
            <div class="sig-box"><div class="sig-space"></div><div class="sig-name">&nbsp;</div><div>{{ __('Approved by') }}</div></div>
        </div>
    </div>

    <script>window.print();</script>
</body>

</html>
