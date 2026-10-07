<div class="space-y-4 text-sm">
    @if (count($rows))
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="py-2 pe-4 font-semibold">{{ __('Material') }}</th>
                    <th class="py-2 pe-4 text-end font-semibold">{{ __('Quantity') }}</th>
                    <th class="py-2 font-semibold">{{ __('Unit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2 pe-4">{{ $row['material'] }}</td>
                        <td class="py-2 pe-4 text-end tabular-nums">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                        <td class="py-2">{{ $row['unit'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-gray-500 dark:text-gray-400">{{ __('No material usage according to the BOM.') }}</p>
    @endif

    @if (count($products))
        <div>
            <p class="font-semibold">{{ __('Basis of the calculation') }}</p>
            <ul class="list-disc ps-5 text-gray-600 dark:text-gray-300">
                @foreach ($products as $product)
                    <li>{{ $product['product_name'] }}: {{ number_format($product['box'], 0, ',', '.') }} {{ __('box') }}, {{ number_format($product['pcs'], 0, ',', '.') }} {{ __('pcs') }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (count($without_bom) || count($skipped))
        <div class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-warning-700 dark:border-warning-800 dark:bg-warning-900 dark:text-warning-400">
            <p class="font-semibold">{{ __('Not counted in the figures above') }}</p>
            <ul class="list-disc ps-5">
                @foreach ($without_bom as $product)
                    <li>{{ __('No BOM') }}: {{ $product['product_name'] }}</li>
                @endforeach
                @foreach ($skipped as $skip)
                    <li>{{ __('Variable quantity, filled in manually') }}: {{ $skip['product_name'] }} -- {{ $skip['material_name'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
