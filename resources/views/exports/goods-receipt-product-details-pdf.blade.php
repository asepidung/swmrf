<!DOCTYPE html>
<html>
<head>
    <title>Beef Receipt Items Detail List</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        h2 { text-align: center; margin-bottom: 5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h2>Beef Receipt Items Detail List</h2>
    <p>Printed at: {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>GR Number</th>
                <th>Receive Date</th>
                <th>Surat Jalan</th>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Barcode</th>
                <th>Product</th>
                <th>Grade</th>
                <th class="text-right">Weight</th>
                <th class="text-right">Pcs</th>
                <th class="text-right">Price</th>
                <th class="text-right">Subtotal</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ optional($record->goodsReceiptProduct)->gr_number ?? '-' }}</strong></td>
                <td>{{ optional($record->goodsReceiptProduct)->receive_date ? \Carbon\Carbon::parse($record->goodsReceiptProduct)->receive_date->format('d M Y') : '-' }}</td>
                <td>{{ optional($record->goodsReceiptProduct)->sj_number ?? '-' }}</td>
                <td>{{ optional(optional($record->goodsReceiptProduct)->purchaseProduct)->po_number ?? '-' }}</td>
                <td>{{ optional(optional($record->goodsReceiptProduct)->supplier)->name ?? '-' }}</td>
                <td>{{ $record->barcode }}</td>
                <td>{{ optional($record->product)->name ?? '-' }}</td>
                <td>{{ optional($record->grade)->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($record->weight, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($record->qty_pcs, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($record->price, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($record->subtotal, 0, ',', '.') }}</td>
                <td>{{ optional(optional($record->goodsReceiptProduct)->createdBy)->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
