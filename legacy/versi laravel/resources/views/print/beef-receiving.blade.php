<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>GR Beef - {{ $record->receiving_number }} - {{ $record->supplier->name ?? 'Unknown' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Document Styles */
        :root {
            --accent: #d9534f;
            /* Merah untuk Beef biar beda dikit aura-nya sama Cattle */
            --ink: #111;
            --muted: #666;
            --line: #e7e7e7;
        }

        body {
            color: var(--ink);
            font-size: 13px;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
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
            font-size: 11px;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta dd {
            margin: 0;
            font-size: 13px;
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
            font-size: 11px;
            text-transform: uppercase;
        }

        .wgh-table td {
            border: 1px solid var(--line);
            padding: 10px 8px;
        }

        .wgh-table td.num {
            text-align: right;
            white-space: nowrap;
            font-weight: bold;
        }

        .wgh-table td.center {
            text-align: center;
        }

        .wgh-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .wgh-table tfoot tr {
            background: #fafafa;
            font-weight: bold;
        }

        .note {
            margin-top: 12px;
        }

        .note .label {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--muted);
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
            font-size: 11px;
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

            .no-print {
                display: none;
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
            <h1>Good Receipt Beef (GRB)</h1>
            <div style="font-weight: bold; color: var(--accent);">GR No: {{ $record->receiving_number }}</div>
        </div>

        <dl class="meta">
            <dt>Tgl. Terima</dt>
            <dd>{{ \Carbon\Carbon::parse($record->receive_date)->format('d-M-Y') }}</dd>

            <dt>Penerima</dt>
            <dd>{{ $record->creator->name ?? 'Admin Gudang' }}</dd>

            <dt>Supplier</dt>
            <dd>{{ $record->supplier->name ?? '-' }}</dd>

            <dt>No. Surat Jalan (SJ)</dt>
            <dd>{{ $record->sj_number ?? '-' }}</dd>

            <dt>No. PO Referensi</dt>
            <dd>{{ $record->purchaseOrder->po_number ?? '-' }}</dd>
        </dl>

        <table class="wgh-table">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Nama Barang / Item Beef</th>
                    <th style="width:150px;">Qty Diterima (Kg)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($record->items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <div style="font-weight: bold;">{{ $item->item->name ?? '-' }}</div>
                        <div style="font-size: 11px; color: var(--muted);">{{ $item->notes ?? '' }}</div>
                    </td>
                    <td class="num">{{ number_format($item->qty_received, 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="center" style="color:#888; padding: 20px;">Tidak ada item daging yang diterima.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align: right; text-transform: uppercase; font-size: 11px;">Total Berat Diterima</td>
                    <td class="num">{{ number_format($record->items->sum('qty_received'), 2, ',', '.') }} Kg</td>
                </tr>
            </tfoot>
        </table>

        @if(!empty($record->note))
        <div class="note">
            <div class="label">Catatan Penerimaan:</div>
            <div style="border: 1px solid var(--line); padding: 8px; background: #fafafa; min-height: 40px;">
                {!! nl2br(e($record->note)) !!}
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
                <div class="sign-line">{{ $record->creator->name ?? 'Admin Gudang' }}</div>
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