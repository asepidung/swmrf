@php
    $record = $getRecord();
    $summary = \App\Models\MutationItem::where('mutation_id', $record->id)
        ->join('products', 'mutation_items.product_id', '=', 'products.id')
        ->selectRaw('products.name as product_name, sum(weight) as total_weight, sum(qty_pcs) as total_pcs, count(barcode) as total_carton')
        ->groupBy('products.name')
        ->get();
@endphp

<div class="fi-ta-content divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10 border dark:border-white/10 rounded-xl">
    <table class="fi-ta-table w-full text-left divide-y divide-gray-200 dark:divide-white/5">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Product</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-right">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Total Koli</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-right">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Total Qty (Kg)</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-right">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Total Pcs</span>
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
            @forelse($summary as $item)
                <tr class="fi-ta-row bg-white dark:bg-gray-900">
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                        <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                            <span class="text-sm text-gray-950 dark:text-white">{{ $item->product_name }}</span>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 text-right">
                        <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                            <span class="text-sm text-gray-950 dark:text-white">{{ $item->total_carton }}</span>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 text-right">
                        <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                            <span class="text-sm text-gray-950 dark:text-white">{{ number_format($item->total_weight, 2) }}</span>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 text-right">
                        <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                            <span class="text-sm text-gray-950 dark:text-white">{{ number_format($item->total_pcs, 0) }}</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada barang</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot class="bg-gray-50 dark:bg-white/5 font-bold">
            <tr>
                <td class="fi-ta-cell p-0 sm:first-of-type:ps-6">
                    <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                        <span class="text-sm text-gray-950 dark:text-white">TOTAL</span>
                    </div>
                </td>
                <td class="fi-ta-cell p-0 sm:last-of-type:pe-6 text-right">
                    <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                        <span class="text-sm text-gray-950 dark:text-white">{{ $summary->sum('total_carton') }}</span>
                    </div>
                </td>
                <td class="fi-ta-cell p-0 sm:last-of-type:pe-6 text-right">
                    <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                        <span class="text-sm text-gray-950 dark:text-white">{{ number_format($summary->sum('total_weight'), 2) }}</span>
                    </div>
                </td>
                <td class="fi-ta-cell p-0 sm:last-of-type:pe-6 text-right">
                    <div class="fi-ta-text grid w-full gap-y-1 px-3 py-4">
                        <span class="text-sm text-gray-950 dark:text-white">{{ number_format($summary->sum('total_pcs'), 0) }}</span>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
