<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Customer Spec') }} - {{ $customer->name }}</title>
    <style>
        @page { size: letter; margin: 12mm; }
        body {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #000;
            margin: 20px;
        }
        table { width: 100%; border-collapse: collapse; }
        .items-table { border: 1px solid black; margin-top: 12px; }
        .items-table th, .items-table td { border: 1px solid black; padding: 6px; text-align: left; vertical-align: top; }
        .items-table th { text-align: center; }
    </style>
</head>
<body>
    <table style="margin-bottom: 8px;">
        <tr>
            <td style="vertical-align: top;">
                <span style="font-size: 16px; font-weight: bold; text-transform: uppercase;">{{ __('Customer Spec') }}</span><br />
                <strong style="font-size: 18px;">PT. SANTI WIJAYA MEAT</strong>
            </td>
            <td style="text-align: right; vertical-align: top; width: 40%;">
                <span style="font-size: 12px; color: #555;">{{ __('Printed') }} :</span><br />
                <strong>{{ now()->format('d M Y H:i') }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="font-size: 11px; color: #555; white-space: nowrap;">Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103</td>
        </tr>
    </table>

    <hr style="border: 0; border-top: 1px solid #000; margin: 8px 0;">

    <table style="margin-bottom: 4px;">
        <tr>
            <td style="width: 18%;">{{ __('Customer') }}</td>
            <td>: <strong>{{ $customer->name }}</strong></td>
        </tr>
        <tr>
            <td>{{ __('Customer Group') }}</td>
            <td>: {{ $customer->group?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>{{ __('Customer Segment') }}</td>
            <td>: {{ $customer->segment?->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th>{{ __('Product') }}</th>
                <th>{{ __('Weight per pack') }}</th>
                <th>{{ __('Cut') }}</th>
                <th>{{ __('Condition') }}</th>
                <th>{{ __('Packaging') }}</th>
                <th>{{ __('Spec Note') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($specs as $spec)
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $spec->product?->name }}</td>
                    <td>{{ $spec->weight_per_pack }}</td>
                    <td>{{ $spec->cut }}</td>
                    <td>{{ \App\Models\CustomerProductSpec::conditionLabel($spec->condition) }}</td>
                    <td>{{ $spec->packaging }}</td>
                    <td>{{ $spec->note }}</td>
                </tr>
            @endforeach
        </tbody>
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
