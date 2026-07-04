<div class="fi-ta-content overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-white/10">
    <table class="fi-ta-table w-full text-left text-sm text-gray-600 dark:text-gray-400">
        <thead class="bg-gray-50 text-gray-900 dark:bg-white/5 dark:text-white">
            <tr>
                <th class="px-4 py-3 font-semibold">{{ __('Product') }}</th>
                <th class="px-4 py-3 font-semibold text-center">{{ __('Box') }}</th>
                <th class="px-4 py-3 font-semibold text-center">{{ __('Pcs') }}</th>
                <th class="px-4 py-3 font-semibold text-right">{{ __('Qty (Kg)') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @php
                // Calling the method from the View Record class
                $summary = $this->getProductionSummary();
                $totalBox = 0;
                $totalPcs = 0;
                $totalQty = 0;
            @endphp
            
            @forelse ($summary as $row)
                @php
                    $totalBox += $row['box'];
                    $totalPcs += $row['pcs'];
                    $totalQty += $row['qty'];
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row['product_name'] }}</td>
                    <td class="px-4 py-3 text-center">{{ number_format($row['box']) }}</td>
                    <td class="px-4 py-3 text-center">{{ number_format($row['pcs']) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['qty'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        {{ __('No production data available.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($summary->count() > 0)
        <tfoot class="bg-gray-50 font-bold text-gray-900 dark:bg-white/5 dark:text-white">
            <tr>
                <td class="px-4 py-3">{{ __('GRAND TOTAL') }}</td>
                <td class="px-4 py-3 text-center">{{ number_format($totalBox) }}</td>
                <td class="px-4 py-3 text-center">{{ number_format($totalPcs) }}</td>
                <td class="px-4 py-3 text-right text-primary-600">{{ number_format($totalQty, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
