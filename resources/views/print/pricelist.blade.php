<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Price List - {{ $record->customerGroup->name }}</title>
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
        .signature-box { display: table-cell; width: 50%; text-align: center; }
        .signature-line { margin-top: 80px; border-bottom: 1px solid #333; width: 200px; display: inline-block; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1>DAFTAR HARGA (PRICE LIST)</h1>
        <p>PT Wijaya Meat</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="150"><strong>Grup Pelanggan</strong></td>
            <td width="20">:</td>
            <td>{{ $record->customerGroup->name }}</td>
            <td width="150"><strong>Tanggal Cetak</strong></td>
            <td width="20">:</td>
            <td>{{ date('d F Y') }}</td>
        </tr>
        <tr>
            <td><strong>Terakhir Diperbarui</strong></td>
            <td>:</td>
            <td>{{ $record->updated_at->format('d F Y H:i') }}</td>
            <td><strong>Dibuat Oleh</strong></td>
            <td>:</td>
            <td>{{ $record->creator->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="50">No.</th>
                <th>Nama Produk</th>
                <th width="150" style="text-align: right">Harga (Rp)</th>
                <th width="200">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($record->items as $index => $item)
            <tr>
                <td style="text-align: center">{{ $index + 1 }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                <td>{{ $item->note ?? '-' }}</td>
            </tr>
            @endforeach
            @if($record->items->isEmpty())
            <tr>
                <td colspan="4" style="text-align: center">Tidak ada data harga.</td>
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
            <p>Disetujui Oleh,</p>
            <div class="signature-line"></div>
            <p>Manajemen</p>
        </div>
    </div>

    <div class="footer">
        Dicetak dari Sistem Wijaya Meat pada {{ date('d-m-Y H:i:s') }}
    </div>
</body>
</html>
