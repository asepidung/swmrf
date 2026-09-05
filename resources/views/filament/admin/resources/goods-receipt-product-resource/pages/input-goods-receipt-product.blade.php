<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <!-- 1. Form Card (Header/Meta Data) -->
        <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-6">
            {{ $this->form }}
        </div>

        <!-- 2. Detail Items Table Card -->
        <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-6 flex flex-col gap-4">
            <h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('Item details') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm text-gray-700 dark:text-gray-300">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/5 text-gray-950 dark:text-white font-bold">
                            <th class="py-3 px-4 text-center">#</th>
                            <th class="py-3 px-4">Item Descriptions</th>
                            <th class="py-3 px-4 text-right">Order Qty</th>
                            <th class="py-3 px-4 text-right">Receive Qty (Kg)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @forelse($this->poItemsWithReceipt as $index => $row)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5">
                                <td class="py-3 px-4 text-center text-gray-400 font-medium">{{ $index + 1 }}</td>
                                <td class="py-3 px-4 font-bold text-gray-950 dark:text-white">{{ strtoupper($row['product_name']) }}</td>
                                <td class="py-3 px-4 text-right font-semibold text-gray-950 dark:text-white">{{ number_format($row['order_qty'], 2, '.', ',') }}</td>
                                <td class="py-3 px-4 text-right font-semibold text-gray-950 dark:text-white">{{ number_format($row['receive_weight'], 2, '.', ',') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center italic text-gray-400">{{ __('There are no items on this purchase order.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        @if($this->poItemsWithReceipt->isNotEmpty())
                            <tr class="bg-gray-50/50 dark:bg-white/5 border-t border-gray-200 dark:border-white/10 font-bold text-gray-950 dark:text-white">
                                <td colspan="2" class="py-3 px-4 text-right">Total:</td>
                                <td class="py-3 px-4 text-right">{{ number_format($this->tableTotals['total_order_qty'], 2, '.', ',') }}</td>
                                <td class="py-3 px-4 text-right">{{ number_format($this->tableTotals['total_receive_weight'], 2, '.', ',') }}</td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- 3. Form Footer (Save Changes) -->
        @if(! $record->is_locked)
            <div class="flex justify-end gap-3">
                <x-filament::button wire:click="saveGr" color="primary">
                    Save Changes
                </x-filament::button>
            </div>
        @endif
    </div>

    <!-- Modal Pilihan Tindakan Lanjutan -->
    <x-filament::modal id="next-step-modal" width="md">
        <x-slot name="heading">
            {{ __('Saved') }}
        </x-slot>
        
        <x-slot name="description">
            {{ __('Goods receipt header saved. How do you want to carry on receiving the goods?') }}
        </x-slot>

        <div class="flex flex-col gap-3 mt-4">
            <x-filament::button tag="a" href="{{ \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('scan', ['record' => $record->id]) }}" icon="heroicon-m-qr-code" size="lg">
                {{ __('Start scanning (barcode)') }}
            </x-filament::button>
            
            <x-filament::button tag="a" href="{{ \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('labeling', ['record' => $record->id]) }}" color="gray" icon="heroicon-m-tag" size="lg">
                {{ __('Start labelling (manual)') }}
            </x-filament::button>
            
            <x-filament::button tag="a" href="{{ \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('index') }}" color="gray" variant="outlined" size="lg">
                {{ __('Later (back to the list)') }}
            </x-filament::button>
        </div>
    </x-filament::modal>
</x-filament-panels::page>
