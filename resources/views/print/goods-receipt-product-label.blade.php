<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Print Goods Receipt Beef</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <style>
        /* Standarisasi font untuk printer thermal */
        * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
            color: #000000;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #fff;
            display: flex;
            justify-content: center;
        }

        /* Konfigurasi ukuran kertas printer thermal */
        @media print {
            @page {
                size: 100mm 75mm;
                margin: 0;
            }

            body {
                width: 100mm;
                height: 75mm;
            }
        }

        /* Pengaturan margin konten */
        .label-wrapper {
            width: 94mm;
            height: 69mm;
            margin: 3mm auto;
            display: flex;
            flex-direction: column;
        }

        table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 1px 0;
        }
    </style>
</head>

<body>
    @php
        $date = \Carbon\Carbon::parse($item->pack_date);
        if (in_array((int)$item->grade_id, [1, 3])) { // CHILL or A
            $expDate = $date->copy()->addMonths(3);
        } else {
            $expDate = $date->copy()->addYear();
        }
    @endphp
    <div class="label-wrapper">
        <table cellpadding="0" border="0">
            <tbody>
                <tr>
                    <td height="23" colspan="4">
                        <span style="font-size: 18px; font-weight: bold;">*YP*</span>
                    </td>
                </tr>
                <tr>
                    <td height="21" colspan="4">
                        <span style="font-size: 14px; font-weight: bold;">Prod By: PT. SANTI WIJAYA MEAT</span>
                    </td>
                </tr>
                <tr>
                    <td height="20" colspan="4">
                        <span style="font-size: 10px;">
                            Perum Asabri Blok B No 20 Rt. 01/05 Ds. Sukasirna Kec. Jonggol Kab. Bogor
                        </span>
                    </td>
                </tr>
                <tr>
                    <td height="20" colspan="2">
                        <span style="font-size: 18px; font-weight: bold;">
                            {{ strtoupper($item->product->name) }}
                        </span>
                    </td>
                    <td colspan="2" rowspan="5" align="center" valign="middle">
                        <img src="{{ asset('img/halal.png') }}" alt="HALAL" height="100" align="absmiddle">
                    </td>
                </tr>
                <tr>
                    <td colspan="1" rowspan="2">
                        <span style="font-size: 30px; font-weight: bold;">
                            {{ number_format($item->weight, 2) }}
                            <sup style="font-size: 14px; font-weight: normal;">Kg</sup>
                        </span>
                    </td>
                    <td height="20" style="font-size: 12px;">
                        @if($item->qty_pcs > 1)
                        <strong><i>{{ $item->qty_pcs }}-Pcs</i></strong>
                        @else
                        &nbsp;
                        @endif
                    </td>
                </tr>
                <tr>
                    <td height="20" style="font-size: 12px;">
                        pH {{ number_format($item->ph_level ?? 0, 1) }}
                    </td>
                </tr>
                <tr>
                    <td height="20" style="font-size: 11px;">Packed Date&nbsp; :</td>
                    <td style="font-size: 11px;">
                        {{ \Carbon\Carbon::parse($item->pack_date)->format('d-M-Y') }}
                    </td>
                </tr>
                <tr>
                    @if(request('show_exp', 1) == 1)
                    <td style="font-size: 11px;">Expired Date :</td>
                    <td style="font-size: 11px;">{{ $expDate->format('d-M-Y') }}</td>
                    @else
                    <td style="font-size: 11px;">&nbsp;</td>
                    <td style="font-size: 11px;">&nbsp;</td>
                    @endif
                </tr>
                <tr>
                    <td height="20" colspan="2">
                        <span style="font-size: 12px; font-weight: bold;">
                            @if(in_array($item->grade_id, [1, 3]))
                            KEEP CHILL 0°C
                            @else
                            KEEP FROZEN -18°C
                            @endif
                        </span>
                    </td>
                    <td style="font-size: 10px; text-align: center;">
                        ID00110015321510124<br>RPHR 3201170-027
                    </td>
                </tr>
                <tr>
                    <td height="20" colspan="4" align="center" valign="middle" style="padding-top: 5px;">
                        <svg id="barcode"></svg>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" align="center">
                        <span style="font-size: 12px;">
                            {{ $item->barcode }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <script>
        JsBarcode("#barcode", "{{ $item->barcode }}", {
            format: "CODE128",
            width: 1.5,
            height: 40,
            displayValue: false,
            margin: 0
        });

        window.onload = function() {
            window.print();
        };
        window.onafterprint = function() {
            window.close();
        };
    </script>
</body>

</html>
