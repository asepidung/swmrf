@php
    $number = fn ($value): string => number_format((float) $value, 0, ',', '.');
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
            </tr>
        </thead>
        <tbody>
            @forelse ($summary['bom'] as $line)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-3 pe-4">{{ $line['material'] }}</td>
                    <td class="py-3 text-end tabular-nums">{{ $number($line['qty']) }} {{ __('pcs') }}</td>
                </tr>
            @empty
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-3 text-gray-500 dark:text-gray-400" colspan="2">{{ __('No material usage according to the BOM.') }}</td>
                </tr>
            @endforelse

            {{-- Drylog diisi manual; ia bagian dari pemakaian material. --}}
            <tr>
                <td class="py-3 pe-4">
                    {{ __('Drylog / Pad Absorber') }}
                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">({{ __('entered manually') }})</span>
                </td>
                <td class="py-3 text-end tabular-nums">
                    @if ($summary['drylog'] === null)
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Not filled in yet.') }}</span>
                    @else
                        {{ $number($summary['drylog']) }} {{ __('pcs') }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>
