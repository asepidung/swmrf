<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Price List - {{ strtoupper($priceList->customerGroup->name) }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #000000;
            --text-main: #2d3748;
            --text-muted: #718096;
            --border-color: #e2e8f0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            background-color: #ffffff;
            font-size: 9pt;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* ----- HEADER ----- */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            /* Diubah agar sejajar di bagian atas */
            border-bottom: 2.5px solid var(--primary);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .company-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .company-logo {
            height: 60px;
        }

        .company-details h1 {
            font-size: 16pt;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 3px 0;
            letter-spacing: -0.3px;
        }

        .company-details p {
            margin: 0;
            color: var(--text-muted);
            font-size: 8.5pt;
        }

        /* ----- INFO DOKUMEN ----- */
        .document-info {
            width: 280px;
            /* Lebar tetap agar tabel rapi */
        }

        .document-title {
            font-size: 18pt;
            font-weight: 800;
            color: var(--text-main);
            text-transform: uppercase;
            margin: 0 0 10px 0;
            letter-spacing: 1.5px;
            text-align: right;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 4px 0;
            font-size: 8.5pt;
            vertical-align: middle;
        }

        .meta-label {
            font-weight: 600;
            color: var(--text-muted);
            text-align: left;
            /* Rata kiri untuk label */
            text-transform: uppercase;
            width: 45%;
        }

        .meta-value {
            font-weight: 700;
            text-align: right;
            /* Rata kanan untuk nilai */
            width: 55%;
        }

        /* ----- TABEL HARGA ----- */
        table.price-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 35px;
        }

        table.price-table th {
            background-color: var(--primary);
            color: #ffffff;
            font-weight: 600;
            font-size: 8.5pt;
            padding: 10px 12px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        table.price-table th.center {
            text-align: center;
        }

        table.price-table th.right {
            text-align: right;
        }

        table.price-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 9pt;
            vertical-align: middle;
        }

        table.price-table td.center {
            text-align: center;
        }

        table.price-table td.right {
            text-align: right;
        }

        table.price-table tr:nth-child(even) td {
            background-color: #f8fafc;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        .product-name {
            font-weight: 600;
            font-size: 9.5pt;
            color: var(--text-main);
        }

        .price-amount {
            font-weight: 700;
            font-size: 10pt;
        }

        /* ----- FOOTER ----- */
        .footer-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .contact-info {
            font-size: 8.5pt;
            color: var(--text-muted);
        }

        .contact-info strong {
            display: block;
            font-size: 10pt;
            color: var(--primary);
            margin-bottom: 6px;
        }

        .signature-area {
            text-align: center;
            width: 220px;
        }

        .signature-title {
            font-size: 9pt;
            color: var(--text-main);
            margin-bottom: 70px;
        }

        .signature-name {
            font-weight: 700;
            font-size: 10pt;
            border-top: 1px solid var(--text-main);
            padding-top: 6px;
        }

        @media print {
            body {
                padding: 0;
            }

            @page {
                size: A4 portrait;
                margin: 15mm;
            }
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="header-section">
            <div class="company-profile">
                <img src="{{ asset('img/LOGO-Y.png') }}" alt="Logo" class="company-logo">
                <div class="company-details">
                    <h1>PT SANTI WIJAYA MEAT</h1>
                    <p><strong>Committed to Meeting Your Need</strong><br>
                        Jl. Perum Asabri Blok B Desa Sukasirna<br>
                        Kec. Jonggol, Kab. Bogor, Jawa Barat</p>
                </div>
            </div>

            <div class="document-info">
                <div class="document-title">PRICE LIST</div>
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Customer</td>
                        <td class="meta-value" style="font-size: 11pt; color: var(--primary);">{{ strtoupper($priceList->customerGroup->name) }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Last Update</td>
                        <td class="meta-value">{{ $priceList->updated_at->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Issued By</td>
                        <td class="meta-value">{{ $priceList->creator->name ?? 'Administrator' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="price-table">
            <thead>
                <tr>
                    <th class="center" style="width: 5%;">NO</th>
                    <th style="width: 28%;">DESCRIPTION</th>
                    <th class="center" style="width: 15%;">CATEGORY</th>
                    <th class="center" style="width: 12%;">BRAND</th>
                    <th class="right" style="width: 20%;">PRICE (IDR)</th>
                    <th class="center" style="width: 20%;">NOTES</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($priceList->items as $index => $item)
                <tr>
                    <td class="center" style="color: var(--text-muted);">{{ $index + 1 }}</td>
                    <td class="product-name">{{ strtoupper($item->product->name ?? '-') }}</td>
                    <td class="center">{{ strtoupper($item->product->category->name ?? 'OFFAL') }}</td>
                    <td class="center">Wijaya Meat</td>
                    <td class="right price-amount">{{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="center" style="font-size: 8.5pt; color: var(--text-muted);">{{ $item->note ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="center" style="padding: 20px; color: var(--text-muted);">
                        Belum ada item produk di dalam Price List ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-section">
            <div class="contact-info">
                <strong>Informasi & Pemesanan:</strong>
                Muryani<br>
                Tel: 0818 0898 5323<br>
                Email: yani@wijayameat.co.id
            </div>
            <div class="signature-area">
                <div class="signature-title">Hormat Kami,</div>
                <div class="signature-name">PT Santi Wijaya Meat</div>
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