<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Summary Repack - {{ $repack->doc_no }}</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 3px 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }

        .data-table th {
            background-color: #f2f2f2;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .summary-box {
            float: right;
            width: 300px;
            border: 1px solid #000;
            padding: 10px;
        }

        .footer-sig {
            margin-top: 50px;
            width: 100%;
            display: flex;
            justify-content: space-between;
        }

        .sig-box {
            text-align: center;
            width: 200px;
        }

        .sig-space {
            height: 60px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>LAPORAN PRODUKSI REPACK</h1>
        <div style="font-size: 14px; font-weight: bold;">PT. SANTI WIJAYA MEAT</div>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%">No. Batch</td>
            <td width="35%">: <strong>{{ $repack->doc_no }}</strong></td>
            <td width="15%">Tanggal</td>
            <td width="35%">: {{ \Carbon\Carbon::parse($repack->repack_date)->format('d-M-Y') }}</td>
        </tr>
        <tr>
            <td>Catatan</td>
            <td>: {{ $repack->note ?? '-' }}</td>
            <td>Status</td>
            <td>: {{ $repack->kunci ? 'LOCKED (FINAL)' : 'DRAFT' }}</td>
        </tr>
    </table>

    <div class="font-bold" style="margin-bottom: 5px;">A. BAHAN BAKU (INPUT) - REKAPITULASI</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="45%">Nama Produk</th>
                <th width="15%" class="text-center">Jml Box</th>
                <th width="15%" class="text-center">Total Pcs</th>
                <th width="20%" class="text-right">Total Berat (Kg)</th>
            </tr>
        </thead>
        <tbody>
            @php
            // Mengelompokkan data berdasarkan nama produk
            $groupedBahan = $bahan->groupBy(fn($item) => $item->product->name ?? 'Unknown');
            $totalBahanWeight = 0;
            $totalBahanPcs = 0;
            $totalBahanBox = 0;
            $no = 1;
            @endphp

            @forelse($groupedBahan as $productName => $items)
            @php
            $boxCount = $items->count();
            $pcsSum = $items->sum('qty_pcs');
            $weightSum = $items->sum('weight');

            $totalBahanBox += $boxCount;
            $totalBahanPcs += $pcsSum;
            $totalBahanWeight += $weightSum;
            @endphp
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td>{{ $productName }}</td>
                <td class="text-center">{{ $boxCount }}</td>
                <td class="text-center">{{ $pcsSum }}</td>
                <td class="text-right">{{ number_format($weightSum, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">Belum ada bahan</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="font-bold" style="background-color: #fafafa;">
                <td colspan="2" class="text-right">TOTAL BAHAN</td>
                <td class="text-center">{{ $totalBahanBox }}</td>
                <td class="text-center">{{ $totalBahanPcs }}</td>
                <td class="text-right">{{ number_format($totalBahanWeight, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="font-bold" style="margin-bottom: 5px; margin-top: 20px;">B. HASIL PRODUKSI (OUTPUT) - REKAPITULASI</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="35%">Nama Produk</th>
                <th width="10%" class="text-center">Grd</th>
                <th width="15%" class="text-center">Jml Box</th>
                <th width="15%" class="text-center">Total Pcs</th>
                <th width="20%" class="text-right">Total Berat (Kg)</th>
            </tr>
        </thead>
        <tbody>
            @php
            // Mengelompokkan data berdasarkan nama produk + grade
            $groupedHasil = $hasil->groupBy(function($item) {
                return ($item->product->name ?? 'Unknown') . '|' . ($item->grade->name ?? 'Unknown');
            });
            $totalHasilWeight = 0;
            $totalHasilPcs = 0;
            $totalHasilBox = 0;
            $no = 1;
            @endphp

            @forelse($groupedHasil as $key => $items)
            @php
            $firstItem = $items->first();
            $productName = $firstItem->product->name ?? 'Unknown';
            $gradeName = $firstItem->grade->name ?? 'Unknown';

            $boxCount = $items->count();
            $pcsSum = $items->sum('qty_pcs');
            $weightSum = $items->sum('weight');

            $totalHasilBox += $boxCount;
            $totalHasilPcs += $pcsSum;
            $totalHasilWeight += $weightSum;
            @endphp
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td>{{ $productName }}</td>
                <td class="text-center">{{ $gradeName }}</td>
                <td class="text-center">{{ $boxCount }}</td>
                <td class="text-center">{{ $pcsSum }}</td>
                <td class="text-right">{{ number_format($weightSum, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">Belum ada hasil</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="font-bold" style="background-color: #fafafa;">
                <td colspan="3" class="text-right">TOTAL HASIL</td>
                <td class="text-center">{{ $totalHasilBox }}</td>
                <td class="text-center">{{ $totalHasilPcs }}</td>
                <td class="text-right">{{ number_format($totalHasilWeight, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-box" style="margin-top: 10px;">
        <table width="100%">
            <tr>
                <td>Total Bahan (In)</td>
                <td class="text-right font-bold">{{ number_format($totalBahanWeight, 2) }} Kg</td>
            </tr>
            <tr>
                <td>Total Hasil (Out)</td>
                <td class="text-right font-bold">{{ number_format($totalHasilWeight, 2) }} Kg</td>
            </tr>
            <tr class="font-bold" style="border-top: 2px solid #000;">
                <td>Balance (Loss)</td>
                @php $balance = $totalHasilWeight - $totalBahanWeight; @endphp
                <td class="text-right" style="{{ $balance < 0 ? 'color: red;' : '' }}">
                    {{ number_format($balance, 2) }} Kg
                </td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>

    <div class="footer-sig">
        <div class="sig-box">
            Operator Produksi
            <div class="sig-space"></div>
            ( ____________________ )
        </div>
        <div class="sig-box">
            QC / Supervisor
            <div class="sig-space"></div>
            ( ____________________ )
        </div>
    </div>

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
