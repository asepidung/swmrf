<div class="text-sm">
    @if ($rows->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">{{ __('No bill of material yet.') }}</p>
    @else
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="py-2 pe-4 font-semibold">{{ __('Material') }}</th>
                    <th class="py-2 pe-4 font-semibold">{{ __('Basis') }}</th>
                    <th class="py-2 pe-4 text-end font-semibold">{{ __('Quantity') }}</th>
                    <th class="py-2 font-semibold">{{ __('Note') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2 pe-4">{{ $row->material?->name ?? '-' }}</td>
                        <td class="py-2 pe-4">{{ __($row->labelBasis()) }}</td>
                        <td class="py-2 pe-4 text-end tabular-nums">
                            @if ($row->jumlahnyaTidakTetap())
                                {{ __('Not fixed') }}
                            @else
                                {{ number_format($row->quantity, 0, ',', '.') }} {{ __('pcs') }}
                            @endif
                        </td>
                        <td class="py-2">{{ $row->note }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
