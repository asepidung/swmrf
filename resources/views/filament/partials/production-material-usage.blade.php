@php
    $number = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $rupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

<div class="space-y-4 text-sm">
    @unless ($summary['final'])
        <x-filament::badge color="warning" icon="heroicon-m-clock">
            {{ __('Not final yet. These figures are recalculated until the document is locked.') }}
        </x-filament::badge>
    @endunless

    <table class="w-full table-fixed text-left">
        <thead>
            <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="py-2 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Material') }}</th>
                <th class="w-40 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Quantity') }}</th>
                @if ($summary['final'])
                    <th class="w-44 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Value') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($summary['bom'] as $line)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-3 pe-4">{{ $line['material'] }}</td>
                    <td class="py-3 text-end tabular-nums">{{ $number($line['qty']) }} {{ __('pcs') }}</td>
                    @if ($summary['final'])
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
                    @endif
                </tr>
            @empty
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-3 text-gray-500 dark:text-gray-400" colspan="{{ $summary['final'] ? 3 : 2 }}">{{ __('No material usage according to the BOM.') }}</td>
                </tr>
            @endforelse

            {{-- Drylog diisi manual; ia bagian dari pemakaian material. --}}
            <tr>
                <td class="py-3 pe-4">
                    {{ $summary['drylog_name'] ?? __('Drylog / Pad Absorber') }}
                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">({{ __('entered manually') }})</span>
                </td>
                <td class="py-3 text-end tabular-nums">
                    @if ($summary['drylog'] === null)
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Not filled in yet.') }}</span>
                    @else
                        {{ $number($summary['drylog']) }} {{ __('pcs') }}
                    @endif
                </td>
                @if ($summary['final'])
                    <td class="py-3 text-end tabular-nums">
                        @if ($summary['drylog_amount'] === null)
                            <span class="text-gray-400">-</span>
                        @else
                            @if ($summary['drylog_amount'] <= 0)
                                <x-filament::badge color="warning" size="sm" class="me-2 inline-flex">{{ __('no price yet') }}</x-filament::badge>
                            @endif
                            {{ $rupiah($summary['drylog_amount']) }}
                        @endif
                    </td>
                @endif
            </tr>
        </tbody>
        @if ($summary['final'])
            <tfoot>
                <tr class="border-t border-gray-200 dark:border-white/10">
                    <td class="py-3 pe-4 font-semibold" colspan="2">{{ __('Total usage value') }}</td>
                    <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($summary['usage_total']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($summary['final'] && $summary['usage_unpriced'] > 0)
        <div>
            <x-filament::badge color="warning" size="sm" class="inline-flex">{{ __('no price yet') }}</x-filament::badge>
            <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">{{ $summary['usage_unpriced'] }} {{ __('rows without a price are counted as Rp 0.') }}</span>
        </div>
    @endif
</div>
