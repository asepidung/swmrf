<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DO No : {{ $record->delivery_order_number }}</title>
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
                <span style="font-size: 16px; font-weight: bold; text-transform: uppercase;">Delivery Order</span><br />
                <strong style="font-size: 18px;">PT. SANTI WIJAYA MEAT</strong><br />
                <span style="font-size: 12px; color: #555;">Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103</span>
            </td>
            <td style="text-align: right; vertical-align: top; width: 40%;">
                <span style="font-size: 12px; color: #555;">DO Number :</span><br />
                <strong style="font-size: 20px;">{{ $record->delivery_order_number }}</strong>
            </td>
        </tr>
    </table>
    <hr />
    <table class="info-table">
        <tr>
            <td width="15%">SO Number</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->salesOrder?->so_number }}</td>
            <td width="15%">Delivery Date</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->format('d-M-Y') : '' }}</td>
        </tr>
        <tr>
            <td>Seal Number</td>
            <td>:</td>
            <td>{{ $record->seal_number }}</td>
            <td>PO Number</td>
            <td>:</td>
            <td>{{ $record->po_number }}</td>
        </tr>
        <tr>
            <td>Driver</td>
            <td>:</td>
            <td>{{ $record->driver }}</td>
            <td>Customer</td>
            <td>:</td>
            <td>{{ $record->customer?->name }}</td>
        </tr>
        <tr>
            <td>Police Number</td>
            <td>:</td>
            <td>{{ $record->police_number }}</td>
            <td valign="top">Address</td>
            <td valign="top">:</td>
            <td valign="top" align="justify">{{ $record->customer?->address }}</td>
        </tr>
    </table>
    <br>
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Code</th>
                <th width="35%">Item Descriptions</th>
                <th width="10%">Box</th>
                <th width="15%">Weight</th>
                <th width="20%">Notes</th>
            </tr>
        </thead>
        <tbody>
            @php
                $no = 1;
                $totalBox = 0;
                $totalWeight = 0;
            @endphp
            @foreach ($record->items as $item)
                @php
                    $totalBox += $item->box;
                    $totalWeight += $item->weight;
                @endphp
                <tr>
                    <td>{{ $no++ }}</td>
                    <td>{{ $item->product?->code }}</td>
                    <td class="left-align">{{ $item->product?->name }}</td>
                    <td>{{ $item->box }}</td>
                    <td class="right-align">{{ number_format($item->weight, 2) }} Kg</td>
                    <td class="left-align">{{ $item->notes }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="3" style="text-align: right;">Total</td>
                <td>{{ $totalBox }}</td>
                <td style="text-align: right;">{{ number_format($totalWeight, 2) }} Kg</td>
                <td></td>
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
            <td width="25%">Warehouse <br><br><br><br><br> ( ................................ )</td>
            <td width="25%">QC/QA <br><br><br><br><br> ( ................................ )</td>
            <td width="25%">Security <br><br><br><br><br> ( ................................ )</td>
            <td width="25%">Customer <br><br><br><br><br> ( ................................ )</td>
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
