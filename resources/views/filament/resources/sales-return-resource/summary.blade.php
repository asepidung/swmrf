@php
    $record = $getRecord();
    $summary = $record ? $record->items()
        ->select(
            'product_id',
            \Illuminate\Support\Facades\DB::raw('count(id) as total_box'),
            \Illuminate\Support\Facades\DB::raw('sum(weight) as total_weight'),
            \Illuminate\Support\Facades\DB::raw('sum(line_amount) as total_amount'),
        )
        ->groupBy('product_id')
        ->with('product')
        ->get() : collect();

    $grandTotalBox = $summary->sum('total_box');
    $grandTotalWeight = $summary->sum('total_weight');

    // Nilainya baru ada sesudah returnya disetujui -- di situlah harganya
    // di-snapshot. Selama masih Draft kolomnya sengaja tidak ditampilkan,
    // supaya tidak ada angka nol yang terbaca sebagai "gratis".
    $adaNilai = $record && (float) $record->credit_amount > 0;
@endphp

@if($record && $summary->count() > 0)
<div class="mt-4">
    <h3 class="text-lg font-bold mb-4">{{ __('Item Summary') }}</h3>
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2">{{ __('Product') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Total Box') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Total Weight (Kg)') }}</th>
                    @if($adaNilai)
                        <th class="px-4 py-2 text-right">{{ __('Credit Value') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($summary as $row)
                <tr>
                    <td class="px-4 py-2">{{ $row->product->name ?? __('Unknown') }}</td>
                    <td class="px-4 py-2 text-right">{{ $row->total_box }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($row->total_weight, 2) }}</td>
                    @if($adaNilai)
                        <td class="px-4 py-2 text-right">{{ number_format((float) $row->total_amount, 0, ',', '.') }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-100 dark:bg-gray-800 font-bold">
                <tr>
                    <td class="px-4 py-2 text-right">{{ __('Grand Total') }}</td>
                    <td class="px-4 py-2 text-right">{{ $grandTotalBox }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($grandTotalWeight, 2) }}</td>
                    @if($adaNilai)
                        <td class="px-4 py-2 text-right">{{ number_format((float) $record->credit_amount, 0, ',', '.') }}</td>
                    @endif
                </tr>
            </tfoot>
        </table>
    </div>

    @if($adaNilai)
        @php
            // Satu retur bisa memotong BEBERAPA invoice sekaligus: pelanggan
            // mengembalikan barang dari beberapa kiriman dalam satu kali jalan.
            $invoices = $record->items->pluck('invoice.invoice_number')->filter()->unique();
            $adaYangMenunggu = $record->items->contains(fn ($item) => $item->invoice_id === null
                && (float) $item->line_amount > 0);
        @endphp

        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            @if($invoices->isNotEmpty())
                {{ __('This return reduces :invoices.', ['invoices' => $invoices->join(', ')]) }}
            @endif
            @if($adaYangMenunggu)
                {{ __('Part of this return is not attached to any invoice yet; it will reduce the invoice made for its delivery order.') }}
            @endif
        </p>
    @endif
</div>
@endif
