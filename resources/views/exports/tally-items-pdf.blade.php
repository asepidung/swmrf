<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Tally Detail') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h2 { text-align: center; margin-bottom: 4px; }
        p { text-align: center; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>{{ __('Tally Detail') }}: {{ $tally->tally_number }}</h2>
    <p>{{ $tally->customer?->name }} &middot; {{ $tally->delivery_date ? \Carbon\Carbon::parse($tally->delivery_date)->format('d-M-Y') : '' }}</p>
    <table>
        <thead>
            <tr>
                <th>#</th><th>{{ __('Barcode') }}</th><th>{{ __('Item') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Grade') }}</th>
                <th class="text-right">{{ __('Weight') }}</th><th class="text-right">{{ __('Pcs') }}</th><th>{{ __('POD') }}</th><th>{{ __('Origin') }}</th><th>{{ __('Time Scan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r[0] }}</td><td>{{ $r[1] }}</td><td>{{ $r[2] }}</td><td>{{ $r[3] }}</td><td>{{ $r[4] }}</td>
                    <td class="text-right">{{ number_format($r[5], 2, ',', '.') }}</td><td class="text-right">{{ $r[6] }}</td><td>{{ $r[7] }}</td><td>{{ $r[8] }}</td><td>{{ $r[9] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">{{ __('Total') }} ({{ count($rows) }} {{ __('items') }})</td>
                <td class="text-right">{{ number_format(array_sum(array_column($rows, 5)), 2, ',', '.') }}</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
