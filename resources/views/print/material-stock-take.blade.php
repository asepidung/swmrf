<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Material Stock Opname - {{ $record->document_number }}</title>
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
            border: 1px solid #000;
            padding: 10px;
            min-height: 50px;
            margin-top: 5px;
            margin-bottom: 20px;
            border-radius: 2px;
        }

        .sig-container {
            display: flex;
            margin-top: 30px;
        }

        .sig-box {
            width: 30%;
            text-align: center;
        }

        .sig-box p {
            margin: 0 0 50px 0;
        }

        .sig-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .sig-role {
            font-size: 10px;
            color: #555;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <div class="logo-box">
                <!-- Replace with actual logo -->
                <img src="{{ asset('img/light.png') }}" alt="Logo" onerror="this.style.display='none'">
            </div>
            <div class="company-info">
                <h1 class="company-name">PT. SANTI WIJAYA MEAT</h1>
                <div class="company-address">
                    PERUM ASABRI RT 001/RW 005, Desa Sukasirna, Kec. Jonggol,<br>
                    Kab. Bogor, Jawa Barat, 16830 Phone: 0813 6006 959
                </div>
            </div>
            <div class="doc-title-box">
                <h2>MATERIAL OPNAME</h2>
                <div class="doc-meta">
                    <strong>Doc No:</strong> {{ $record->document_number }}<br>
                    <strong>Date:</strong> {{ \Carbon\Carbon::parse($record->date)->format('d-M-Y') }}<br>
                    <strong>Period:</strong> {{ $record->periode }}
                </div>
            </div>
        </div>

        <div class="meta-container">
            <div class="meta-box">
                <h4>{{ __('Document Status') }}</h4>
                <div class="meta-content">{{ $record->status }}</div>
                <div class="meta-address">
                    <strong>{{ __('Total Items Checked') }}:</strong> {{ $record->items->count() }} {{ __('Items') }}<br>
                </div>
            </div>
            <div class="meta-box">
                <h4>{{ __('Variance Summary') }}</h4>
                <div class="meta-content">
                    <span style="color: #5cb85c;">Sesuai: {{ $record->items->where('difference_qty', 0)->whereNotNull('physical_qty')->count() }}</span> | 
                    <span style="color: #f0ad4e;">Lebih: {{ $record->items->where('difference_qty', '>', 0)->count() }}</span>
                </div>
                <div class="meta-address">
                    <strong style="color: #d9534f;">Kurang:</strong> {{ $record->items->where('difference_qty', '<', 0)->count() }} Items |
                    <strong>Kosong / Hilang:</strong> {{ $record->items->whereNull('physical_qty')->count() }} Items
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="5%">{{ __('No') }}</th>
                    <th width="15%">{{ __('Code') }}</th>
                    <th width="30%">{{ __('Item Name') }}</th>
                    <th width="15%">{{ __('System Qty') }}</th>
                    <th width="15%">{{ __('Physical Qty') }}</th>
                    <th width="20%">{{ __('Variance') }}</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $no = 1; 
                @endphp
                @forelse($record->items as $item)
                    @php
                        $sys = $item->system_qty ?? 0;
                        $phys = $item->physical_qty ?? 0;
                        $diff = $item->difference_qty ?? 0;
                        $isNull = is_null($item->physical_qty);
                    @endphp
                <tr>
                    <td class="text-center">{{ $no++ }}</td>
                    <td class="text-center">{{ $item->material->code ?? '-' }}</td>
                    <td>{{ $item->material->name ?? '-' }}</td>
                    
                    <td class="text-right">{{ number_format($sys, 2, ',', '.') }}</td>
                    <td class="text-right" style="font-weight: bold;">
                        @if($isNull)
                            <span style="color: #d9534f;">- (Kosong)</span>
                        @else
                            {{ number_format($phys, 2, ',', '.') }}
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: bold; {{ $diff > 0 ? 'color: #5cb85c;' : ($diff < 0 ? 'color: #d9534f;' : '') }}">
                        @if($isNull)
                            <span style="color: #d9534f;">Hilang</span>
                        @else
                            {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">{{ __('No item data available.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-container">
            <div class="note-section">
                <strong>{{ __('Notes') }}:</strong>
                <div class="note-box">
                    {{ $record->note ?? __('No additional notes.') }}
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
