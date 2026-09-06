<x-filament-panels::page>
    {{ $this->form }}

<x-filament::section>
        <x-slot name="heading">{{ __('Most frequently ordered') }}</x-slot>

        <x-slot name="description">
            {{ __('Ranked by how many separate sales orders contain the product between :from and :until. Weight is shown as context, not as the ranking.', [
                'from' => \Illuminate\Support\Carbon::parse($dari)->format('d M Y'),
                'until' => \Illuminate\Support\Carbon::parse($sampai)->format('d M Y'),
            ]) }}
        </x-slot>

        @if (! $adaKategori)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('There is no product category yet, so there is nothing to compare within.') }}
            </p>
        @elseif ($baris->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('No sales order in this range for this category.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 text-left font-semibold">#</th>
                            <th class="py-2 text-left font-semibold">{{ __('Code') }}</th>
                            <th class="py-2 text-left font-semibold">{{ __('Product Name') }}</th>
                            <th class="py-2 text-right font-semibold">{{ __('Times ordered') }}</th>
                            <th class="py-2 text-right font-semibold">{{ __('Weight (Kg)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $nomor => $satu)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-1.5 text-gray-500">{{ $nomor + 1 }}</td>
                                <td class="py-1.5 font-medium">{{ $satu->code }}</td>
                                <td class="py-1.5">{{ $satu->name }}</td>
                                <td class="py-1.5 text-right font-bold tabular-nums">{{ (int) $satu->frekuensi }}</td>
                                <td class="py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ number_format((float) $satu->berat, 2, ',', '.') }}
                                </td>

</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
