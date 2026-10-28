<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-6">
            {{ $this->form }}
        </div>

        <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-6 flex flex-col gap-4">
            <h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('Item details') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm text-gray-700 dark:text-gray-300">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/5 text-gray-950 dark:text-white font-bold">
                            <th class="py-3 px-4 text-center">#</th>
                            <th class="py-3 px-4">Item Descriptions</th>
                            <th class="py-3 px-4 text-right">Order Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @forelse($this->poItems as $index => $item)
                            <tr>
                                <td class="py-3 px-4 text-center text-gray-400 font-medium">{{ $index + 1 }}</td>
                                <td class="py-3 px-4 font-bold text-gray-950 dark:text-white">{{ strtoupper($item->product->name ?? '-') }}</td>
                                <td class="py-3 px-4 text-right font-semibold text-gray-950 dark:text-white">{{ number_format($item->qty, 2, '.', ',') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center italic text-gray-400">{{ __('There are no items on this purchase order.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <x-filament::button wire:click="saveGr" color="primary">
                Save Changes
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
