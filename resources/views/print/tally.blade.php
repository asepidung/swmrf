<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tally No : {{ $record->tally_number }}</title>
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
    </style>
</head>
<body>
    <p align="center">
        <span style="font-size: 16px; font-weight: bold; text-transform: uppercase;">TALY SHEET</span><br />
        <strong>Taly Number : {{ $record->tally_number }}</strong>
    </p>

    <table class="info-table">
        <tr>
            <td width="12%">Customer</td>
            <td width="2%">:</td>
            <td width="25%">{{ $record->salesOrder?->customer?->name }}</td>
            <td width="12%">Delivery Date</td>
            <td width="2%">:</td>
            <td width="30%">{{ $record->salesOrder?->delivery_date ? \Carbon\Carbon::parse($record->salesOrder->delivery_date)->format('d-M-Y') : '' }}</td>
        </tr>
        <tr>
            <td>PO Numb</td>
            <td>:</td>
            <td>{{ $record->salesOrder?->po_number }}</td>
            <td>SO Numb</td>
            <td>:</td>
            <td>{{ $record->salesOrder?->so_number }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Product</th>
                @for ($i = 1; $i <= 10; $i++)
                    <th>{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</th>
                @endfor
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($productData as $productName => $data)
                @php
                    $weights = $data['weights'];
                    $rowsNeeded = ceil(count($weights) / 10);
                @endphp
                @for ($rowIndex = 0; $rowIndex < $rowsNeeded; $rowIndex++)
                    <tr>
                        <td class="left-align">
                            @if ($rowIndex === 0)
                                {{ $productName }}
                            @endif
                        </td>
                        @for ($i = 0; $i < 10; $i++)
                            @php
                                $weightIndex = ($rowIndex * 10) + $i;
                            @endphp
                            <td>
                                @if (isset($weights[$weightIndex]))
                                    {{ number_format($weights[$weightIndex], 2) }}
                                @endif
                            </td>
                        @endfor
                        <td class="right-align">
                            @if ($rowIndex == $rowsNeeded - 1)
                                <strong>{{ number_format($data['total'], 2) }}</strong>
                            @endif
                        </td>
                    </tr>
                @endfor
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="10" style="text-align: right;">TOTAL</td>
                <td>{{ $totalBox }}</td>
                <td style="text-align: right;">{{ number_format($totalQty, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="signatures-table">
        <tr>
            <td width="30%">WAREHOUSE <br><br><br><br><br> ( ..................................... )</td>
            <td width="30%">QC/QA <br><br><br><br><br> ( ..................................... )</td>
            <td width="30%">CUSTOMER <br><br><br><br><br> ( ..................................... )</td>
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
