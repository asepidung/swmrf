<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Beef Stocks Export</title>
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
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Data Stok Beef' }}</h2>
    {{-- Angka ini posisi kapan. Berkas yang dibuka besok tidak punya
         konteks layarnya. --}}
    @isset($keterangan)
        <p style="margin: 0 0 8px; font-size: 11px;">{{ $keterangan }}</p>
    @endisset
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Kode</th>
                <th>Nama Produk</th>
                {{-- Judul kolom mengikuti gudang x grade yang ada isinya.
                     Dulu keempatnya ditulis mati di sini, sama seperti di
                     resource-nya, jadi stok ber-grade lain tidak pernah punya
                     kolom sementara Total tetap menghitungnya. --}}
                @foreach($buckets ?? [] as $bucket)
                    <th class="text-right">{{ $bucket['warehouse'] }} {{ $bucket['grade'] }}</th>
                @endforeach
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->code ?? '-' }}</td>
                    <td>{{ $record->name ?? '-' }}</td>
                    @foreach($buckets ?? [] as $bucket)
                        <td class="text-right">
                            {{ ($record->{$bucket['key']} ?? 0) > 0 ? number_format($record->{$bucket['key']}, 2, ',', '.') : '' }}
                        </td>
                    @endforeach
                    <td class="text-right" style="font-weight: bold;">
                        {{ $record->total_qty > 0 ? number_format($record->total_qty, 2, ',', '.') : '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
