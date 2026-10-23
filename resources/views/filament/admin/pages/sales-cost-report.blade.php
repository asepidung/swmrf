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

    @php
        $groupsForCards = collect($report['rows'])->groupBy('delivery_order');
        $completeGroups = $groupsForCards->filter(fn ($lines) => $lines->every(fn ($l) => $l['unvalued_barcodes'] === 0 && $l['sales'] !== null));
        // Margin terbesar di paling atas; yang belum punya margin di bawah (Owner, 24 Oktober 2026).
        $invoiceGroups = ($this->onlyValued ? $completeGroups : $groupsForCards)
            ->sortByDesc(fn ($lines) => $lines->contains(fn ($l) => $l['margin'] === null) ? -INF : $lines->sum('margin'));
        $invoicePage = $this->paginateRows($invoiceGroups, 'invoicePage');
    @endphp

    <x-filament::section :heading="__('Per delivery order')">
        @if (count($report['rows']))
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="{{ $head }}">{{ __('Date') }}</th>
                            <th class="{{ $head }}">{{ __('Invoice') }}</th>
                            <th class="{{ $head }}">{{ __('Customer') }}</th>
                            <th class="{{ $head }} text-end">Kg</th>
                            <th class="{{ $head }} text-end">{{ __('Margin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Daftar ringkas: tanggal, faktur, customer, kg, margin. Penjualan, HPP dan rincian ada di halaman detail faktur (Owner, 24 Oktober 2026). --}}
                        @foreach ($invoicePage as $doNumber => $lines)
                            @php
                                $first = $lines->first();
                                $margin = $lines->contains(fn ($l) => $l['margin'] === null) ? null : $lines->sum('margin');
                                $detailUrl = \App\Filament\Admin\Pages\SalesCostReportDetail::getUrl(['order' => $first['delivery_order_id']]);
                            @endphp
                            <tr class="{{ $row }} cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap py-3 pe-4 tabular-nums"><a href="{{ $detailUrl }}" class="block">{{ \Carbon\Carbon::parse($first['date'])->format('d/m/y') }}</a></td>
                                <td class="whitespace-nowrap py-3 pe-4"><a href="{{ $detailUrl }}" class="block font-medium text-primary-600 dark:text-primary-400">{{ $first['invoice'] ?? '-' }}</a></td>
                                <td class="py-3 pe-4"><a href="{{ $detailUrl }}" class="block">{{ $first['customer'] }}</a></td>
                                <td class="whitespace-nowrap py-3 pe-4 text-end tabular-nums"><a href="{{ $detailUrl }}" class="block">{{ $number($lines->sum('kg')) }}</a></td>
                                <td class="whitespace-nowrap py-3 text-end tabular-nums"><a href="{{ $detailUrl }}" class="block">{{ $rupiah($margin) }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-4 font-semibold" colspan="3">{{ __('Total') }}</td>
                            <td class="whitespace-nowrap py-3 pe-4 text-end font-semibold tabular-nums">{{ $number($total['kg']) }}</td>
                            <td class="whitespace-nowrap py-3 text-end font-semibold tabular-nums">{{ $total['margin_rows'] > 0 ? $rupiah($total['margin']) : '-' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-3"><x-filament::pagination :paginator="$invoicePage" /></div>

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

    @if (count($report['by_product']))
        @php
            $productGroups = collect($report['by_product'])->sortByDesc(fn ($g) => $g['margin_rows'] > 0 ? $g['margin'] : -INF);
            $productPage = $this->paginateRows($productGroups, 'productPage');
        @endphp
        <x-filament::section :heading="__('Per product')">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="{{ $head }}">{{ __('Product') }}</th>
                            <th class="{{ $head }} text-end">Kg</th>
                            <th class="{{ $head }} text-end">{{ __('Margin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productPage as $group)
                            <tr class="{{ $row }}">
                                <td class="py-3 pe-4">{{ $group['name'] }}</td>
                                <td class="whitespace-nowrap py-3 pe-4 text-end tabular-nums">{{ $number($group['kg']) }}</td>
                                <td class="whitespace-nowrap py-3 text-end tabular-nums">{{ $group['margin_rows'] > 0 ? $rupiah($group['margin']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-4 font-semibold">{{ __('Total') }}</td>
                            <td class="whitespace-nowrap py-3 pe-4 text-end font-semibold tabular-nums">{{ $number($total['kg']) }}</td>
                            <td class="whitespace-nowrap py-3 text-end font-semibold tabular-nums">{{ $total['margin_rows'] > 0 ? $rupiah($total['margin']) : '-' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-3"><x-filament::pagination :paginator="$productPage" /></div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
