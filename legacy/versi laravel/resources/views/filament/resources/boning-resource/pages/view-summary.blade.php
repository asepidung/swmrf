<div class="overflow-x-auto">
    <table class="w-full text-sm text-left divide-y divide-gray-200 dark:divide-white/5">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="px-4 py-3 font-medium text-center text-gray-500 dark:text-gray-400" style="width: 50px;">#</th>
                <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Product</th>
                <th class="px-4 py-3 font-medium text-center text-gray-500 dark:text-gray-400" style="width: 90px;">Box</th>
                <th class="px-4 py-3 font-medium text-center text-gray-500 dark:text-gray-400" style="width: 90px;">Pcs</th>
                <th class="px-4 py-3 font-medium text-right text-gray-500 dark:text-gray-400" style="width: 120px;">Qty (Kg)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @php
            $no = 1;
            $grandBox = 0;
            $grandPcs = 0;
            $grandQty = 0;
            @endphp
            @foreach($summary as $item)
            @php
            $grandBox += $item['box'];
            $grandPcs += $item['pcs'];
            $grandQty += $item['qty'];
            @endphp
            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                <td class="px-4 py-3 text-center text-gray-900 dark:text-gray-200">{{ $no++ }}</td>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item['product_name'] }}</td>
                <td class="px-4 py-3 text-center text-gray-900 dark:text-gray-200">{{ $item['box'] }}</td>
                <td class="px-4 py-3 text-center text-gray-900 dark:text-gray-200">{{ $item['pcs'] }}</td>
                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-200">{{ number_format($item['qty'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th colspan="2" class="px-4 py-3 font-bold text-right text-gray-900 dark:text-white">GRAND TOTAL</th>
                <th class="px-4 py-3 font-bold text-center text-gray-900 dark:text-white">{{ $grandBox }}</th>
                <th class="px-4 py-3 font-bold text-center text-gray-900 dark:text-white">{{ $grandPcs }}</th>
                <th class="px-4 py-3 font-bold text-right text-gray-900 dark:text-white">{{ number_format($grandQty, 2) }}</th>
            </tr>
        </tfoot>
    </table>
</div>