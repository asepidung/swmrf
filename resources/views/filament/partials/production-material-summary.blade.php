@php
    $number = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $rupiah = fn (?float $value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
    $heading = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $head = 'py-2 text-xs font-medium text-gray-500 dark:text-gray-400';
    $row = 'border-t border-gray-100 dark:border-white/5';
@endphp

<div class="divide-y divide-gray-200 text-sm dark:divide-white/10">
    @unless ($summary['final'])
        <div class="pb-4">
            <x-filament::badge color="warning" icon="heroicon-m-clock">
                {{ __('Not final yet. These figures are recalculated until the document is locked.') }}
            </x-filament::badge>
        </div>
    @endunless

    {{-- Pemakaian menurut BOM --}}
    <section class="py-4 first:pt-0">
        <h4 class="{{ $heading }} mb-2">{{ __('Usage per BOM') }}</h4>

        @if (count($summary['bom']))
            <table class="w-full table-fixed text-left">
                <thead>
                    <tr>
                        <th class="{{ $head }}">{{ __('Material') }}</th>
                        <th class="{{ $head }} w-40 text-end">{{ __('Quantity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['bom'] as $line)
                        <tr class="{{ $row }}">
                            <td class="py-2 pe-4">{{ $line['material'] }}</td>
                            <td class="py-2 text-end tabular-nums">{{ $number($line['qty']) }} {{ __('pcs') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-gray-500 dark:text-gray-400">{{ __('No material usage according to the BOM.') }}</p>
        @endif
    </section>

    {{-- Drylog --}}
    <section class="py-4">
        <h4 class="{{ $heading }} mb-2">{{ __('Drylog / Pad Absorber') }}</h4>

        @if ($summary['drylog'] === null)
            <p class="text-gray-500 dark:text-gray-400">{{ __('Not filled in yet.') }}</p>
        @else
            <table class="w-full table-fixed text-left">
                <tbody>
                    <tr class="{{ $row }}">
                        <td class="py-2 pe-4">{{ __('Drylog / Pad Absorber') }}</td>
                        <td class="w-40 py-2 text-end tabular-nums">{{ $number($summary['drylog']) }} {{ __('pcs') }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </section>

    {{-- Material terbuang --}}
    <section class="py-4 last:pb-0">
        <h4 class="{{ $heading }} mb-2">{{ __('Wasted Material') }}</h4>

        @if (count($summary['wastes']))
            <table class="w-full table-fixed text-left">
                <thead>
                    <tr>
                        <th class="{{ $head }}">{{ __('Material') }}</th>
                        <th class="{{ $head }} w-28 text-end">{{ __('Quantity') }}</th>
                        <th class="{{ $head }} w-1/4 ps-6">{{ __('Reason') }}</th>
                        <th class="{{ $head }} w-48 text-end">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['wastes'] as $line)
                        <tr class="{{ $row }}">
                            <td class="py-2 pe-4">{{ $line['material'] }}</td>
                            <td class="py-2 text-end tabular-nums">{{ $number($line['qty']) }} {{ $line['unit'] }}</td>
                            <td class="py-2 ps-6">{{ $line['reason'] }}</td>
                            <td class="py-2 text-end tabular-nums">
                                @if ($line['amount'] === null)
                                    <span class="text-gray-400">-</span>
                                @else
                                    @if ($summary['final'] && (float) $line['amount'] <= 0)
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
                        <tr class="border-t-2 border-gray-200 dark:border-white/10">
                            <td class="py-2 pe-4 font-semibold" colspan="3">{{ __('Total wasted') }}</td>
                            <td class="py-2 text-end font-semibold tabular-nums">{{ $rupiah($summary['waste_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        @else
            <p class="text-gray-500 dark:text-gray-400">{{ __('Nothing was wasted.') }}</p>
        @endif
    </section>
</div>
