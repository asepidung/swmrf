<x-filament-panels::page>
    <style>
        .fi-header {
            display: none !important;
        }

        /* Auto hide sidebar and expand main content on this page */
        aside.fi-sidebar {
            display: none !important;
        }
        .fi-main-ctn {
            padding-inline-start: 0 !important;
        }
        :root {
            --sidebar-width: 0px !important;
            --collapsed-sidebar-width: 0px !important;
        }

        table.fi-ta-table th,
        table.fi-ta-table tbody td {
            padding-top: 0.25rem !important;
            padding-bottom: 0.25rem !important;
            padding-left: 8px !important;
            padding-right: 8px !important;
            height: auto !important;
        }

        table.fi-ta-table tbody td>div,
        table.fi-ta-table tbody td>div>div,
        table.fi-ta-table tbody td>div>div>div {
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            min-height: unset !important;
            line-height: 1.2 !important;
            gap: 0 !important;
        }

        .fi-ta-text,
        .fi-ta-text-item,
        .fi-ta-text-item-label {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            line-height: 1.1 !important;
            font-size: 13px !important;
            white-space: nowrap !important;
            letter-spacing: -0.1px !important;
        }

        .fi-badge {
            padding: 2px 6px !important;
            min-height: 18px !important;
            line-height: 18px !important;
            font-size: 11px !important;
        }

        .fi-ta-actions {
            gap: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            justify-content: center !important;
        }

        .fi-ta-actions button {
            padding: 4px !important;
            min-height: 24px !important;
            height: 24px !important;
            width: 24px !important;
            margin: 0 !important;
        }

        .fi-ta-actions button svg {
            width: 16px !important;
            height: 16px !important;
        }
    </style>

    <div class="mb-6 flex items-center justify-between rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <x-filament::button
            href="{{ \App\Filament\Admin\Resources\BoningResource::getUrl('index') }}"
            tag="a"
            color="gray"
            icon="heroicon-m-arrow-left">
            {{ __('DATA BONING') }}
        </x-filament::button>

        <div class="text-lg font-bold uppercase tracking-wider text-gray-950 dark:text-white">
            {{ __('BATCH') }}: <span class="text-primary-600 dark:text-primary-400">{{ $record->doc_no }}</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 32% 1fr; gap: 1.5rem; align-items: start; width: 100%;">

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="position: sticky; top: 1.5rem;">
            @if($record->kunci == 1)
                <h3 class="text-lg font-bold mb-4">{{ __('Production Summary') }}</h3>
                @include('filament.resources.boning-resource.pages.view-summary', ['summary' => $this->getProductionSummary()])
            @else
                <form wire:submit.prevent="create">

                    {{ $this->form }}

                    <div class="mt-6 w-full">
                        <x-filament::button
                            id="submit_btn_label"
                            type="submit"
                            size="xl"
                            class="w-full">
                            {{ __('PRINT & SAVE LABEL') }}
                        </x-filament::button>
                    </div>

                </form>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            {{ $this->table }}
        </div>

    </div>

    <script>
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

        document.addEventListener('refreshTable', () => {
            setTimeout(() => {
                const productContainer = document.querySelector('.product-select-container');
                if (productContainer) {
                    const focusTarget = productContainer.querySelector('button, input');
                    if (focusTarget) focusTarget.focus();
                }
            }, 100);
        });

        document.addEventListener('auto-print', (event) => {
            window.open(event.detail.url, '_blank');
        });
    </script>
</x-filament-panels::page>
