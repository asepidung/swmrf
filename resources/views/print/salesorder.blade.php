<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sales Order - {{ $record->so_number }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12pt; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24pt; color: #1a1a1a; }
        .header p { margin: 5px 0 0 0; color: #555; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        .items-table th { background-color: #f8f9fa; font-weight: bold; border-bottom: 2px solid #333; }
        .items-table td.text-right { text-align: right; }
        .footer { margin-top: 40px; font-size: 10pt; color: #666; text-align: center; }
        .signature-area { margin-top: 50px; width: 100%; display: table; }
        .signature-box { display: table-cell; width: 33.33%; text-align: center; }
        .signature-line { margin-top: 80px; border-bottom: 1px solid #333; width: 180px; display: inline-block; }
        .total-row { font-weight: bold; background-color: #f8f9fa; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1>SALES ORDER</h1>
        <p>PT Wijaya Meat</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="150"><strong>SO Number</strong></td>
            <td width="20">:</td>
            <td>{{ $record->so_number }}</td>
            <td width="150"><strong>Delivery Date</strong></td>
            <td width="20">:</td>
            <td>{{ \Carbon\Carbon::parse($record->delivery_date)->format('d F Y') }}</td>
        </tr>
        <tr>
            <td><strong>Customer</strong></td>
            <td>:</td>
            <td>{{ $record->customer->name ?? '-' }}</td>
            <td><strong>PO Number</strong></td>
            <td>:</td>
            <td>{{ $record->po_number ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Shipping Address</strong></td>
            <td>:</td>
            <td>{{ $record->shipping_address ?? '-' }}</td>
            <td><strong>Status</strong></td>
            <td>:</td>
            <td>{{ ucfirst($record->status) }}</td>
        </tr>
        @if($record->note)
        <tr>
            <td><strong>Note</strong></td>
            <td>:</td>
            <td colspan="4">{{ $record->note }}</td>
        </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="40" style="text-align: center">No.</th>
                <th>Product Name</th>
                <th width="100" style="text-align: right">Weight / Qty</th>
                <th width="120" style="text-align: right">Price (Rp)</th>
                <th width="80" style="text-align: right">Disc (%)</th>
                <th width="150">Note</th>
            </tr>
        </thead>
        <tbody>
            @php $totalWeight = 0; @endphp
            @foreach($record->items as $index => $item)
            @php $totalWeight += $item->weight; @endphp
            <tr>
                <td style="text-align: center">{{ $index + 1 }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ number_format($item->weight, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right">{{ $item->discount }}%</td>
                <td>{{ $item->note ?? '-' }}</td>
            </tr>
            @endforeach
            @if($record->items->isEmpty())
            <tr>
                <td colspan="6" style="text-align: center">No items found.</td>
            </tr>
            @else
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL ESTIMASI BERAT / QTY</td>
                <td class="text-right">{{ number_format($totalWeight, 0, ',', '.') }}</td>
                <td colspan="3"></td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="signature-area">
        <div class="signature-box">
            <p>Dibuat Oleh,</p>
            <div class="signature-line"></div>
            <p>{{ $record->creator->name ?? 'Admin' }}</p>
        </div>
        <div class="signature-box">
            <p>Mengetahui,</p>
            <div class="signature-line"></div>
            <p>Sales Manager</p>
        </div>
        <div class="signature-box">
            <p>Penerima (Customer),</p>
            <div class="signature-line"></div>
            <p>{{ $record->customer->name ?? 'Customer' }}</p>
        </div>
    </div>

    <div class="footer">
        Dicetak pada {{ date('d-m-Y H:i:s') }}
    </div>
</body>
</html>
