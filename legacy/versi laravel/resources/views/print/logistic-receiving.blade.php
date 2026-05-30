<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>GR - {{ $receiving->receiving_number }} - {{ $receiving->supplier->name ?? 'Unknown' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Document Styles */
        :root {
            --accent: #f0ad4e;
            --ink: #111;
            --muted: #666;
            --line: #e7e7e7;
        }

        body {
            color: var(--ink);
            font-size: 13px;
            font-family: Arial, sans-serif;
        }

        .doc {
            max-width: 960px;
            margin: 24px auto 48px;
            padding: 0 16px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 12px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            height: 52px;
            width: auto;
        }

        .brand .name {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .brand .tag {
            font-size: 12px;
            color: var(--muted);
        }

        .title {
            margin: 18px 0 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .title h1 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .meta {
            margin-top: 12px;
            display: grid;
            grid-template-columns: auto 1fr auto 1fr;
            column-gap: 16px;
            row-gap: 6px;
            align-items: center;
        }

        .meta dt {
            font-weight: 600;
            margin: 0;
        }

        .meta dd {
            margin: 0;
        }

        table.wgh-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .wgh-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }

        .wgh-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .wgh-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .wgh-table td.center {
            text-align: center;
        }

        .wgh-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .note {
            margin-top: 12px;
        }

        .note .label {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .signs {
            margin-top: 50px;
            display: flex;
            justify-content: flex-end;
            gap: 40px;
        }

        .sign-card {
            width: 200px;
            text-align: center;
        }

        .sign-card .muted {
            margin-bottom: 70px;
            color: var(--muted);
            font-weight: 600;
        }

        .sign-line {
            border-top: 1px solid var(--ink);
            padding-top: 6px;
            font-weight: bold;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .doc {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="doc">
        <div class="header">
            <div class="brand">
                <img src="{{ asset('img/LOGO-Y.png') }}" alt="Logo">
                <div>
                    <div class="name">PT. SANTI WIJAYA MEAT</div>
                    <div class="tag">Committed to Meeting Your Need</div>
                </div>
            </div>
        </div>

        <div class="title">
            <h1>Good Receipt</h1>
            <div class="muted">GR No: {{ $receiving->receiving_number }}</div>
        </div>

        <dl class="meta">
            <dt>Tgl. Terima</dt>
            <dd>{{ \Carbon\Carbon::parse($receiving->receive_date)->format('d-M-Y') }}</dd>

            <dt>Penerima</dt>
            <dd>{{ $receiving->creator->name ?? 'Admin Gudang' }}</dd>

            <dt>Supplier</dt>
            <dd>{{ $receiving->supplier->name ?? '-' }}</dd>

            <dt>No. Surat Jalan (SJ)</dt>
            <dd>{{ $receiving->sj_number ?? '-' }}</dd>

            <dt>No. PO Referensi</dt>
            <dd>{{ $receiving->purchaseOrder->po_number ?? '-' }}</dd>
        </dl>

        <table class="wgh-table">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Nama Barang / Item</th>
                    <th style="width:150px;">Qty Diterima</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receiving->items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item->item->name ?? '-' }}</td>
                    <td class="center" style="font-weight: bold;">{{ number_format($item->qty_received, 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="center" style="color:#888;">Tidak ada item yang diterima.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if(!empty($receiving->note))
        <div class="note">
            <div class="label">Catatan Penerimaan:</div>
            <div style="border: 1px solid var(--line); padding: 8px; background: #fafafa;">
                {!! nl2br(e($receiving->note)) !!}
            </div>
        </div>
        @endif

        <div class="signs">
            <div class="sign-card">
                <div class="muted">Supir / Pengirim,</div>
                <div class="sign-line"></div>
            </div>

            <div class="sign-card">
                <div class="muted">Diterima Oleh,</div>
                <div class="sign-line">{{ $receiving->creator->name ?? 'Admin Gudang' }}</div>
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