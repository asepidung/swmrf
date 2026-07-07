<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Stock Opname - {{ $record->document_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            margin: 0;
        }

        .header {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .logo-box {
            width: 80px;
            margin-right: 20px;
        }

        .logo-box img {
            width: 100%;
            height: auto;
        }

        .company-info {
            flex-grow: 1;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            margin: 0;
        }

        .company-address {
            font-size: 10px;
            color: #333;
            margin-top: 3px;
            line-height: 1.3;
        }

        .doc-title-box {
            text-align: right;
            min-width: 200px;
        }

        .doc-title-box h2 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
            color: #000;
            border-bottom: 1px solid #333;
            display: inline-block;
        }

        .doc-meta {
            margin-top: 8px;
            font-size: 11px;
            text-align: right;
        }

        .meta-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
        }

        .meta-box {
            width: 50%;
            border: 1px solid #000;
            padding: 8px;
            border-radius: 2px;
        }

        .meta-box h4 {
            margin: 0 0 5px 0;
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }

        .meta-content {
            font-size: 12px;
            font-weight: bold;
        }

        .meta-address {
            font-size: 10px;
            font-weight: normal;
            margin-top: 4px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table th {
            background: #fafafa;
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            text-transform: uppercase;
            font-size: 10px;
        }

        table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .note-section {
            width: 100%;
        }

        .note-box {
            border: 1px solid #ccc;
            padding: 8px;
            min-height: 40px;
            margin-top: 5px;
            font-size: 10px;
            font-style: italic;
        }

        .sig-container {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .sig-box {
            width: 30%;
            text-align: center;
        }

        .sig-space {
            height: 60px;
        }

        .sig-name {
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            font-size: 11px;
        }

        .sig-role {
            font-size: 10px;
            color: #555;
        }

        @media print {
            body {
                background: none;
            }

            .no-print {
                display: none;
            }
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(255, 0, 0, 0.15);
            font-weight: bold;
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
            user-select: none;
        }
    </style>
</head>

<body>

    @if($record->trashed())
        <div class="watermark">DELETED</div>
    @endif

    <div style="padding: 10px;">
        <div class="header">
            <div class="logo-box">
                <img src="{{ asset('img/light.png') }}" alt="LOGO">
            </div>
            <div class="company-info">
                <div class="company-name">PT. SANTI WIJAYA MEAT</div>
                <div class="company-address">
                    PERUM ASABRI RT 001/RW 005, Desa Sukasirna, Kec. Jonggol,<br>
                    Kab. Bogor, Jawa Barat, 16830 Phone: 0813 6006 959
                </div>
            </div>
            <div class="doc-title-box">
                <h2>STOCK OPNAME</h2>
                <div class="doc-meta">
                    <strong>Doc No:</strong> {{ $record->document_number }}<br>
                    <strong>Date:</strong> {{ \Carbon\Carbon::parse($record->date)->format('d-M-Y') }}<br>
                    <strong>Period:</strong> {{ $record->period }}
                </div>
            </div>
        </div>

        <div class="meta-container">
            <div class="meta-box">
                <h4>{{ __('Document Status') }}</h4>
                <div class="meta-content">{{ $record->status }}</div>
                <div class="meta-address">
                    <strong>{{ __('Total Fisik (Karton/Pcs)') }}:</strong> {{ $record->items->whereIn('status', ['MATCHED', 'UNEXPECTED'])->count() }} {{ __('Items') }}<br>
                    <strong>Total Fisik (Weight):</strong> {{ number_format($record->items->whereIn('status', ['MATCHED', 'UNEXPECTED'])->sum('weight'), 2, ',', '.') }} Kg
                </div>
            </div>
            <div class="meta-box">
                <h4>{{ __('Item Progress Summary') }}</h4>
                <div class="meta-content">
                    <span style="color: #5cb85c;">Match: {{ $record->items->where('status', 'MATCHED')->count() }}</span> | 
                    <span style="color: #f0ad4e;">Found: {{ $record->items->where('status', 'UNEXPECTED')->count() }}</span>
                </div>
                <div class="meta-address">
                    <strong style="color: #d9534f;">{{ __('Missing (Tidak Ditemukan)') }}:</strong> {{ $record->items->where('status', 'MISSING')->count() }} {{ __('Items') }}
                </div>
            </div>
        </div>

        @php
            $summary = [];
            foreach($record->items as $item) {
                $key = $item->product_id ?? 0;
                if (!isset($summary[$key])) {
                    $summary[$key] = [
                        'product' => $item->product->name ?? '-',
                        'matched' => 0,
                        'missing' => 0,
                        'found' => 0,
                    ];
                }
                
                if ($item->status === 'MATCHED') {
                    $summary[$key]['matched'] += $item->weight;
                } elseif ($item->status === 'MISSING') {
                    $summary[$key]['missing'] += $item->weight;
                } elseif ($item->status === 'UNEXPECTED') {
                    $summary[$key]['found'] += $item->weight;
                }
            }
        @endphp

        <table>
            <thead>
                <tr>
                    <th width="5%">{{ __('No') }}</th>
                    <th width="25%">{{ __('Item Name') }}</th>
                    <th width="12%">{{ __('System Stock') }}</th>
                    <th width="12%">{{ __('Matched') }}</th>
                    <th width="11%">{{ __('Found (+)') }}</th>
                    <th width="11%">{{ __('Missing (-)') }}</th>
                    <th width="12%">{{ __('Total Fisik') }}</th>
                    <th width="12%">{{ __('Selisih') }}</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $no = 1; 
                    $total_system = 0;
                    $total_matched = 0;
                    $total_found = 0;
                    $total_missing = 0;
                    $total_fisik = 0;
                    $total_selisih = 0;
                @endphp
                @forelse($summary as $row)
                    @php
                        $system = $row['matched'] + $row['missing'];
                        $fisik = $row['matched'] + $row['found'];
                        $selisih = $fisik - $system; // or $row['found'] - $row['missing']
                        
                        $total_system += $system;
                        $total_matched += $row['matched'];
                        $total_found += $row['found'];
                        $total_missing += $row['missing'];
                        $total_fisik += $fisik;
                        $total_selisih += $selisih;
                    @endphp
                <tr>
                    <td class="text-center">{{ $no++ }}</td>
                    <td>{{ $row['product'] }}</td>
                    
                    <td class="text-right">{{ number_format($system, 2, ',', '.') }}</td>
                    <td class="text-right" style="color: #5cb85c;">{{ number_format($row['matched'], 2, ',', '.') }}</td>
                    <td class="text-right" style="color: #f0ad4e;">{{ number_format($row['found'], 2, ',', '.') }}</td>
                    <td class="text-right" style="color: #d9534f;">{{ number_format($row['missing'], 2, ',', '.') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($fisik, 2, ',', '.') }}</td>
                    <td class="text-right" style="font-weight: bold; {{ $selisih > 0 ? 'color: #5cb85c;' : ($selisih < 0 ? 'color: #d9534f;' : '') }}">
                        {{ $selisih > 0 ? '+' : '' }}{{ number_format($selisih, 2, ',', '.') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">{{ __('No item data available.') }}</td>
                </tr>
                @endforelse
            </tbody>
            @if(count($summary) > 0)
            <tfoot>
                <tr style="background: #fafafa; font-weight: bold;">
                    <td colspan="2" class="text-center">{{ __('GRAND TOTAL') }}</td>
                    <td class="text-right">{{ number_format($total_system, 2, ',', '.') }} Kg</td>
                    <td class="text-right" style="color: #5cb85c;">{{ number_format($total_matched, 2, ',', '.') }} Kg</td>
                    <td class="text-right" style="color: #f0ad4e;">{{ number_format($total_found, 2, ',', '.') }} Kg</td>
                    <td class="text-right" style="color: #d9534f;">{{ number_format($total_missing, 2, ',', '.') }} Kg</td>
                    <td class="text-right">{{ number_format($total_fisik, 2, ',', '.') }} Kg</td>
                    <td class="text-right" style="{{ $total_selisih > 0 ? 'color: #5cb85c;' : ($total_selisih < 0 ? 'color: #d9534f;' : '') }}">
                        {{ $total_selisih > 0 ? '+' : '' }}{{ number_format($total_selisih, 2, ',', '.') }} Kg
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>

        <div class="footer-container">
            <div class="note-section">
                <strong>{{ __('General Notes') }}:</strong>
                <div class="note-box">
                    {{ $record->summary_note ?? __('Tidak ada catatan tambahan.') }}
                </div>
            </div>
        </div>

        <div class="sig-container" style="justify-content: space-between;">
            <div class="sig-box">
                <p>{{ __('Checked by') }},</p>
                <div class="sig-space"></div>
                <div class="sig-name">_________________</div>
                <div class="sig-role">{{ __('Warehouse SPV') }}</div>
            </div>
            
            <div class="sig-box">
                <p>{{ __('Prepared by') }},</p>
                <div class="sig-space"></div>
                <div class="sig-name">{{ strtoupper($record->creator->name ?? __('OFFICER')) }}</div>
                <div class="sig-role">{{ __('Officer / Admin') }}</div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };

        window.onafterprint = function() {
            window.close();
        };
    </script>

</body>

</html>
