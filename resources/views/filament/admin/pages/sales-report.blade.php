<x-filament-panels::page>
    {{ $this->form }}

    @php
        $rupiah = fn (?float $angka): string => 'Rp ' . number_format((float) ($angka ?? 0), 0, ',', '.');
    @endphp

    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total') }} {{ $tahun }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $rupiah($totalSekarang) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total') }} {{ $tahunSebelumnya }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $rupiah($totalSebelumnya) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Change against :year', ['year' => $tahunSebelumnya]) }}
            </div>

            {{--
                Strip, bukan angka, kalau tahun lalu nol. Naik dari nol bukan
                "naik seratus persen" -- itu pembagian yang tidak ada artinya,
                dan angka yang tidak berdasar terdengar lebih meyakinkan
                daripada tanda strip yang jujur.
            --}}
            <div @class([
                'mt-1 text-2xl font-bold',
                'text-gray-400' => $selisihPersen === null,
                'text-success-600 dark:text-success-400' => $selisihPersen !== null && $selisihPersen >= 0,
                'text-danger-600 dark:text-danger-400' => $selisihPersen !== null && $selisihPersen < 0,
            ])>
                @if ($selisihPersen === null)
                    &mdash;
                @else
                    {{ $selisihPersen > 0 ? '+' : '' }}{{ number_format($selisihPersen, 1, ',', '.') }}%
                @endif
            </div>

            @if ($selisihPersen === null)
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('No sales in :year to compare against.', ['year' => $tahunSebelumnya]) }}
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('Monthly sales') }}</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="py-2 text-left font-semibold">{{ __('Month') }}</th>
                        <th class="py-2 text-right font-semibold">{{ $tahun }}</th>
                        <th class="py-2 text-right font-semibold">{{ $tahunSebelumnya }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($namaBulan as $nomor => $nama)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="py-1.5">{{ $nama }}</td>

                            {{--
                                Bulan yang belum terjadi ditulis strip, bukan
                                Rp 0. Nol berarti "tidak ada penjualan"; bulan
                                yang belum datang bukan itu.
                            --}}
                            <td class="py-1.5 text-right tabular-nums">
                                @if ($bulanan[$nomor] === null)
                                    <span class="text-gray-400">&mdash;</span>
                                @else
                                    {{ $rupiah($bulanan[$nomor]) }}
                                @endif
                            </td>

                            <td class="py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $rupiah($bulananSebelumnya[$nomor]) }}
                            </td>
                        </tr>
                    @endforeach

                    <tr class="font-bold">
                        <td class="py-2">{{ __('Total') }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $rupiah($totalSekarang) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $rupiah($totalSebelumnya) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Figures are amounts billed: subtotal plus other charges, less down payment and approved returns. Cost of goods is not included yet.') }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
