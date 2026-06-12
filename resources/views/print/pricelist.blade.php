<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $record->customerGroup->name }} - Price List</title>
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
            width: 100%;
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
            margin-top: 20px;
        }

        .marketing-info {
            font-size: 10px;
            color: #333;
            line-height: 1.5;
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
                <h2>PRICE LIST</h2>
                <div class="doc-meta">
                    <strong>Price Update:</strong> {{ $record->updated_at ? $record->updated_at->format('d-M-Y') : '-' }}<br>
                    <strong>Printed:</strong> {{ date('d-M-Y') }}
                </div>
            </div>
        </div>

        <div class="meta-container">
            <div class="meta-box">
                <h4>Customer Details</h4>
                <div class="meta-content">{{ $record->customerGroup->name }}</div>
                <div class="meta-address">
                    <strong>UP / CP:</strong> {{ $record->customerGroup->customers->pluck('pic')->filter()->unique()->implode(', ') ?: '-' }}
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="35%">Prod Desc</th>
                    <th width="20%">Prod Category</th>
                    <th width="15%">Brand</th>
                    <th width="15%">Price</th>
                    <th width="10%">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($record->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->product->category->name ?? '-' }}</td>
                    <td class="text-center">Wijaya Meat</td>
                    <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                    <td>{{ $item->note ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">No prices defined.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-container">
            <div class="marketing-info">
                <strong>Informasi Lebih Lanjut Silahkan Hubungi:</strong><br>
                Muryani 0818 0898 5323<br>
                yani@wijayameat.co.id
            </div>
        </div>

        <div class="sig-container">
            <div class="sig-box">
                <p>Dibuat Oleh,</p>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $record->creator->name ?? 'Admin' }}</div>
                <div class="sig-role">Sales Dept.</div>
            </div>
            <div class="sig-box">
                <p>Disetujui Oleh,</p>
                <div class="sig-space"></div>
                <div class="sig-name">MANAJEMEN</div>
                <div class="sig-role">PT. Santi Wijaya Meat</div>
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
