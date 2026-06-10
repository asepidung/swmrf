<div class="p-4">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse border border-gray-200 dark:border-gray-700">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800">
                    <th class="border border-gray-200 dark:border-gray-700 p-2 text-left text-sm font-bold text-gray-700 dark:text-gray-200">{{ __('Product') }}</th>
                    <th class="border border-gray-200 dark:border-gray-700 p-2 text-center text-sm font-bold text-gray-700 dark:text-gray-200">{{ __('Box') }}</th>
                    <th class="border border-gray-200 dark:border-gray-700 p-2 text-center text-sm font-bold text-gray-700 dark:text-gray-200">{{ __('Pcs') }}</th>
                    <th class="border border-gray-200 dark:border-gray-700 p-2 text-right text-sm font-bold text-gray-700 dark:text-gray-200">{{ __('Qty (Kg)') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalBox = 0;
                    $totalPcs = 0;
                    $totalQty = 0;
                @endphp
                @forelse($summary as $row)
                    @php
                        $totalBox += $row['box'];
                        $totalPcs += $row['pcs'];
                        $totalQty += $row['qty'];
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-sm text-gray-700 dark:text-gray-200">{{ $row['product_name'] }}</td>
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-center text-sm text-gray-700 dark:text-gray-200">{{ $row['box'] }}</td>
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-center text-sm text-gray-700 dark:text-gray-200">{{ $row['pcs'] }}</td>
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-right text-sm text-gray-700 dark:text-gray-200">{{ number_format($row['qty'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="border border-gray-200 dark:border-gray-700 p-4 text-center text-sm text-gray-500">{{ __('No production items found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            @if(count($summary) > 0)
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-800 font-bold">
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-sm text-gray-700 dark:text-gray-200">{{ __('GRAND TOTAL') }}</td>
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-center text-sm text-gray-700 dark:text-gray-200">{{ $totalBox }}</td>
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-center text-sm text-gray-700 dark:text-gray-200">{{ $totalPcs }}</td>
                        <td class="border border-gray-200 dark:border-gray-700 p-2 text-right text-sm text-gray-700 dark:text-gray-200">{{ number_format($totalQty, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
