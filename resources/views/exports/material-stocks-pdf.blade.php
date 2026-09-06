<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Material Stocks Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .warning-text {
            color: #d9534f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Data Stok Material' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Kode Material</th>
                <th>Nama Material</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th class="text-right">Stok Aktual</th>
                <th class="text-right">Min. Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                {{-- Barisnya MATERIAL, bukan MaterialStock.

                     Seluruh kolom di sini dulu membaca `$record->material->...`,
                     padahal yang dikirim resource-nya model `Material` yang
                     tidak punya relasi bernama `material`. Karena setiap
                     pembacaannya berakhir `?? '-'`, tidak ada satu pun error:
                     PDF-nya terbit rapi, isinya strip semua, dan min stock-nya
                     selalu 0,00.

                     Penanda merah "di bawah minimum" pun tidak pernah menyala,
                     karena yang dibandingkan `qty < 0`. --}}
                @php
                    $isBelowMin = ! ($masked ?? false) && $record->qty < ($record->min_stock ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->code ?? '-' }}</td>
                    <td>{{ $record->name ?? '-' }}</td>
                    <td>{{ $record->category->name ?? '-' }}</td>
                    <td>{{ $record->unit->name ?? '-' }}</td>
                    <td class="text-right @if($isBelowMin) warning-text @endif">
                        {{ ($masked ?? false) ? '***' : number_format((int) $record->qty, 0, ',', '.')}}
                    </td>
                    <td class="text-right">
                        {{ number_format($record->min_stock ?? 0, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
