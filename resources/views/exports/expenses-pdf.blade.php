<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Expenses Export</title>
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
        tfoot td {
            font-weight: bold;
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Pengeluaran Kas Kecil' }}</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="4%">No</th>
                <th width="10%">Nomor</th>
                <th width="9%">Tanggal</th>
                <th width="9%">Jenis</th>
                <th width="12%">Kategori</th>
                <th width="12%">Penerima</th>
                <th class="text-right" width="12%">Uang Muka</th>
                <th class="text-right" width="12%">Nota</th>
                <th width="9%">Status</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalAdvance = 0;
                $totalReceipt = 0;
            @endphp
            @forelse($records as $index => $record)
                @php
                    $totalAdvance += (float) $record->advance_amount;
                    $totalReceipt += (float) $record->receipt_amount;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->expense_number }}</td>
                    <td>{{ optional($record->expense_date)->format('d M Y') ?? '-' }}</td>
                    <td>{{ $record->type === \App\Models\Expense::TYPE_ADVANCE ? 'Advance' : 'Reimburse' }}</td>
                    <td>{{ $record->category->name ?? '-' }}</td>
                    <td>{{ $record->recipient_name }}</td>
                    <td class="text-right">{{ $record->advance_amount !== null ? number_format((float) $record->advance_amount, 0, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ $record->receipt_amount !== null ? number_format((float) $record->receipt_amount, 0, ',', '.') : '-' }}</td>
                    <td>{{ $record->status }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center" colspan="9">Tidak ada data pada rentang ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="6">Total</td>
                <td class="text-right">{{ number_format($totalAdvance, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalReceipt, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
