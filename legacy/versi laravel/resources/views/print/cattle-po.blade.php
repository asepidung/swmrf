<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Purchase Order Cattle - {{ $po->po_number }}</title>
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
    </style>
</head>

<body>

    <div style="padding: 10px;">
        <div class="header">
            <div class="logo-box">
                <img src="{{ asset('img/LOGO-Y.png') }}" alt="LOGO">
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
                    <strong>PO No:</strong> {{ $po->po_number }}<br>
                    <strong>Created:</strong> {{ $po->created_at->format('d-M-Y') }}<br>
                    <strong style="color: #d9534f;">Est. Arrival:</strong> {{ \Carbon\Carbon::parse($po->po_date)->format('d-M-Y') }}
                </div>
            </div>
        </div>

        <div class="meta-container">
            <div class="meta-box">
                <h4>Vendor / Supplier</h4>
                <div class="meta-content">{{ $po->supplier->name ?? 'Unknown Supplier' }}</div>
                <div class="meta-address">
                    {{ $po->supplier->address ?? 'No Address Provided' }}<br>
                    <strong>Terms of Payment:</strong> {{ $po->supplier->term_of_payment ?? '0' }} Days
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
                    <th width="25%">Cattle Class</th>
                    <th width="15%">Qty (Head)</th>
                    <th width="20%">Price / Kg</th>
                    <th width="35%">Item Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->cattleCategory->name ?? '-' }}</td>
                    <td class="text-center">{{ number_format($item->qty_head, 0, ',', '.') }} Ekor</td>
                    <td class="text-right">Rp {{ number_format($item->price_per_kg, 0, ',', '.') }}</td>
                    <td>{{ $item->note ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-container">
            <div class="note-section">
                <strong>General Notes:</strong>
                <div class="note-box">
                    {{ $po->note ?? 'Tidak ada catatan tambahan.' }}
                </div>
            </div>
        </div>

        <div class="sig-container">
            <div class="sig-box">
                <p>Purchasing,</p>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $po->creator->name ?? 'AYU' }}</div>
                <div class="sig-role">Purchasing Dept.</div>
            </div>

            <div class="sig-box">
                <p>Mengetahui,</p>
                <div class="sig-space"></div>
                <div class="sig-name">AHMAD</div>
                <div class="sig-role">Finance Manager</div>
            </div>

            <div class="sig-box">
                <p>Supplier Confirmation,</p>
                <div class="sig-space"></div>
                <div class="sig-name">( ____________________ )</div>
                <div class="sig-role">Name & Stamp</div>
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