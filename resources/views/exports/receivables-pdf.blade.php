<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receivables Export</title>
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
    <h2>{{ $title ?? 'Daftar Piutang Customer' }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Customer</th>
                <th>No. Invoice</th>
                <th>Tanggal Invoice</th>
                <th class="text-center">T.O.P (Hari)</th>
                <th>Jatuh Tempo</th>
                <th class="text-right">Total Tagihan</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                @php
                    $invoice = $record->invoice;
                    $tukarfaktur = $record->customer->invoice_exchange ?? false;
                    $status = $invoice->status ?? '-';
                    $tgltf = $invoice->invoice_exchange_date ?? null;
                    
                    if ($tukarfaktur && empty($tgltf) && $status === 'Belum TF') {
                        $jatuhTempoFormatted = 'BTF';
                    } else {
                        $jatuhTempoFormatted = $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-';
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->customer->name ?? '-' }}</td>
                    <td>{{ $invoice->invoice_number ?? '-' }}</td>
                    <td>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-' }}</td>
                    <td class="text-center">{{ $invoice->term_of_payment ?? 0 }}</td>
                    <td>{{ $jatuhTempoFormatted }}</td>
                    <td class="text-right">Rp {{ number_format($invoice->balance ?? 0, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
