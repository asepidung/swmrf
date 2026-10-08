@php
    $rupiah = fn (?float $value): string => $value === null ? '-' : 'Rp '.number_format($value, 0, ',', '.');
@endphp

<div class="space-y-6 text-sm">
    @unless ($summary['final'])
        <p class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-warning-700 dark:border-warning-800 dark:bg-warning-900 dark:text-warning-400">
            {{ __('Not final yet. These figures are recalculated until the document is locked.') }}
        </p>
    @endunless

    <div>
        <p class="mb-2 font-semibold">{{ __('Usage per BOM') }}</p>
        @if (count($summary['bom']))
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="py-2 pe-4 font-semibold">{{ __('Material') }}</th>
                        <th class="py-2 pe-4 text-end font-semibold">{{ __('Quantity') }}</th>
                        <th class="py-2 font-semibold">{{ __('Unit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['bom'] as $row)
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
    </div>

    <div>
        <p class="mb-1 font-semibold">{{ __('Drylog / Pad Absorber') }}</p>
        @if ($summary['drylog'] === null)
            <p class="text-gray-500 dark:text-gray-400">{{ __('Not filled in yet.') }}</p>
        @else
            <p class="tabular-nums">{{ number_format($summary['drylog'], 0, ',', '.') }} {{ __('pcs') }}</p>
        @endif
    </div>

    <div>
        <p class="mb-2 font-semibold">{{ __('Wasted Material') }}</p>
        @if (count($summary['wastes']))
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="py-2 pe-4 font-semibold">{{ __('Material') }}</th>
                        <th class="py-2 pe-4 text-end font-semibold">{{ __('Quantity') }}</th>
                        <th class="py-2 pe-4 font-semibold">{{ __('Reason') }}</th>
                        <th class="py-2 text-end font-semibold">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['wastes'] as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="py-2 pe-4">{{ $row['material'] }}</td>
                            <td class="py-2 pe-4 text-end tabular-nums">{{ number_format($row['qty'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                            <td class="py-2 pe-4">{{ $row['reason'] }}</td>
                            <td class="py-2 text-end tabular-nums">
                                {{ $rupiah($row['amount']) }}
                                @if ($summary['final'] && (float) $row['amount'] <= 0)
                                    <span class="text-warning-600">({{ __('no price yet') }})</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                @if ($summary['final'])
                    <tfoot>
                        <tr>
                            <td class="py-2 pe-4 font-semibold" colspan="3">{{ __('Total wasted') }}</td>
                            <td class="py-2 text-end font-semibold tabular-nums">{{ $rupiah($summary['waste_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        @else
            <p class="text-gray-500 dark:text-gray-400">{{ __('Nothing was wasted.') }}</p>
        @endif
    </div>
</div>
