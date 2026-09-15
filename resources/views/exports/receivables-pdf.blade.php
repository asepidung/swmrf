<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Receivables') }}</title>
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
    <h2>{{ $title ?? __('Receivables') }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">{{ __('No') }}</th>
                <th>{{ __('Group Name') }}</th>
                <th class="text-right">{{ __('Total Receivable') }}</th>
                <th class="text-center">{{ __('Number of Invoices') }}</th>
                <th class="text-right">{{ __('Due Soon') }}</th>
                <th class="text-right">{{ __('Overdue') }}</th>
                <th class="text-right">{{ __('Customer Deposit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $record->name }}</td>
                    <td class="text-right">Rp {{ number_format($record->total_receivable ?? 0, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $record->total_receivable_count ?? 0 }}</td>
                    <td class="text-right">Rp {{ number_format($record->due_soon ?? 0, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($record->overdue ?? 0, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($depositOf($record), 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

