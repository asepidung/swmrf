@php
    $posisi = $this->posisi();
    $buckets = $posisi['buckets'];
    $batas = $this->batas();
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    {{-- Batas waktunya ditulis TERANG-TERANGAN, bukan disimpulkan sendiri
         oleh pembacanya. "Posisi tanggal 1 September" bisa berarti pagi,
         siang, atau tengah malam; yang dipakai di sini akhir hari. --}}
    <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="text-sm text-gray-950 dark:text-white">
            <span class="font-semibold">{{ __('Position as at') }}</span>
            {{ $batas->format('d M Y') }} {{ __('at') }} <span class="font-mono">23:59:59</span>
            &mdash; {{ __('everything entered up to the end of that day is counted.') }}
        </p>

        {{-- Peringatan ini ikut setiap kali angka tanggal mundur ditampilkan.
             Angka yang benar tetapi disalahpahami sama merugikannya dengan
             angka yang salah. --}}
        <p class="mt-2 text-sm text-warning-600 dark:text-warning-400">
            <span class="font-semibold">{{ __('The date is the time of ENTRY, not the document date.') }}</span>
            {{ __('Goods that arrived on Monday but were entered on Tuesday count on Tuesday.') }}
        </p>
    </div>

    @if ($this->sedangOpname())
        <div class="fi-section rounded-xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-base font-semibold text-danger-600 dark:text-danger-400">
                {{ __('A stock count is running, so the numbers are hidden.') }}
            </p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('This page answers exactly the question the physical count is supposed to answer.') }}
            </p>
        </div>
    @elseif ($buckets === [])
        <div class="fi-section rounded-xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('No stock had been recorded by that date.') }}
            </p>
        </div>
    @else
        <div class="fi-section overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th rowspan="2" class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">{{ __('Code') }}</th>
                        <th rowspan="2" class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">{{ __('Product Name') }}</th>
                        @php
                            $perGudang = [];
                            foreach ($buckets as $bucket) {
                                $perGudang[$bucket['warehouse']][] = $bucket;
                            }
                        @endphp
                        @foreach ($perGudang as $namaGudang => $kolomGudang)
                            <th colspan="{{ count($kolomGudang) }}" class="border-l border-gray-200 px-3 py-2 text-center font-semibold uppercase text-gray-950 dark:border-white/10 dark:text-white">
                                {{ $namaGudang }}
                            </th>
                        @endforeach
                        <th rowspan="2" class="border-l border-gray-200 px-3 py-2 text-right font-semibold text-gray-950 dark:border-white/10 dark:text-white">{{ __('Total') }}</th>
                    </tr>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        @foreach ($buckets as $bucket)
                            <th class="border-l border-gray-200 px-3 py-2 text-center text-xs font-semibold uppercase text-gray-500 dark:border-white/10 dark:text-gray-400">
                                {{ $bucket['grade'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($posisi['kategori'] as $namaKategori => $isi)
                        <tr class="bg-gray-100 dark:bg-white/5">
                            <td colspan="2" class="px-3 py-2 font-bold uppercase text-gray-950 dark:text-white">{{ $namaKategori }}</td>
                            @foreach ($buckets as $bucket)
                                <td class="border-l border-gray-200 px-3 py-2 text-right font-bold text-gray-950 dark:border-white/10 dark:text-white">
                                    {{ ($isi['kolom'][$bucket['key']] ?? 0) > 0 ? number_format($isi['kolom'][$bucket['key']], 2, '.', ',') : '' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 px-3 py-2 text-right font-bold text-gray-950 dark:border-white/10 dark:text-white">
                                {{ number_format($isi['total'], 2, '.', ',') }}
                            </td>
                        </tr>

                        @foreach ($isi['produk'] as $satu)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-3 py-1.5 font-medium text-gray-950 dark:text-white">{{ $satu['kode'] }}</td>
                                <td class="px-3 py-1.5 text-gray-950 dark:text-white">{{ $satu['nama'] }}</td>
                                @foreach ($buckets as $bucket)
                                    <td class="border-l border-gray-100 px-3 py-1.5 text-right text-gray-950 dark:border-white/5 dark:text-white">
                                        {{ ($satu['kolom'][$bucket['key']] ?? 0) > 0 ? number_format($satu['kolom'][$bucket['key']], 2, '.', ',') : '' }}
                                    </td>
                                @endforeach
                                <td class="border-l border-gray-100 px-3 py-1.5 text-right font-semibold text-gray-950 dark:border-white/5 dark:text-white">
                                    {{ number_format($satu['total'], 2, '.', ',') }}
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>

                <tfoot>
                    <tr class="border-t-2 border-gray-300 dark:border-white/20">
                        <td colspan="2" class="px-3 py-2 text-right font-bold uppercase text-gray-950 dark:text-white">{{ __('Total') }}</td>
                        @foreach ($buckets as $bucket)
                            <td class="border-l border-gray-200 px-3 py-2 text-right font-bold text-gray-950 dark:border-white/10 dark:text-white">
                                {{ number_format($posisi['total'][$bucket['key']] ?? 0, 2, '.', ',') }}
                            </td>
                        @endforeach
                        <td class="border-l border-gray-200 px-3 py-2 text-right font-bold text-gray-950 dark:border-white/10 dark:text-white">
                            {{ number_format($posisi['grand'], 2, '.', ',') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-filament-panels::page>
