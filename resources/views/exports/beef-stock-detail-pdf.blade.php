<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h2 { text-align: center; margin: 0 0 4px; }
        p { margin: 0 0 8px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        tfoot td { font-weight: bold; background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <p>{{ $keterangan }}</p>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 4%">No</th>
                @foreach($judul as $kolom)
                    <th>{{ $kolom }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($baris as $i => $isi)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    @foreach($isi as $sel)
                        <td>{{ $sel }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">{{ __('Total') }}</td>
                <td class="text-right">{{ number_format($totalBerat, 2, '.', ',') }}</td>
                <td class="text-center">{{ number_format($totalPcs) }}</td>
                <td colspan="{{ count($judul) - 4 }}"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
