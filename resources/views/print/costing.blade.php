<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Costing : {{ $record->costing_number }}</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #000;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 16px 0 6px;
        }
        .items-table {
            border: 1px solid black;
        }
        .items-table th, .items-table td {
            border: 1px solid black;
            padding: 6px;
            text-align: center;
        }
        .items-table td.left-align {
            text-align: left;
        }
        .items-table td.right-align {
            text-align: right;
        }
        .items-table tfoot td {
            font-weight: bold;
            background: #fafafa;
        }
        .flag-note {
            display: block;
            font-size: 10px;
            color: #a15c00;
            margin-top: 2px;
        }
        .flag-note.danger {
            color: #b91c1c;
        }
        .summary-box {
            margin-top: 16px;
            width: 320px;
            margin-left: auto;
            border: 1px solid black;
        }
        .summary-box td {
            padding: 6px 10px;
        }
        .summary-box td.label {
            color: #555;
        }
        .summary-box td.value {
            text-align: right;
            font-weight: bold;
        }
        .summary-box tr.total td {
            border-top: 1px solid black;
            font-size: 14px;
        }
        hr {
            border: 0;
            border-top: 1px solid #000;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #000;
            border-radius: 3px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <table style="width: 100%; margin-bottom: 10px;">
        <tr>
            <td style="vertical-align: top;">
                <span style="font-size: 16px; font-weight: bold; text-transform: uppercase;">Costing / HPP</span><br />
                <strong style="font-size: 18px;">PT. SANTI WIJAYA MEAT</strong><br />
                <span style="font-size: 12px; color: #555;">Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103</span>
            </td>
            <td style="text-align: right; vertical-align: top; width: 40%;">
                <span style="font-size: 12px; color: #555;">No. Costing :</span><br />
                <strong style="font-size: 20px;">{{ $record->costing_number }}</strong>
            </td>
        </tr>
    </table>
    <hr />
    <table class="info-table">
        <tr>
            <td width="15%">Tanggal</td>
            <td width="2%">:</td>
            <td width="33%">{{ optional($record->costing_date)->format('d-M-Y') }}</td>
            <td width="15%">Boning</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->boning?->doc_no }} ({{ optional($record->boning?->boning_date)->format('d-M-Y') }})</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>:</td>
            <td><span class="status-badge">{{ $record->status }}</span></td>
            <td>Overhead / Kg</td>
            <td>:</td>
            <td>Rp {{ number_format((float) $record->overhead_per_kg, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Biaya Beli Sapi</div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="25%">Kelas</th>
                <th width="20%">Jumlah Ekor</th>
                <th width="20%">Berat Terima (Kg)</th>
                <th width="15%">Harga / Kg</th>
                <th width="20%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($record->cattle as $baris)
                <tr>
                    <td>{{ $baris->cattleClass?->name }}</td>
                    <td>{{ number_format((float) $baris->head_count, 0, ',', '.') }}</td>
                    <td class="right-align">{{ number_format((float) $baris->received_weight, 2, ',', '.') }}</td>
                    <td class="right-align">Rp {{ number_format((float) $baris->price_per_kg, 0, ',', '.') }}</td>
                    <td class="right-align">Rp {{ number_format((float) $baris->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada data.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="right-align">Total Biaya Beli</td>
                <td class="right-align">Rp {{ number_format((float) $record->purchase_cost, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">Alokasi Nilai Jual per Produk</div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="20%">Produk</th>
                <th width="10%">Berat (Kg)</th>
                <th width="16%">Acuan Harga</th>
                <th width="10%">Gross</th>
                <th width="8%">Terms</th>
                <th width="10%">Net</th>
                <th width="12%">Nilai Jual</th>
                <th width="14%">HPP / Kg</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($record->items as $item)
                <tr>
                    <td class="left-align">
                        {{ $item->product?->name }}
                        @if ($item->flag === \App\Models\CostingItem::FLAG_NO_REFERENCE)
                            <span class="flag-note">{{ __('No reference group -- valued using general price') }}</span>
                        @elseif ($item->flag === \App\Models\CostingItem::FLAG_NO_PRICE)
                            <span class="flag-note danger">{{ __('No price found') }}</span>
                        @endif
                    </td>
                    <td class="right-align">{{ number_format((float) $item->weight_kg, 2, ',', '.') }}</td>
                    <td>{{ $item->referenceGroup?->name ?? __('General price') }}</td>
                    <td class="right-align">Rp {{ number_format((float) $item->gross_price, 0, ',', '.') }}</td>
                    <td class="right-align">{{ number_format((float) $item->trading_terms_percent, 2, ',', '.') }}%</td>
                    <td class="right-align">Rp {{ number_format((float) $item->net_price, 0, ',', '.') }}</td>
                    <td class="right-align">Rp {{ number_format((float) $item->sales_value, 0, ',', '.') }}</td>
                    <td class="right-align">Rp {{ number_format((float) $item->hpp_per_kg, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Belum ada data.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="right-align">Total</td>
                <td class="right-align">{{ number_format((float) $record->total_kg, 2, ',', '.') }}</td>
                <td colspan="4"></td>
                <td class="right-align">Rp {{ number_format((float) $record->total_sales_value, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table class="summary-box">
        <tr>
            <td class="label">Biaya Beli</td>
            <td class="value">Rp {{ number_format((float) $record->purchase_cost, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Total Nilai Jual</td>
            <td class="value">Rp {{ number_format((float) $record->total_sales_value, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Rasio (k)</td>
            <td class="value">{{ number_format((float) $record->ratio_k, 6) }}</td>
        </tr>
        <tr>
            <td class="label">Margin %</td>
            <td class="value">{{ number_format($record->marginPercent(), 2) }}%</td>
        </tr>
        <tr>
            <td class="label">Overhead / Kg</td>
            <td class="value">Rp {{ number_format((float) $record->overhead_per_kg, 0, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td class="label">Laba</td>
            <td class="value">Rp {{ number_format((float) $record->profit, 0, ',', '.') }}</td>
        </tr>
    </table>
</body>
</html>
