@php
    $report = $this->getReport();
    $number = fn ($value): string => number_format((float) $value, 2, ',', '.');
    $rupiah = fn ($value): string => $value === null ? '-' : 'Rp '.number_format((float) $value, 0, ',', '.');
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
            {{ __('Delivery orders in this period. HPP is read from the cost of the barcodes that were shipped, at the time this report is opened. Rows not invoiced yet, or with barcodes that have no cost yet, have no margin.') }}
        </p>
    </x-filament::section>

    <x-filament::section :heading="__('Per delivery order')">
        @if (count($report['rows']))
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="{{ $head }}">{{ __('Date') }}</th>
                            <th class="{{ $head }}">{{ __('Delivery Order') }}</th>
                            <th class="{{ $head }}">{{ __('Invoice') }}</th>
                            <th class="{{ $head }}">{{ __('Customer') }}</th>
                            <th class="{{ $head }}">{{ __('Items') }}</th>
                            <th class="{{ $head }} text-end">Kg</th>
                            <th class="{{ $head }} text-end">{{ __('Sales') }}</th>
                            <th class="{{ $head }} text-end">HPP</th>
                            <th class="{{ $head }} text-end">{{ __('Margin') }}</th>
                            <th class="{{ $head }} text-end">{{ __('Unvalued barcodes') }}</th>
                        </tr>
                    </thead>
                    @foreach (collect($report['rows'])->groupBy('delivery_order') as $doNumber => $lines)
                        @php
                            $first = $lines->first();
                            $sum = [
                                'kg' => $lines->sum('kg'),
                                'sales' => $lines->contains(fn ($l) => $l['sales'] === null) ? null : $lines->sum('sales'),
                                'hpp' => $lines->sum('hpp'),
                                'margin' => $lines->contains(fn ($l) => $l['margin'] === null) ? null : $lines->sum('margin'),
                                'unvalued' => $lines->sum('unvalued_barcodes'),
                            ];
                        @endphp
                        {{-- Satu baris per surat jalan/faktur; klik untuk melihat barangnya (Owner, 24 Oktober 2026). --}}
                        <tbody x-data="{ open: false }" class="{{ $row }}">
                            <tr class="cursor-pointer border-t border-gray-100 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5" x-on:click="open = ! open">
                                <td class="py-3 pe-3 tabular-nums">
                                    <span class="inline-block w-4 text-gray-400" x-text="open ? '▾' : '▸'"></span>{{ \Carbon\Carbon::parse($first['date'])->format('d/m/Y') }}
                                </td>
                                <td class="py-3 pe-3">{{ $doNumber }}</td>
                                <td class="py-3 pe-3">{{ $first['invoice'] ?? '-' }}</td>
                                <td class="py-3 pe-3">{{ $first['customer'] }}</td>
                                <td class="py-3 pe-3 text-gray-500">{{ $lines->count() }} {{ __('items') }}</td>
                                <td class="py-3 text-end tabular-nums">{{ $number($sum['kg']) }}</td>
                                <td class="py-3 text-end tabular-nums">{{ $rupiah($sum['sales']) }}</td>
                                <td class="py-3 text-end tabular-nums">{{ $rupiah($sum['hpp']) }}</td>
                                <td class="py-3 text-end tabular-nums">{{ $rupiah($sum['margin']) }}</td>
                                <td class="py-3 text-end tabular-nums">
                                    @if ($sum['unvalued'] > 0)
                                        <x-filament::badge color="warning" size="sm" class="inline-flex">{{ $sum['unvalued'] }}</x-filament::badge>
                                    @else
                                        0
                                    @endif
                                </td>
                            </tr>
                            @foreach ($lines as $line)
                                <tr x-show="open" x-cloak class="bg-gray-50/60 text-xs dark:bg-white/5">
                                    <td></td>
                                    <td colspan="3"></td>
                                    <td class="py-2 pe-3">{{ $line['product'] }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $number($line['kg']) }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $rupiah($line['sales']) }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $rupiah($line['hpp']) }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $rupiah($line['margin']) }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $line['unvalued_barcodes'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                    <tfoot>
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-3 font-semibold" colspan="5">{{ __('Total') }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $number($total['kg']) }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($total['sales']) }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($total['hpp']) }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($total['margin']) }}</td>
                            <td class="py-3 text-end font-semibold tabular-nums">{{ $total['unvalued_barcodes'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($total['not_invoiced_rows'] > 0 || $total['unvalued_barcodes'] > 0)
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <x-filament::badge color="warning" size="sm" class="inline-flex">{{ __('incomplete') }}</x-filament::badge>
                    {{ $total['not_invoiced_rows'] }} {{ __('rows not invoiced yet') }},
                    {{ $total['unvalued_barcodes'] }} {{ __('barcodes without a cost') }} ({{ $number($total['unvalued_kg']) }} kg).
                    {{ $total['margin_rows'] }} / {{ count($report['rows']) }} {{ __('rows have a margin') }}.
                </div>
            @endif
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No delivery orders in this period.') }}</p>
        @endif
    </x-filament::section>

    @foreach ([['by_customer', __('Per customer')], ['by_product', __('Per product')]] as [$key, $heading])
        @if (count($report[$key]))
            <x-filament::section :heading="$heading">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th class="{{ $head }}">{{ $key === 'by_customer' ? __('Customer') : __('Product') }}</th>
                                <th class="{{ $head }} text-end">Kg</th>
                                <th class="{{ $head }} text-end">{{ __('Sales') }}</th>
                                <th class="{{ $head }} text-end">HPP</th>
                                <th class="{{ $head }} text-end">{{ __('Margin') }}</th>
                                <th class="{{ $head }} text-end">{{ __('Unvalued barcodes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report[$key] as $group)
                                <tr class="{{ $row }}">
                                    <td class="py-3 pe-3">{{ $group['name'] }}</td>
                                    <td class="py-3 text-end tabular-nums">{{ $number($group['kg']) }}</td>
                                    <td class="py-3 text-end tabular-nums">{{ $rupiah($group['sales']) }}</td>
                                    <td class="py-3 text-end tabular-nums">{{ $rupiah($group['hpp']) }}</td>
                                    <td class="py-3 text-end tabular-nums">{{ $rupiah($group['margin']) }}</td>
                                    <td class="py-3 text-end tabular-nums">{{ $group['unvalued_barcodes'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endforeach
</x-filament-panels::page>
