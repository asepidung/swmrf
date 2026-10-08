@php
    $number = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $rupiah = fn (?float $value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

<div class="text-sm">
    @if (count($summary['wastes']))
        <table class="w-full table-fixed text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="py-2 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Material') }}</th>
                    <th class="w-32 py-2 pe-6 text-end text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Quantity') }}</th>
                    <th class="w-1/4 py-2 ps-2 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Reason') }}</th>
                    <th class="w-44 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['wastes'] as $line)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-3 pe-4">{{ $line['material'] }}</td>
                        <td class="py-3 pe-6 text-end tabular-nums">{{ $number($line['qty']) }} {{ $line['unit'] }}</td>
                        <td class="py-3 ps-2">{{ $line['reason'] }}</td>
                        <td class="py-3 text-end tabular-nums">
                            @if ($line['amount'] === null)
                                <span class="text-gray-400">-</span>
                            @else
                                @if ((float) $line['amount'] <= 0)
                                    <x-filament::badge color="warning" size="sm" class="me-2 inline-flex">{{ __('no price yet') }}</x-filament::badge>
                                @endif
                                {{ $rupiah($line['amount']) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @if ($summary['final'])
                <tfoot>
                    <tr>
                        <td class="py-3 pe-4 font-semibold" colspan="3">{{ __('Total wasted') }}</td>
                        <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($summary['waste_total']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @else
        <p class="py-2 text-gray-500 dark:text-gray-400">{{ __('Nothing was wasted.') }}</p>
    @endif
</div>
