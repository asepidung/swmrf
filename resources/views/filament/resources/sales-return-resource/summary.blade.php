@php
    $record = $getRecord();
    $summary = $record ? $record->items()
        ->select('product_id', \Illuminate\Support\Facades\DB::raw('count(id) as total_box'), \Illuminate\Support\Facades\DB::raw('sum(weight) as total_weight'))
        ->groupBy('product_id')
        ->with('product')
        ->get() : collect();
    
    $grandTotalBox = $summary->sum('total_box');
    $grandTotalWeight = $summary->sum('total_weight');
@endphp

@if($record && $summary->count() > 0)
<div class="mt-4">
    <h3 class="text-lg font-bold mb-4">Item Summary</h3>
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2">Product</th>
                    <th class="px-4 py-2 text-right">Total Box</th>
                    <th class="px-4 py-2 text-right">Total Weight (Kg)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($summary as $row)
                <tr>
                    <td class="px-4 py-2">{{ $row->product->name ?? 'Unknown' }}</td>
                    <td class="px-4 py-2 text-right">{{ $row->total_box }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($row->total_weight, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-100 dark:bg-gray-800 font-bold">
                <tr>
                    <td class="px-4 py-2 text-right">Grand Total</td>
                    <td class="px-4 py-2 text-right">{{ $grandTotalBox }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($grandTotalWeight, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif
