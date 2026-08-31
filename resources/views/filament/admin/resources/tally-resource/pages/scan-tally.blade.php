{{--
    Halaman pemindaian tally.

    Dua hal yang dulu membuatnya sesak. Pertama, kolom Max POD Age memakai
    sepertiga lebar padahal isinya paling banyak dua angka, sehingga kotak
    pindai -- satu-satunya isian yang benar-benar dipakai sepanjang hari --
    justru menjadi sempit. Kedua, ringkasan PO punya enam kolom di separuh
    layar, sehingga kolom paling kanan terpotong.

    Berat dan jumlah box kini digabung menjadi satu kolom berbentuk
    "12,22 / 1". Keduanya memang selalu dibaca berbarengan, dan menyatukannya
    membebaskan satu kolom penuh tanpa menghilangkan satu angka pun.

    DUA KOLOM SELALU, tanpa breakpoint. Yang dipegang operator adalah alat
    pemindai, bukan tetikus: begitu ringkasan PO turun ke bawah daftar, ia
    harus menggulir bolak-balik hanya untuk melihat sisa kebutuhan barang.
    Pernah dicoba menyejajarkannya hanya mulai breakpoint terbesar, dan
    hasilnya justru menumpuk di layar Owner.

    Ringkasannya juga DILEKATKAN (sticky). Satu tally bisa berisi ratusan
    baris, jadi sekadar bersebelahan belum cukup -- tanpa dilekatkan,
    ringkasannya ikut tergulir hilang begitu daftarnya memanjang.
--}}
<x-filament-panels::page>
    <div class="grid grid-cols-2 gap-6 items-start w-full">

        {{-- Kiri: pemindai dan daftar hasil pindai --}}
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <form wire:submit.prevent="scan">
                    <div class="flex gap-3 items-end">
                        <div class="flex-1 min-w-0">
                            <label for="barcode_input" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                {{ __('Scan Barcode Here') }}
                            </label>
                            <input
                                id="barcode_input"
                                type="text"
                                wire:model="barcode"
                                placeholder="{{ __('Scan Barcode Here') }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-center text-xl font-bold py-3"
                                autofocus
                                autocomplete="off"
                                required
                            >
                        </div>

                        {{--
                            Lebarnya dipatok, bukan sepersekian layar: isinya
                            paling banyak dua angka, dan sisa ruangnya lebih
                            berguna untuk kotak pindai di sebelahnya.
                        --}}
                        <div class="w-20 shrink-0">
                            <label for="pod_limit_input" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                {{ __('Max POD') }}
                            </label>
                            <input
                                id="pod_limit_input"
                                type="number"
                                required
                                min="0"
                                max="99"
                                wire:model.live="podLimit"
                                placeholder="0"
                                title="{{ __('Maximum pack age in days. Older items turn red and can be relabeled.') }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-center text-xl font-bold py-3"
                            >
                        </div>
                    </div>
                    <button type="submit" class="hidden">{{ __('Scan') }}</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{ $this->table }}
            </div>
        </div>

        {{-- Kanan: ringkasan PO, melekat supaya tetap terlihat saat menggulir --}}
        <div class="sticky top-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-bold mb-3 text-gray-950 dark:text-white">{{ __('PO Summary') }}</h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-2 font-semibold">{{ __('Product') }}</th>
                            <th class="py-2 px-2 text-right font-semibold whitespace-nowrap">{{ __('PO') }}</th>
                            <th class="py-2 px-2 text-right font-semibold whitespace-nowrap">{{ __('Scan / Box') }}</th>
                            <th class="py-2 px-2 text-right font-semibold whitespace-nowrap">{{ __('Balance') }}</th>
                            <th class="py-2 pl-2 font-semibold">{{ __('Notes') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $totalPo = 0;
                            $totalScan = 0;
                            $totalBox = 0;
                        @endphp

                        @foreach($this->getSummaryData() as $row)
                            @php
                                $totalPo += $row['po_weight'];
                                $totalScan += $row['scanned_weight'];
                                $totalBox += $row['scanned_box'];
                            @endphp
                            <tr>
                                <td class="py-2 pr-2 font-medium text-gray-900 dark:text-white">{{ $row['product_name'] }}</td>
                                <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap">{{ number_format($row['po_weight'], 2) }}</td>
                                <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap font-semibold text-primary-600 dark:text-primary-400">
                                    {{ number_format($row['scanned_weight'], 2) }}
                                    <span class="font-normal text-gray-400 dark:text-gray-500">/ {{ $row['scanned_box'] }}</span>
                                </td>
                                {{--
                                    Kelebihan pindai berwarna merah; kekurangan
                                    tidak. Kurang itu pekerjaan yang belum
                                    selesai, lebih itu barang yang tidak
                                    dipesan.
                                --}}
                                <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap font-medium {{ $row['balance'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                                    {{ number_format($row['balance'], 2) }}
                                </td>
                                <td class="py-2 pl-2 text-gray-500 dark:text-gray-400 text-xs">{{ $row['notes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        @php
                            $totalBalance = $totalScan - $totalPo;
                        @endphp
                        <tr class="border-t-2 border-gray-200 dark:border-gray-800 font-bold text-gray-900 dark:text-white">
                            <td class="py-2 pr-2">{{ __('Total') }}</td>
                            <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap">{{ number_format($totalPo, 2) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap">
                                {{ number_format($totalScan, 2) }}
                                <span class="font-normal text-gray-400 dark:text-gray-500">/ {{ $totalBox }}</span>
                            </td>
                            <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap {{ $totalBalance > 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">
                                {{ number_format($totalBalance, 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('focus-barcode', () => {
            setTimeout(() => {
                const input = document.getElementById('barcode_input');
                if (input) input.focus();
            }, 50);
        });

        document.addEventListener('auto-print', (event) => {
            window.open(event.detail.url, '_blank');
        });

        // Initial focus on mount
        window.addEventListener('load', () => {
            const input = document.getElementById('barcode_input');
            if (input) input.focus();
        });

        // Livewire page update hook
        document.addEventListener('livewire:init', () => {
            Livewire.on('focus-barcode', () => {
                setTimeout(() => {
                    const input = document.getElementById('barcode_input');
                    if (input) input.focus();
                }, 50);
            });
        });
    </script>
</x-filament-panels::page>
