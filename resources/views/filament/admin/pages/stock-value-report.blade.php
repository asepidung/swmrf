@php
    $report = $this->getReport();
    $number = fn ($value): string => number_format((float) $value, 2, ',', '.');
    $rupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
    $head = 'py-2 text-xs font-medium text-gray-500 dark:text-gray-400';
    $row = 'border-t border-gray-100 dark:border-white/5';
    $total = $report['total'];
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Only stock that is in the warehouse is counted. Barcodes without a cost yet are shown separately and are not counted as zero.') }}
        </p>
    </x-filament::section>

    <x-filament::section :heading="__('Stock Value Report')">
        @if (count($report['rows']))
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="{{ $head }}">{{ __('Warehouse') }}</th>
                            <th class="{{ $head }}">{{ __('Product') }}</th>
                            <th class="{{ $head }} text-end">{{ __('Barcodes') }}</th>
                            <th class="{{ $head }} text-end">Kg</th>
                            <th class="{{ $head }} text-end">{{ __('Value') }}</th>
                            <th class="{{ $head }} text-end">{{ __('Unvalued barcodes') }}</th>
                            <th class="{{ $head }} text-end">{{ __('Unvalued Kg') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $line)
                            <tr class="{{ $row }}">
                                <td class="py-3 pe-4">{{ $line['warehouse'] }}</td>
                                <td class="py-3 pe-4">{{ $line['product'] }}</td>
                                <td class="py-3 text-end tabular-nums">{{ $line['barcodes'] }}</td>
                                <td class="py-3 text-end tabular-nums">{{ $number($line['kg']) }}</td>
                                <td class="py-3 text-end tabular-nums">{{ $rupiah($line['value']) }}</td>
                                <td class="py-3 text-end tabular-nums">
                                    @if ($line['unvalued_barcodes'] > 0)
                                        <x-filament::badge color="warning" size="sm" class="inline-flex">{{ $line['unvalued_barcodes'] }}</x-filament::badge>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="py-3 text-end tabular-nums">{{ $number($line['unvalued_kg']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-4 font-semibold" colspan="2">{{ __('Total') }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $total['barcodes'] }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $number($total['kg']) }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($total['value']) }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $total['unvalued_barcodes'] }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $number($total['unvalued_kg']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No stock found.') }}</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
