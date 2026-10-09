@php
    $report = $this->getReport();
    $number = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $rupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
    $head = 'py-2 text-xs font-medium text-gray-500 dark:text-gray-400';
    $row = 'border-t border-gray-100 dark:border-white/5';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Only locked Boning and Repack documents are counted.') }}
            {{ count($report['documents']) }} {{ __('documents') }}.
        </p>
    </x-filament::section>

    <x-filament::section :heading="__('Usage per BOM')">
        @if (count($report['bom']))
            <table class="w-full table-fixed text-left text-sm">
                <thead>
                    <tr>
                        <th class="{{ $head }}">{{ __('Material') }}</th>
                        <th class="{{ $head }} w-40 text-end">{{ __('Quantity') }}</th>
                        <th class="{{ $head }} w-48 text-end">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['bom'] as $line)
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-4">{{ $line['material'] }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $number($line['qty']) }} {{ __('pcs') }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $rupiah($line['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="{{ $row }}">
                        <td class="py-3 pe-4">
                            {{ __('Drylog / Pad Absorber') }}
                            <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">({{ __('entered manually') }})</span>
                        </td>
                        <td class="py-3 text-end tabular-nums">
                            {{ $number($report['drylog']['qty']) }} {{ __('pcs') }}
                        </td>
                        <td class="py-3 text-end tabular-nums">{{ $rupiah($report['drylog']['amount']) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="{{ $row }}">
                        <td class="py-3 pe-4 font-semibold" colspan="2">{{ __('Total usage value') }}</td>
                        <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($report['usage_total']) }}</td>
                    </tr>
                </tfoot>
            </table>
            @if ($report['usage_unpriced'] > 0)
                <div class="mt-3">
                    <x-filament::badge color="warning" size="sm" class="inline-flex">{{ __('no price yet') }}</x-filament::badge>
                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">{{ $report['usage_unpriced'] }} {{ __('rows without a price are counted as Rp 0.') }}</span>
                </div>
            @endif
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No locked documents in this period.') }}</p>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('Material Waste')">
        @if (count($report['wastes']))
            <table class="w-full table-fixed text-left text-sm">
                <thead>
                    <tr>
                        <th class="{{ $head }}">{{ __('Material') }}</th>
                        <th class="{{ $head }} w-36 text-end">{{ __('Quantity') }}</th>
                        <th class="{{ $head }} w-48 text-end">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['wastes'] as $line)
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-4">{{ $line['material'] }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $number($line['qty']) }} {{ __('pcs') }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $rupiah($line['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="py-3 pe-4 font-semibold">{{ __('Total wasted') }}</td>
                        <td class="py-3 text-end font-semibold tabular-nums">{{ $number($report['waste_total']['qty']) }} {{ __('pcs') }}</td>
                        <td class="py-3 text-end font-semibold tabular-nums">{{ $rupiah($report['waste_total']['amount']) }}</td>
                    </tr>
                </tfoot>
            </table>
            @if ($report['unpriced'] > 0)
                <div class="mt-3">
                    <x-filament::badge color="warning" size="sm" class="inline-flex">{{ __('no price yet') }}</x-filament::badge>
                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">{{ $report['unpriced'] }} {{ __('rows without a price are counted as Rp 0.') }}</span>
                </div>
            @endif
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Nothing was wasted.') }}</p>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('Per document')">
        @if (count($report['documents']))
            <table class="w-full table-fixed text-left text-sm">
                <thead>
                    <tr>
                        <th class="{{ $head }} w-24">{{ __('Process') }}</th>
                        <th class="{{ $head }}">{{ __('Document') }}</th>
                        <th class="{{ $head }} w-28">{{ __('Date') }}</th>
                        <th class="{{ $head }} w-28 text-end">{{ __('Drylog / Pad Absorber') }}</th>
                        <th class="{{ $head }} w-28 text-end">{{ __('Wasted') }}</th>
                        <th class="{{ $head }} w-40 text-end">{{ __('Usage value') }}</th>
                        <th class="{{ $head }} w-40 text-end">{{ __('Waste value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['documents'] as $document)
                        <tr class="{{ $row }}">
                            <td class="py-3 pe-2">{{ $document['kind'] === 'boning' ? 'Boning' : 'Repack' }}</td>
                            <td class="py-3 pe-4">
                                <a class="text-primary-600 hover:underline dark:text-primary-400" href="{{ $this->documentUrl($document['kind'], $document['id']) }}">{{ $document['doc_no'] }}</a>
                            </td>
                            <td class="py-3 pe-2 tabular-nums">{{ \Carbon\Carbon::parse($document['date'])->format('d/m/Y') }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $document['drylog'] === null ? '-' : $number($document['drylog']) }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $number($document['waste_qty']) }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $rupiah($document['usage_amount']) }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $rupiah($document['waste_amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No locked documents in this period.') }}</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
