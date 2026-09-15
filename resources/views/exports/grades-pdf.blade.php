<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Grades') }}</title>
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
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? __('Grades') }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 10%">{{ __('Grade Digit') }}</th>
                <th>{{ __('Name') }}</th>
                <th class="text-center" style="width: 20%">{{ __('Active') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td class="text-center">{{ $record->id }}</td>
                    <td>{{ $record->name }}</td>
                    <td class="text-center">{{ $record->is_active ? __('Yes') : __('No') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
