@php
    $rupiah = fn ($value): string => $value === null ? '-' : 'Rp '.number_format((float) $value, 0, ',', '.');
    $number = fn ($value, int $decimals = 2): string => number_format((float) $value, $decimals, ',', '.');
@endphp

<div class="space-y-4 text-sm">
    @if (! $valuation)
        <p class="text-gray-500 dark:text-gray-400">{{ __('This repack has not been valued yet.') }}</p>
    @else
        <div class="flex flex-wrap items-center gap-2">
            @if ($valuation->isPending())
                <x-filament::badge color="warning" icon="heroicon-m-clock">{{ __('Waiting: some input goods have no cost yet. It is valued automatically once all of them do.') }}</x-filament::badge>
            @else
                <x-filament::badge color="success">{{ __('Valued') }}</x-filament::badge>
            @endif

            @if ($valuation->needs_review)
                <x-filament::badge color="warning">{{ __('Needs review: check the stock value of the new products, or a result cost that is zero or does not add up.') }}</x-filament::badge>
            @endif
        </div>

        <table class="w-full table-fixed text-left">
            <tbody>
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ __('Input goods cost') }}</td>
                    <td class="py-2 text-end tabular-nums">{{ $number($valuation->total_input_kg) }} kg &middot; {{ $rupiah($valuation->total_input_cost) }}</td>
                </tr>
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ __('Shrinkage (financial loss)') }}</td>
                    <td class="py-2 text-end tabular-nums">{{ $number($valuation->shrink_kg) }} kg &middot; {{ $rupiah($valuation->shrink_value) }}</td>
                </tr>
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ __('New products / extra kg (stock value)') }}</td>
                    <td class="py-2 text-end tabular-nums">{{ $rupiah($valuation->new_product_value) }}</td>
                </tr>
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ __('Difference carried by the products that were changed') }}</td>
                    <td class="py-2 text-end tabular-nums">{{ $rupiah($valuation->difference_value) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="w-full table-fixed text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="py-2 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Product') }}</th>
                    <th class="w-28 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Weight') }}</th>
                    <th class="w-36 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Cost per kg') }}</th>
                    <th class="w-40 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-3 pe-4">{{ $line['product'] }}</td>
                        <td class="py-3 text-end tabular-nums">{{ $number($line['kg']) }} kg</td>
                        <td class="py-3 text-end tabular-nums">{{ $rupiah($line['cost_per_kg']) }}</td>
                        <td class="py-3 text-end tabular-nums">{{ $rupiah($line['value']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
