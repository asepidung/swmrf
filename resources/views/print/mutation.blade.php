<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan Mutasi - {{ $record->mutation_number }}</title>
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
                <h2>SURAT JALAN MUTASI</h2>
                <div class="doc-meta">
                    <strong>No Mutasi:</strong> {{ $record->mutation_number }}<br>
                    <strong>Tanggal:</strong> {{ $record->mutation_date->format('d-M-Y') }}<br>
                    <strong style="color: #d9534f;">Status:</strong> {{ $record->status }}
                </div>
            </div>
        </div>

        <div class="meta-container">
            <div class="meta-box">
                <h4>DARI GUDANG (ASAL)</h4>
                <div class="meta-content">{{ $record->fromWarehouse->name }}</div>
                <div class="meta-address">
                    <strong>Dibuat Oleh:</strong> {{ $record->createdBy->name ?? '-' }}
                </div>
            </div>
            <div class="meta-box">
                <h4>TUJUAN GUDANG</h4>
                <div class="meta-content">{{ $record->toWarehouse->name }}</div>
                <div class="meta-address">
                    <strong>Diterima Oleh:</strong> {{ $record->receivedBy->name ?? 'Belum Diterima' }}
                </div>
            </div>
        </div>

        @php
            $summary = \App\Models\MutationItem::where('mutation_id', $record->id)
                ->join('products', 'mutation_items.product_id', '=', 'products.id')
                ->selectRaw('products.name as product_name, sum(weight) as total_weight, sum(qty_pcs) as total_pcs, count(barcode) as total_carton')
                ->groupBy('products.name')
                ->get();
        @endphp

        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="45%">Nama Produk</th>
                    <th width="15%">Total BOX</th>
                    <th width="20%">Berat (Kg)</th>
                    <th width="15%">Qty (Pcs)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-center">{{ $item->total_carton }}</td>
                    <td class="text-right">{{ number_format($item->total_weight, 2, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($item->total_pcs, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background-color: #f9f9f9;">
                    <td colspan="2" class="text-right">TOTAL KESELURUHAN</td>
                    <td class="text-center">{{ number_format($summary->sum('total_carton'), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($summary->sum('total_weight'), 2, ',', '.') }} Kg</td>
                    <td class="text-center">{{ number_format($summary->sum('total_pcs'), 0, ',', '.') }} Pcs</td>
                </tr>
            </tfoot>
        </table>

        <div class="footer-container">
            <div class="note-section">
                <strong>Catatan Mutasi:</strong>
                <div class="note-box">
                    {{ $record->note ?? 'Tidak ada catatan tambahan.' }}
                </div>
            </div>
        </div>

        <div class="sig-container">
            <div class="sig-box">
                <p>Pengirim,</p>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $record->createdBy->name ?? '................................' }}</div>
                <div class="sig-role">Gudang Asal</div>
            </div>
            <div class="sig-box">
                <p>Mengetahui,</p>
                <div class="sig-space"></div>
                <div class="sig-name">................................</div>
                <div class="sig-role">Checker</div>
            </div>
            <div class="sig-box">
                <p>Penerima,</p>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $record->receivedBy->name ?? '................................' }}</div>
                <div class="sig-role">Gudang Tujuan</div>
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
