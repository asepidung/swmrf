{{--
    Neraca bahan vs hasil repack.

    Keputusan Project Owner, 31 Agustus 2026: selisih yang tidak wajar diberi
    PERINGATAN, bukan ditolak. Alasannya dari lapangan -- dalam praktiknya
    sering ada barang lain yang ikut masuk, dan menolak penyimpanan akan
    menghentikan pekerjaan yang sebenarnya sah.

    Yang dibandingkan hanya TOTAL bahan lawan TOTAL hasil, bukan per item.
    Repack daging tidak berpasangan satu-satu: beberapa item bisa menjadi satu,
    dan satu item bisa menjadi beberapa.

    Warna sebelumnya terbalik maknanya -- hasil yang lebih berat daripada
    bahan diwarnai hijau, sementara susut biasa diwarnai merah. Padahal susut
    kecil itu wajar, dan hasil yang melebihi bahan justru yang mustahil secara
    fisik: memotong dan mengemas ulang tidak menambah berat.

    AMBANG PERSEN BELUM DITENTUKAN. Owner belum tahu berapa susut yang masih
    wajar, jadi persentasenya ditampilkan apa adanya di sini supaya angkanya
    bisa diamati dulu dari pemakaian sehari-hari. Peringatan keras hanya untuk
    kasus yang tidak butuh ambang: hasil lebih berat daripada bahannya.
--}}
@php
    $balance = $totalHasilQty - $totalBahanQty;
    $isImpossible = $balance > 0.001;
    $shrinkPercent = $totalBahanQty > 0 ? abs($balance) / $totalBahanQty * 100 : 0;
@endphp

<div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800">
    <div class="flex justify-between items-center">
        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            {{ __('SELISIH (BALANCE)') }}
        </span>

        <span @class([
            'text-sm font-black px-3 py-1 rounded-lg',
            'bg-danger-50 text-danger-700 dark:bg-danger-900/20 dark:text-danger-400' => $isImpossible,
            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => ! $isImpossible,
        ])>
            {{ number_format($balance, 2) }} Kg
            @if ($totalBahanQty > 0)
                <span class="font-normal">({{ number_format($shrinkPercent, 1) }}%)</span>
            @endif
        </span>
    </div>

    @if ($isImpossible)
        <div class="mt-2 flex items-start gap-2 rounded-lg bg-danger-50 p-3 dark:bg-danger-900/20">
            <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-danger-600 dark:text-danger-400" />
            <div class="text-sm text-danger-700 dark:text-danger-400">
                <p class="font-bold">{{ __('Result is heavier than the source') }}</p>
                <p>
                    {{ __('Source :source kg, result :result kg. Please check whether something was recorded twice or a weight was mistyped. This does not block saving.', [
                        'source' => number_format($totalBahanQty, 2),
                        'result' => number_format($totalHasilQty, 2),
                    ]) }}
                </p>
            </div>
        </div>
    @endif
</div>
