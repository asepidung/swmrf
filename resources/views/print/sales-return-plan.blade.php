<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Retur Jual : {{ $record->plan_number }}</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
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
        .items-table {
            border: 1px solid black;
            margin-top: 15px;
        }
        .items-table th, .items-table td {
            border: 1px solid black;
            padding: 6px;
            text-align: center;
        }
        .items-table td.left-align {
            text-align: left;
        }
        hr {
            border: 0;
            border-top: 1px solid #000;
        }
    </style>
</head>
<body>
    <table style="width: 100%; margin-bottom: 10px;">
        <tr>
            <td style="vertical-align: top;">
                <span style="font-size: 16px; font-weight: bold; text-transform: uppercase;">Plan Retur Jual</span><br />
                <strong style="font-size: 18px;">PT. SANTI WIJAYA MEAT</strong><br />
                <span style="font-size: 12px; color: #555;">Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103</span>
            </td>
            <td style="text-align: right; vertical-align: top; width: 40%;">
                <span style="font-size: 12px; color: #555;">Plan Number :</span><br />
                <strong style="font-size: 20px;">{{ $record->plan_number }}</strong>
            </td>
        </tr>
    </table>
    <hr />
    <table class="info-table">
        <tr>
            <td width="15%">DO Number</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->deliveryOrder?->delivery_order_number ?? 'Unidentified DO' }}</td>
            <td width="15%">Plan Date</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->plan_date ? \Carbon\Carbon::parse($record->plan_date)->format('d-M-Y') : '' }}</td>
        </tr>
        <tr>
            <td>Customer</td>
            <td>:</td>
            <td>{{ $record->customer?->name }}</td>
            <td>Status</td>
            <td>:</td>
            <td>{{ $record->status }}</td>
        </tr>
        <tr>
            <td valign="top">Address</td>
            <td valign="top">:</td>
            <td valign="top" colspan="4" align="justify">{{ $record->customer?->address }}</td>
        </tr>
    </table>
    <br>
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="45%">Product</th>
                <th width="20%">Claimed Weight (Kg)</th>
                <th width="15%">Claimed Qty (Pcs)</th>
                <th width="15%">Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="left-align">{{ $item->product?->name }}</td>
                    <td>{{ number_format((float) $item->claimed_weight, 2) }}</td>
                    <td>{{ $item->claimed_qty_pcs ?? '-' }}</td>
                    <td class="left-align">{{ $item->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($record->note)
        <br>
        <strong>Note:</strong> {{ $record->note }}
    @endif
</body>
</html>
