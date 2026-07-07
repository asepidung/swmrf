<div class="overflow-x-auto">
    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-4 py-3">{{ __('Item') }}</th>
                <th scope="col" class="px-4 py-3">{{ __('Grade') }}</th>
                <th scope="col" class="px-4 py-3">{{ __('Qty') }}</th>
                <th scope="col" class="px-4 py-3">{{ __('pH') }}</th>
                <th scope="col" class="px-4 py-3">{{ __('POD') }}</th>
                <th scope="col" class="px-4 py-3">{{ __('Origin') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr class="border-b dark:border-gray-700">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item->product?->name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $item->grade?->name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ number_format($item->weight, 2, '.', '') }} / {{ $item->qty_pcs }}</td>
                    <td class="px-4 py-3">{{ $item->ph_level ? number_format($item->ph_level, 2) : '-' }}</td>
                    <td class="px-4 py-3">{{ $item->pack_date ? \Carbon\Carbon::parse($item->pack_date)->format('d/m/Y') : '-' }}</td>
                    <td class="px-4 py-3">{{ \App\Helpers\BarcodeHelper::getOrigin($item->barcode) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No missing items.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
