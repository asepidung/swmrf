<x-filament-panels::page>
    <style>
        /* Hilangkan header bawaan Filament */
        .fi-header {
            display: none !important;
        }

        /* --- HACK SUPER NUKLIR KHUSUS ISI TABEL --- */

        /* 1. Hajar padding Kiri-Kanan jadi super mepet (4px) biar gak scroll */
        table.fi-ta-table th,
        table.fi-ta-table tbody td {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            padding-left: 4px !important;
            /* DARI 12px JADI 4px */
            padding-right: 4px !important;
            /* DARI 12px JADI 4px */
            height: auto !important;
        }

        /* 2. Hajar <div> pembungkus lapisan 1, 2, dan 3 di dalam TD */
        table.fi-ta-table tbody td>div,
        table.fi-ta-table tbody td>div>div,
        table.fi-ta-table tbody td>div>div>div {
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            min-height: unset !important;
            line-height: 1.1 !important;
            gap: 0 !important;
        }

        /* 3. Rapatkan elemen teks dan paksa 1 baris */
        .fi-ta-text,
        .fi-ta-text-item,
        .fi-ta-text-item-label {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 1 !important;
            font-size: 12px !important;
            /* Turunin setengah poin biar makin aman */
            white-space: nowrap !important;
            /* Paksa teks gak turun ke baris bawah */
            letter-spacing: -0.2px !important;
            /* Rapetin jarak antar huruf dikit banget */
        }

        /* 4. Kecilin ukuran Badge (Tulisan C / F) */
        .fi-badge {
            padding: 0px 4px !important;
            min-height: 16px !important;
            line-height: 16px !important;
            font-size: 10px !important;
        }

        /* 5. Kecilin kotak Action (Tombol Delete) biar gak makan tempat di ujung */
        .fi-ta-actions {
            gap: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            justify-content: center !important;
        }

        .fi-ta-actions button {
            padding: 2px !important;
            min-height: 22px !important;
            height: 22px !important;
            width: 22px !important;
            margin: 0 !important;
        }

        .fi-ta-actions button svg {
            width: 14px !important;
            height: 14px !important;
        }
    </style>

    <div class="mb-6 flex items-center justify-between rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <x-filament::button
            href="{{ \App\Filament\Resources\BoningResource::getUrl('index') }}"
            tag="a"
            color="gray"
            icon="heroicon-m-arrow-left">
            DATA BONING
        </x-filament::button>

        <div class="text-lg font-bold uppercase tracking-wider text-gray-950 dark:text-white">
            BATCH: <span class="text-primary-600 dark:text-primary-400">{{ $record->doc_no }}</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 32% 1fr; gap: 1.5rem; align-items: start; width: 100%;">

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="position: sticky; top: 1.5rem;">
            <form wire:submit.prevent="create">

                {{ $this->form }}

                <div class="mt-6 w-full">
                    <x-filament::button
                        id="submit_btn_label"
                        type="submit"
                        size="xl"
                        class="w-full">
                        PRINT & SAVE LABEL
                    </x-filament::button>
                </div>

            </form>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            {{ $this->table }}
        </div>

    </div>

    <script>
        // 1. KENDALIKAN TOMBOL TAB (Dari Product langsung loncat ke Qty)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                const activeEl = document.activeElement;
                if (activeEl && activeEl.closest('.product-select-container')) {
                    e.preventDefault();
                    const qtyInput = document.getElementById('qty_input_field');
                    if (qtyInput) qtyInput.focus();
                }
            }
        });

        // 2. KEMBALIKAN KURSOR KE PRODUCT SETELAH PRINT (Ini yang diubah!)
        document.addEventListener('refreshTable', () => {
            setTimeout(() => {
                const productContainer = document.querySelector('.product-select-container');
                if (productContainer) {
                    // Cari elemen tombol/input bawaan dropdown Filament lalu fokuskan
                    const focusTarget = productContainer.querySelector('button, input');
                    if (focusTarget) focusTarget.focus();
                }
            }, 100);
        });

        // 3. AUTO-PRINT DI TAB BARU
        document.addEventListener('auto-print', (event) => {
            window.open(event.detail.url, '_blank');
        });
    </script>
</x-filament-panels::page>