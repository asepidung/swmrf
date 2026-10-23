@php
    $report = $this->getReport();
    $total = $report['total'];
    $number = fn ($value): string => number_format((float) $value, 2, ',', '.');
    $rupiah = fn ($value): string => $value === null ? '-' : 'Rp '.number_format((float) $value, 0, ',', '.');
    $head = 'py-2 text-xs font-medium text-gray-500 dark:text-gray-400';
    $row = 'border-t border-gray-100 dark:border-white/5';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <dl class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
            <div><dt class="text-xs text-gray-500">{{ __('Invoice') }}</dt><dd class="font-semibold">{{ $report['invoice'] ?? '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('Delivery Order') }}</dt><dd class="font-semibold">{{ $deliveryOrder->delivery_order_number }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('Customer') }}</dt><dd class="font-semibold">{{ $deliveryOrder->customer?->name ?? '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('Shipped') }}</dt><dd class="font-semibold">{{ \Carbon\Carbon::parse($deliveryOrder->delivery_date)->format('d/m/Y') }}</dd></div>
        </dl>
    </x-filament::section>

    <x-filament::section :heading="__('Items')">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="{{ $head }}">{{ __('Product') }}</th>
                        <th class="{{ $head }} text-end">Kg</th>
                        <th class="{{ $head }} text-end">{{ __('Sales') }}</th>
                        <th class="{{ $head }} text-end">HPP</th>
                        <th class="{{ $head }} text-end">{{ __('Margin') }}</th>
                        <th class="{{ $head }} text-end whitespace-nowrap">{{ __('Unvalued barcodes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['rows'] as $line)
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-4">{{ $line['product'] }}</td>
                            <td class="whitespace-nowrap py-3 pe-4 text-end tabular-nums">{{ $number($line['kg']) }}</td>
                            <td class="whitespace-nowrap py-3 pe-4 text-end tabular-nums">{{ $rupiah($line['sales']) }}</td>
                            <td class="whitespace-nowrap py-3 pe-4 text-end tabular-nums">{{ $line['unvalued_barcodes'] > 0 ? '-' : $rupiah($line['hpp']) }}</td>
                            <td class="whitespace-nowrap py-3 pe-4 text-end tabular-nums">{{ $rupiah($line['margin']) }}</td>
                            <td class="py-3 text-end tabular-nums">
                                @if ($line['unvalued_barcodes'] > 0)
                                    <x-filament::badge color="warning" size="sm" class="inline-flex">{{ $line['unvalued_barcodes'] }}</x-filament::badge>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="{{ $row }}">
                        <td class="py-3 pe-4 font-semibold">{{ __('Total') }}</td>
                        <td class="whitespace-nowrap py-3 pe-4 text-end font-semibold tabular-nums">{{ $number($total['kg']) }}</td>
                        <td class="whitespace-nowrap py-3 pe-4 text-end font-semibold tabular-nums">{{ $rupiah($total['sales']) }}</td>
                        <td class="whitespace-nowrap py-3 pe-4 text-end font-semibold tabular-nums">{{ $total['unvalued_barcodes'] > 0 ? '-' : $rupiah($total['hpp']) }}</td>
                        <td class="whitespace-nowrap py-3 pe-4 text-end font-semibold tabular-nums">{{ $total['margin_rows'] > 0 ? $rupiah($total['margin']) : '-' }}</td>
                        <td class="py-3 text-end font-semibold tabular-nums">{{ $total['unvalued_barcodes'] }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($total['unvalued_barcodes'] > 0 || $total['not_invoiced_rows'] > 0)
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                <x-filament::badge color="warning" size="sm" class="inline-flex">{{ __('incomplete') }}</x-filament::badge>
                {{ $total['unvalued_barcodes'] }} {{ __('barcodes without a cost') }} ({{ $number($total['unvalued_kg']) }} kg).
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
