<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Purchase Order Material - {{ $record->po_number }}</title>
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
                @php
                    $path = public_path('img/light.png');
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                @endphp
                <img src="{{ $base64 }}" alt="LOGO">
            </div>
            <div class="company-info">
                <div class="company-name">PT. SANTI WIJAYA MEAT</div>
                <div class="company-address">
                    PERUM ASABRI RT 001/RW 005, Desa Sukasirna, Kec. Jonggol,<br>
                    Kab. Bogor, Jawa Barat, 16830 Phone: 0813 6006 959
                </div>
            </div>
            <div class="doc-title-box">
                <h2>PURCHASE ORDER</h2>
                <div class="doc-meta">
                    <strong>PO No:</strong> {{ $record->po_number }}<br>
                    <strong>PO Date:</strong> {{ $record->po_date ? \Carbon\Carbon::parse($record->po_date)->format('d-M-Y') : $record->created_at->format('d-M-Y') }}<br>
                </div>
            </div>
        </div>

        <div class="meta-container">
            <div class="meta-box">
                <h4>Vendor / Supplier</h4>
                <div class="meta-content">{{ $record->supplier->name ?? 'Unknown Supplier' }}</div>
                <div class="meta-address">
                    {{ $record->supplier->address ?? 'No Address Provided' }}<br>
                    <strong>Terms of Payment:</strong> {{ $record->supplier->top_days ?? '0' }} Days (after goods received)
                </div>
            </div>
            <div class="meta-box">
                <h4>Ship To</h4>
                <div class="meta-content">PT. SANTI WIJAYA MEAT - RPH Jonggol</div>
                <div class="meta-address">
                    Jl. SMPN 1 Jonggol Kp. Menan Rt 04/01 Ds. Sukamaju<br>
                    Kec. Jonggol Kab. Bogor POS 16830
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="30%">Material Name</th>
                    <th width="10%">Qty</th>
                    <th width="15%">Unit Price</th>
                    <th width="20%">Total</th>
                    <th width="20%">Item Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach($record->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->material->name ?? '-' }}</td>
                    <td class="text-center">{{ number_format($item->qty, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->subtotal ?? ($item->qty * $item->price), 0, ',', '.') }}</td>
                    <td>{{ $item->note ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-container">
            <div class="note-section">
                <strong>General Notes:</strong>
                <div class="note-box">
                    {{ $record->note ?? 'Tidak ada catatan tambahan.' }}
                </div>
            </div>
            
                        <div style="width: 300px; margin-top: 10px;">
                <table style="border: none; margin: 0;">
                    @php
                    $taxAmount = $record->materialRequisition->tax_amount ?? 0;
                    $subtotal = $record->total_amount - $taxAmount;
                    $dpAmount = $record->supplierPayments()->sum('amount') ?? 0;
                    $remaining = $record->total_amount - $dpAmount;
                    @endphp
                    <tr>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; white-space: nowrap;">Subtotal:</td>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; font-size: 14px; white-space: nowrap;">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($taxAmount > 0)
                    <tr>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; white-space: nowrap;">Tax 11%:</td>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; font-size: 14px; white-space: nowrap;">Rp {{ number_format($taxAmount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; white-space: nowrap;">Grand Total:</td>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; font-size: 14px; white-space: nowrap;">Rp {{ number_format($record->total_amount, 0, ',', '.') }}</td>
                    </tr>
                    @if($dpAmount > 0)
                    <tr>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; color: green; white-space: nowrap;">DP:</td>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; font-size: 14px; color: green; white-space: nowrap;">- Rp {{ number_format($dpAmount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; color: #d32f2f; white-space: nowrap;">Remaining:</td>
                        <td style="border: none; text-align: right; font-weight: bold; padding: 4px; font-size: 14px; color: #d32f2f; white-space: nowrap;">Rp {{ number_format($remaining, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="sig-container" style="justify-content: flex-end; gap: 40px;">
            <div class="sig-box">
                <p>Purchasing,</p>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $record->materialRequisition->reviewer->name ?? '-' }}</div>
                <div class="sig-role">Purchasing Dept.</div>
            </div>
            <div class="sig-box">
                <p>Approved By,</p>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $record->approvedBy->name ?? '-' }}</div>
                <div class="sig-role">Finance / Direktur</div>
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
