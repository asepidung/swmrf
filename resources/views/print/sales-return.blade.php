<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Retur Jual : {{ $record->return_number }}</title>
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
        .items-table td.right-align {
            text-align: right;
        }
        .signatures-table {
            margin-top: 50px;
            text-align: center;
        }
        .signatures-table td {
            padding-top: 50px;
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
                <span style="font-size: 16px; font-weight: bold; text-transform: uppercase;">Berita Acara Retur Jual</span><br />
                <strong style="font-size: 18px;">PT. SANTI WIJAYA MEAT</strong><br />
                <span style="font-size: 12px; color: #555;">Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103</span>
            </td>
            <td style="text-align: right; vertical-align: top; width: 40%;">
                <span style="font-size: 12px; color: #555;">Return Number :</span><br />
                <strong style="font-size: 20px;">{{ $record->return_number }}</strong>
            </td>
        </tr>
    </table>
    <hr />
    <table class="info-table">
        <tr>
            <td width="15%">DO Number</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->deliveryOrder?->delivery_order_number ?? 'Unidentified DO' }}</td>
            <td width="15%">Return Date</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->return_date ? \Carbon\Carbon::parse($record->return_date)->format('d-M-Y') : '' }}</td>
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
                <th width="15%">Code</th>
                <th width="45%">Item Descriptions</th>
                <th width="15%">Total Pcs</th>
                <th width="20%">Total Weight</th>
            </tr>
        </thead>
        <tbody>
            @php
                $no = 1;
                $totalBox = 0;
                $totalWeight = 0;
                $groupedItems = $record->items->groupBy('product_id');
            @endphp
            @foreach ($groupedItems as $productId => $items)
                @php
                    $firstItem = $items->first();
                    $sumPcs = $items->sum('qty_pcs');
                    $sumWeight = $items->sum('weight');
                    
                    $totalBox += $sumPcs;
                    $totalWeight += $sumWeight;
                @endphp
                <tr>
                    <td>{{ $no++ }}</td>
                    <td>{{ $firstItem->product?->code }}</td>
                    <td class="left-align">{{ $firstItem->product?->name }}</td>
                    <td>{{ $sumPcs }}</td>
                    <td class="right-align">{{ number_format($sumWeight, 2) }} Kg</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="3" style="text-align: right;">Total</td>
                <td>{{ $totalBox }}</td>
                <td style="text-align: right;">{{ number_format($totalWeight, 2) }} Kg</td>
            </tr>
        </tbody>
    </table>
    <i>
        <p align="justify">
            <strong>Note !</strong><br>
            {{ $record->note ?? '-' }}
        </p>
    </i>
    <table class="signatures-table">
        <tr>
            <td width="33%">Dibuat Oleh <br><br><br><br><br> ( ................................ )</td>
            <td width="33%">Diketahui Oleh <br><br><br><br><br> ( ................................ )</td>
            <td width="33%">Customer <br><br><br><br><br> ( ................................ )</td>
        </tr>
    </table>

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
