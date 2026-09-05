<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>GRB - {{ $record->gr_number }} - {{ $record->supplier->name ?? 'Unknown' }}</title>
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
            font-size: 11px;
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
            font-size: 12px;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .items-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .items-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .items-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .items-table td.center {
            text-align: center;
        }

        .items-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .items-table tfoot tr {
            background: #fafafa;
            font-weight: bold;
        }

        .items-table tfoot td {
            border: 1px solid var(--line);
            padding: 8px;
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
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .sign-card {
            width: 180px;
            text-align: center;
        }

        .sign-card .muted {
            margin-bottom: 60px;
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
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    @if($record->trashed())
        <div class="watermark">DELETED</div>
    @endif
    <div class="doc">
        <div class="header">
            <div class="brand">
                <img src="{{ asset('img/light.png') }}" alt="Logo">
                <div>
                    <div class="name">PT. SANTI WIJAYA MEAT</div>
                    <div class="tag">Committed to Meeting Your Need</div>
                </div>
            </div>
        </div>

        <div class="title">
            <h1>Receiving Note</h1>
            <div style="font-weight: bold; color: var(--ink); font-size: 14px;">No: {{ $record->gr_number }}</div>
        </div>

        <dl class="meta">
            <dt>Tgl. Terima</dt>
            <dd>{{ \Carbon\Carbon::parse($record->receive_date)->format('d-M-Y') }}</dd>

            <dt>Supplier</dt>
            <dd>{{ $record->supplier->name ?? '-' }}</dd>

            <dt>No. PO Referensi</dt>
            <dd>{{ $record->purchaseProduct->po_number ?? '-' }}</dd>

            <dt>No. Surat Jalan</dt>
            <dd>{{ $record->sj_number ?? '-' }}</dd>

            <dt>Penerima</dt>
            <dd>{{ $record->createdBy->name ?? 'Admin Gudang' }}</dd>
        </dl>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Product Name</th>
                    <th style="width:100px;">Qty PO</th>
                    <th style="width:100px;">Qty Received</th>
                    <th style="width:120px;">Price</th>
                    <th style="width:150px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // Group received items by product_id
                    $groupedItems = $record->items()
                        ->select('product_id', DB::raw('SUM(weight) as total_weight'), DB::raw('SUM(qty_pcs) as total_pcs'), DB::raw('SUM(subtotal) as total_subtotal'), DB::raw('MAX(price) as item_price'))
                        ->groupBy('product_id')
                        ->with('product')
                        ->get();

                    $subtotalSum = 0;
                @endphp
                @forelse($groupedItems as $index => $item)
                @php
                    $poItem = \App\Models\PurchaseProductItem::where('purchase_product_id', $record->purchase_product_id)
                        ->where('product_id', $item->product_id)
                        ->first();
                    $poQty = $poItem ? $poItem->qty : 0;
                    $subtotalSum += $item->total_subtotal;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td style="font-weight: bold; font-size: 11px;">{{ $item->product->name ?? '-' }}</td>
                    <td class="center">{{ number_format($poQty, 2, ',', '.') }}</td>
                    <td class="center">{{ number_format($item->total_weight, 2, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($item->item_price, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($item->total_subtotal, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="center" style="color:#888; padding: 20px;">Tidak ada data produk yang diterima.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                @php
                    $isTax11 = (bool) $record->supplier?->isPkp();
                    $tax = $record->supplier?->ppnAtas($subtotalSum) ?? 0;
                    $grandTotal = $subtotalSum + $tax;
                @endphp
                <tr>
                    <td colspan="5" style="text-align: right; text-transform: uppercase;">Total Sebelum Pajak (Subtotal)</td>
                    <td class="num">Rp {{ number_format($subtotalSum, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: right; text-transform: uppercase;">
                        PPN ({{ $isTax11 ? '11%' : '0%' }})
                    </td>
                    <td class="num">Rp {{ number_format($tax, 0, ',', '.') }}</td>
                </tr>
                <tr style="font-size: 12px; font-weight: bold; background: #eaeaea;">
                    <td colspan="5" style="text-align: right; text-transform: uppercase;">Total Setelah Pajak (Net Total)</td>
                    <td class="num">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        @if(!empty($record->note))
        <div class="note">
            <div class="label">Catatan Tambahan:</div>
            <div style="border: 1px solid var(--line); padding: 8px; background: #fafafa; min-height: 40px; font-size: 10px;">
                {!! nl2br(e($record->note)) !!}
            </div>
        </div>
        @endif

        <div class="signs">
            <div class="sign-card">
                <div class="muted">Pengirim / Supir,</div>
                <div class="sign-line">&nbsp;</div>
            </div>
            <div class="sign-card">
                <div class="muted">Disetujui Oleh,</div>
                <div class="sign-line">&nbsp;</div>
            </div>
            <div class="sign-card">
                <div class="muted">Dibuat Oleh (Penerima),</div>
                <div class="sign-line">{{ $record->createdBy->name ?? 'Admin Gudang' }}</div>
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
