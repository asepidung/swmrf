<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan Mutasi - {{ $record->mutation_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { font-size: 12pt; }
            .no-print { display: none; }
            .print-break { page-break-before: always; }
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #f3f4f6; }
    </style>
</head>
<body class="bg-white text-black p-8 font-sans">
    <!-- Header -->
    <div class="flex justify-between items-start mb-6 border-b-2 border-black pb-4">
        <div>
            <h1 class="text-3xl font-bold uppercase tracking-wider">SURAT JALAN MUTASI</h1>
            <p class="text-lg mt-1 font-semibold">No: {{ $record->mutation_number }}</p>
        </div>
        <div class="text-right">
            <h2 class="text-2xl font-bold">Wijaya Meat SWM</h2>
            <p class="text-sm">Tanggal: {{ $record->mutation_date->format('d/m/Y') }}</p>
            <p class="text-sm font-semibold mt-1">Status: {{ $record->status }}</p>
        </div>
    </div>

    <!-- Info Section -->
    <div class="grid grid-cols-2 gap-8 mb-6">
        <div>
            <h3 class="font-bold border-b border-gray-400 mb-2">DARI GUDANG (ASAL)</h3>
            <p class="font-semibold text-lg">{{ $record->fromWarehouse->name }}</p>
            <p class="text-sm text-gray-700 mt-2">Dibuat Oleh: {{ $record->createdBy->name ?? '-' }}</p>
        </div>
        <div>
            <h3 class="font-bold border-b border-gray-400 mb-2">KE GUDANG (TUJUAN)</h3>
            <p class="font-semibold text-lg">{{ $record->toWarehouse->name }}</p>
            <p class="text-sm text-gray-700 mt-2">Diterima Oleh: {{ $record->receivedBy->name ?? 'Belum Diterima' }}</p>
        </div>
    </div>

    @if($record->note)
    <div class="mb-6 p-4 border border-gray-300 rounded bg-gray-50">
        <span class="font-bold">Catatan:</span> {{ $record->note }}
    </div>
    @endif

    @php
        $summary = \App\Models\MutationItem::where('mutation_id', $record->id)
            ->join('products', 'mutation_items.product_id', '=', 'products.id')
            ->selectRaw('products.name as product_name, sum(weight) as total_weight, sum(qty_pcs) as total_pcs, count(barcode) as total_carton')
            ->groupBy('products.name')
            ->get();
    @endphp

    <!-- Items Table -->
    <div class="mb-8">
        <h3 class="font-bold text-lg mb-2">Ringkasan Barang</h3>
        <table>
            <thead>
                <tr>
                    <th class="w-12 text-center">No</th>
                    <th>Nama Produk</th>
                    <th class="text-right">Total Koli</th>
                    <th class="text-right">Berat (Kg)</th>
                    <th class="text-right">Qty (Pcs)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-right">{{ $item->total_carton }}</td>
                    <td class="text-right">{{ number_format($item->total_weight, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->total_pcs, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-bold bg-gray-100">
                    <td colspan="2" class="text-right">TOTAL KESELURUHAN</td>
                    <td class="text-right">{{ number_format($summary->sum('total_carton'), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($summary->sum('total_weight'), 2, ',', '.') }} Kg</td>
                    <td class="text-right">{{ number_format($summary->sum('total_pcs'), 0, ',', '.') }} Pcs</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Signatures -->
    <div class="grid grid-cols-3 gap-4 mt-12 text-center">
        <div>
            <p class="mb-20">Pengirim (Gudang Asal)</p>
            <p class="font-bold underline">{{ $record->createdBy->name ?? '....................................' }}</p>
        </div>
        <div>
            <p class="mb-20">Mengetahui / Checker</p>
            <p class="font-bold underline">....................................</p>
        </div>
        <div>
            <p class="mb-20">Penerima (Gudang Tujuan)</p>
            <p class="font-bold underline">{{ $record->receivedBy->name ?? '....................................' }}</p>
        </div>
    </div>

    <div class="no-print mt-8 text-center">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
            🖨️ Cetak Dokumen
        </button>
    </div>

    <script>
        window.onload = function() {
            // Uncomment line below if you want to auto-print when opened
            // window.print();
        }
    </script>
</body>
</html>
