<style>
    /* STYLE TABEL REKAP MINI */
    .table-mini {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        margin-bottom: 1.5rem;
    }

    .table-mini th {
        background-color: #f8fafc;
        padding: 6px;
        border: 1px solid #e2e8f0;
        text-align: center;
        font-weight: bold;
    }

    .table-mini td {
        padding: 6px;
        border: 1px solid #e2e8f0;
    }

    .dark .table-mini th {
        background-color: #1f2937;
        border-color: #374151;
    }

    .dark .table-mini td {
        border-color: #374151;
    }

    .repack-grid {
        display: grid !important;
        grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
        gap: 16px !important;
        align-items: start !important;
        width: 100% !important;
    }

    .repack-col-6 {
        grid-column: span 6 / span 6 !important;
    }

    @media (max-width: 1024px) {
        .repack-col-6 {
            grid-column: span 12 / span 12 !important;
        }
    }

    .repack-balance-card {
        margin-top: 16px;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-w: 448px;
        margin-left: auto;
        margin-right: auto;
        background-color: #f8fafc;
    }

    .dark .repack-balance-card {
        border-color: #374151;
        background-color: #111827;
    }
</style>

<div class="repack-grid mt-4">
    <!-- Left Column: Bahan Masuk Summary Table -->
    <div class="repack-col-6 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4 border dark:border-gray-800">
        <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">{{ __('BAHAN MASUK (INPUT)') }}</div>
        <table class="table-mini">
            <thead>
                <tr>
                    <th class="text-left">{{ __('NAMA BARANG') }}</th>
                    <th class="w-12 text-center">{{ __('BOX') }}</th>
                    <th class="w-20 text-right">{{ __('QTY') }}</th>
                </tr>
            </thead>
            <tbody class="text-gray-700 dark:text-gray-300">
                @php
                $bahan = \App\Models\RepackMaterial::with('product')->where('repack_id', $record->id)->get();
                $groupedBahan = $bahan->groupBy(fn($item) => $item->product->name ?? 'Unknown');
                $totalBahanBox = 0;
                $totalBahanQty = 0;
                @endphp
                @forelse($groupedBahan as $name => $items)
                    @php
                    $box = $items->count();
                    $qty = $items->sum('weight');
                    $totalBahanBox += $box;
                    $totalBahanQty += $qty;
                    @endphp
                    <tr>
                        <td>{{ $name }}</td>
                        <td class="text-center">{{ $box }}</td>
                        <td class="text-right">{{ number_format($qty, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center italic text-gray-500 py-3">{{ __('Belum ada bahan') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="font-bold bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white">
                <tr>
                    <td class="text-right py-2">{{ __('TOTAL') }}</td>
                    <td class="text-center py-2">{{ $totalBahanBox }}</td>
                    <td class="text-right py-2">{{ number_format($totalBahanQty, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Right Column: Hasil Keluar Summary Table -->
    <div class="repack-col-6 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4 border dark:border-gray-800">
        <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">{{ __('HASIL KELUAR (OUTPUT)') }}</div>
        <table class="table-mini">
            <thead>
                <tr>
                    <th class="text-left">{{ __('NAMA BARANG') }}</th>
                    <th class="w-12 text-center">{{ __('BOX') }}</th>
                    <th class="w-20 text-right">{{ __('QTY') }}</th>
                </tr>
            </thead>
            <tbody class="text-gray-700 dark:text-gray-300">
                @php
                $hasil = \App\Models\RepackResult::with('product')->where('repack_id', $record->id)->get();
                $groupedHasil = $hasil->groupBy(fn($item) => $item->product->name ?? 'Unknown');
                $totalHasilBox = 0;
                $totalHasilQty = 0;
                @endphp
                @forelse($groupedHasil as $name => $items)
                    @php
                    $box = $items->count();
                    $qty = $items->sum('weight');
                    $totalHasilBox += $box;
                    $totalHasilQty += $qty;
                    @endphp
                    <tr>
                        <td>{{ $name }}</td>
                        <td class="text-center">{{ $box }}</td>
                        <td class="text-right">{{ number_format($qty, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center italic text-gray-500 py-3">{{ __('Belum ada hasil') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="font-bold bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white">
                <tr>
                    <td class="text-right py-2">{{ __('TOTAL') }}</td>
                    <td class="text-center py-2">{{ $totalHasilBox }}</td>
                    <td class="text-right py-2">{{ number_format($totalHasilQty, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Lost / Balance -->
@php
    $balance = $totalHasilQty - $totalBahanQty;
    $isImpossible = $balance > 0.001;
    $shrinkPercent = $totalBahanQty > 0 ? abs($balance) / $totalBahanQty * 100 : 0;
@endphp
<div class="repack-balance-card">
    <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; color: #6b7280;">{{ __('SELISIH (BALANCE)') }}</span>
    {{--
        Warna sebelumnya terbalik maknanya: hasil yang lebih berat daripada
        bahan diwarnai hijau, sementara susut biasa diwarnai merah. Susut kecil
        itu wajar; hasil yang melebihi bahan justru mustahil secara fisik.
    --}}
    <span style="font-size: 14px; font-weight: 900; padding: 4px 12px; border-radius: 8px; font-family: monospace; {{ $isImpossible ? 'background-color: rgba(239, 68, 68, 0.15); color: #ef4444;' : 'background-color: rgba(107, 114, 128, 0.15); color: #6b7280;' }}">
        {{ number_format($balance, 2) }} Kg
        @if ($totalBahanQty > 0)
            <span style="font-weight: 400;">({{ number_format($shrinkPercent, 1) }}%)</span>
        @endif
    </span>
</div>
@if ($isImpossible)
    <div style="margin-top: 8px; padding: 10px; border-radius: 8px; background-color: rgba(239, 68, 68, 0.1); color: #b91c1c; font-size: 12px;">
        <strong>{{ __('Result is heavier than the source') }}</strong><br>
        {{ __('Source :source kg, result :result kg. Please check whether something was recorded twice or a weight was mistyped. This does not block saving.', [
            'source' => number_format($totalBahanQty, 2),
            'result' => number_format($totalHasilQty, 2),
        ]) }}
    </div>
@endif
