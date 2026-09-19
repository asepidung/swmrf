<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Kas Keluar : {{ $record->expense_number }}</title>
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
        .amount-box {
            border: 1px solid black;
            margin-top: 15px;
            padding: 10px;
            text-align: center;
        }
        .amount-box .label {
            font-size: 12px;
            color: #555;
        }
        .amount-box .value {
            font-size: 22px;
            font-weight: bold;
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
        .signature-table td {
            padding-top: 50px;
            text-align: center;
            vertical-align: bottom;
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
                <span style="font-size: 16px; font-weight: bold; text-transform: uppercase;">Bukti Kas Keluar</span><br />
                <strong style="font-size: 18px;">PT. SANTI WIJAYA MEAT</strong><br />
                <span style="font-size: 12px; color: #555;">Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103</span>
            </td>
            <td style="text-align: right; vertical-align: top; width: 40%;">
                <span style="font-size: 12px; color: #555;">No. Bukti :</span><br />
                <strong style="font-size: 20px;">{{ $record->expense_number }}</strong>
            </td>
        </tr>
    </table>
    <hr />
    <table class="info-table">
        <tr>
            <td width="15%">Tanggal</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->expense_date ? \Carbon\Carbon::parse($record->expense_date)->format('d-M-Y') : '' }}</td>
            <td width="15%">Jenis</td>
            <td width="2%">:</td>
            <td width="33%">{{ $record->type === \App\Models\Expense::TYPE_ADVANCE ? 'Kasbon (Advance)' : 'Reimburse' }}</td>
        </tr>
        <tr>
            <td>Kategori</td>
            <td>:</td>
            <td>{{ $record->category?->name }}</td>
            <td>Status</td>
            <td>:</td>
            <td>{{ $record->status }}</td>
        </tr>
        <tr>
            <td>Diberikan Kepada</td>
            <td>:</td>
            <td>{{ $record->recipient_name }}</td>
            <td>Akun Kas/Bank</td>
            <td>:</td>
            <td>{{ $record->bankAccount?->initial }}</td>
        </tr>
        @if ($record->description)
            <tr>
                <td valign="top">Keterangan</td>
                <td valign="top">:</td>
                <td valign="top" colspan="4" align="justify">{{ $record->description }}</td>
            </tr>
        @endif
    </table>

    <div class="amount-box">
        @if ($record->type === \App\Models\Expense::TYPE_ADVANCE)
            <div class="label">Nominal Uang Muka</div>
            <div class="value">Rp {{ number_format((float) $record->advance_amount, 0, ',', '.') }}</div>
            @if ($record->receipt_amount !== null)
                <div class="label" style="margin-top: 8px;">Nominal Nota (Realisasi)</div>
                <div class="value">Rp {{ number_format((float) $record->receipt_amount, 0, ',', '.') }}</div>
            @endif
        @else
            <div class="label">Nominal Nota</div>
            <div class="value">Rp {{ number_format((float) $record->receipt_amount, 0, ',', '.') }}</div>
        @endif
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="15%">Tanggal</th>
                <th width="15%">Jenis</th>
                <th width="20%">Nominal</th>
                <th width="50%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->transactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d-M-Y') : '' }}</td>
                    <td>{{ $transaction->type === 'in' ? 'Kas Masuk' : 'Kas Keluar' }}</td>
                    <td>Rp {{ number_format((float) $transaction->amount, 0, ',', '.') }}</td>
                    <td class="left-align">{{ $transaction->description }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signature-table" style="margin-top: 20px;">
        <tr>
            <td width="50%">
                <div>( {{ $record->recipient_name }} )</div>
                <div style="font-size: 12px; color: #555;">Diterima Oleh</div>
            </td>
            <td width="50%">
                <div>( {{ $record->createdBy?->name ?? '-' }} )</div>
                <div style="font-size: 12px; color: #555;">Dicatat Oleh</div>
            </td>
        </tr>
    </table>
</body>
</html>
