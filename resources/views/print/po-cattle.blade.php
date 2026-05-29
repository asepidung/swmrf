<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print PO Cattle - {{ $record->document_number }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 3px solid #dc2626; /* SWM Red */
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo-container {
            width: 250px;
        }
        .logo-container img {
            max-width: 100%;
            height: auto;
        }
        .company-info {
            text-align: right;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #dc2626;
            margin: 0;
        }
        .slogan {
            font-style: italic;
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .doc-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            vertical-align: top;
            padding: 5px;
        }
        .info-table .label {
            font-weight: bold;
            width: 120px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .items-table th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        .items-table .text-right {
            text-align: right;
        }
        .items-table .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature {
            text-align: center;
            width: 250px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 80px;
            padding-top: 5px;
        }
        @media print {
            body {
                padding: 0;
            }
            @page {
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 500);">

    <div class="header">
        <div class="logo-container">
            <!-- Assuming light.png is in public/images/ or similar. We use asset() -->
            <img src="{{ asset('images/light.png') }}" alt="SWM Logo" onerror="this.src='https://via.placeholder.com/250x80?text=SWM+Logo'">
        </div>
        <div class="company-info">
            <h1 class="company-name">PT SANTI WIJAYA MEAT</h1>
            <div class="slogan">"Committed to Meeting Your Need"</div>
        </div>
    </div>

    <div class="doc-title">PURCHASE ORDER (CATTLE)</div>

    <table class="info-table">
        <tr>
            <td width="50%">
                <table width="100%">
                    <tr>
                        <td class="label">Supplier:</td>
                        <td>{{ $record->supplier->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Address:</td>
                        <td>{{ $record->supplier->address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Phone:</td>
                        <td>{{ $record->supplier->phone ?? '-' }}</td>
                    </tr>
                </table>
            </td>
            <td width="50%">
                <table width="100%">
                    <tr>
                        <td class="label">PO Number:</td>
                        <td><strong>{{ $record->document_number }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Date:</td>
                        <td>{{ $record->created_at->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Shipping Date:</td>
                        <td>{{ $record->shipping_date->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Ship To:</td>
                        <td>RPH Jonggol Jl. SMPN 01 Jonggol<br>Kp. Menan Rt 06 Rw 02 Ds. Sukamaju<br>Kec. Jonggol Kab. Bogor 16830</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="35%">Cattle Class</th>
                <th width="15%" class="text-center">Qty (Head)</th>
                <th width="20%" class="text-right">Price</th>
                <th width="25%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $totalQty = 0; $totalAmount = 0; @endphp
            @foreach($record->items as $index => $item)
            @php 
                $subtotal = $item->qty * $item->price;
                $totalQty += $item->qty;
                $totalAmount += $subtotal;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    {{ $item->cattleClass->name ?? '-' }}
                    @if($item->item_notes)
                        <br><small style="color: #666;">Note: {{ $item->item_notes }}</small>
                    @endif
                </td>
                <td class="text-center">{{ number_format($item->qty) }}</td>
                <td class="text-right">{{ number_format($item->price, 2) }}</td>
                <td class="text-right">{{ number_format($subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-right">Grand Total</th>
                <th class="text-center">{{ number_format($totalQty) }}</th>
                <th></th>
                <th class="text-right">{{ number_format($totalAmount, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    @if($record->summary_note)
    <div style="margin-bottom: 20px;">
        <strong>Notes:</strong><br>
        {!! nl2br(e($record->summary_note)) !!}
    </div>
    @endif

    <div class="footer">
        <div class="signature">
            <div style="margin-bottom: 60px;">Created By,</div>
            <div class="signature-line">{{ $record->creator->name ?? 'Admin' }}</div>
        </div>
        <div class="signature">
            <div style="margin-bottom: 60px;">Approved By,</div>
            <div class="signature-line">Director</div>
        </div>
        <div class="signature">
            <div style="margin-bottom: 60px;">Supplier / Vendor,</div>
            <div class="signature-line">{{ $record->supplier->name ?? '-' }}</div>
        </div>
    </div>

</body>
</html>
